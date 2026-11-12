<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\ReportExport;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Role;
use App\Services\LeadSourceReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard & Báo cáo → Phân tích chất lượng nguồn Lead (Giám đốc, Sale Admin).
 *
 * Trả lời 1 câu hỏi: nguồn nào mang về Lead tốt (chốt được, ra doanh thu)?
 *   - Bộ lọc: khoảng ngày + kỳ nhanh (tháng / quý / 6 tháng / năm), chi nhánh, kênh liên hệ, nguồn (chọn nhiều)
 *   - 5 thẻ KPI có so sánh với kỳ liền trước cùng độ dài + đường xu hướng theo tháng
 *   - Biểu đồ: cơ cấu Lead, tỷ lệ chốt (vạch trung bình), doanh thu, Lead mới theo tháng, kết quả xử lý
 *   - Bảng tổng hợp: tô màu xanh / đỏ theo mức chênh so với trung bình + đánh giá nguồn
 * Tỷ lệ chốt = Lead chốt thành công (có Cơ hội Won) / Lead, tính theo ngày tạo Lead - xem App\Services\LeadSourceReport.
 * Xuất Excel: popup chọn kỳ, tiêu chí, sheet, cột và xem trước (trait ReportExport) - Giám đốc, Sale Admin.
 */
#[Layout('layouts.app')]
class LeadSources extends Component
{
    use ReportExport;

    // Bảng màu phân loại (thứ tự cố định) - dùng chung các trang báo cáo (LeadConversion::PALETTE)
    public const PALETTE = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];

    // Màu cố định theo nguồn - biểu đồ và bảng dùng chung để người xem nhận ra nguồn qua màu
    public const SOURCE_COLORS = [
        'Zalo'         => '#2a78d6',
        'Facebook'     => '#eb6834',
        'Website ODIN' => '#1baf7a',
        'TikTok'       => '#eda100',
        'Workshop'     => '#e87ba4',
        'Instagram'    => '#6250d6',
    ];

    public const OTHER_COLOR = '#9ca3af';

    // Kết quả xử lý Lead (mỗi Lead đúng 1 nhóm) → [màu nền, màu chữ trên thanh]
    public const OUTCOMES = [
        'won'            => ['#1f9d55', '#ffffff'],
        'in_progress'    => ['#8fb8ec', '#1f2937'],
        'failed'         => ['#eda5a5', '#1f2937'],
        'no_opportunity' => ['#d9dde3', '#1f2937'],
    ];

    // Tỷ lệ chốt theo nguồn: từ trung bình trở lên tô đậm, dưới trung bình tô nhạt
    public const RATE_COLORS = ['above' => '#2a78d6', 'below' => '#a9c8ef'];

    // Thẻ KPI: màu đường xu hướng khi tăng (giảm luôn đỏ)
    public const TREND_COLORS = ['count' => '#2a78d6', 'rate' => '#1baf7a', 'down' => '#e34948'];

    // Nguồn ít hơn số Lead này chưa đủ để đánh giá / chọn "cao nhất" (tỷ lệ chưa đáng tin)
    public const MIN_LEADS = 10;

    public const PRESETS = ['month', 'quarter', '6m', 'year'];

    /* ── Bộ lọc ───────────────────────────────────────────────────────── */

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $branch = '';

    #[Url(except: '')]
    public string $method = '';

    // Nguồn được chọn (rỗng = tất cả)
    #[Url(except: [])]
    public array $sources = [];

    public function resetFilters(): void
    {
        $this->reset('from', 'to', 'branch', 'method', 'sources');
    }

    public function hasFilters(): bool
    {
        return $this->from !== '' || $this->to !== '' || $this->branch !== '' || $this->method !== '' || $this->sources !== [];
    }

    /**
     * Kỳ nhanh: đặt Từ ngày / Đến ngày theo kỳ chứa ngày hôm nay.
     */
    public function preset(string $key): void
    {
        if ($range = $this->presetRange($key)) {
            [$this->from, $this->to] = $range;
        }
    }

    public function presetRange(string $key): ?array
    {
        $today = CarbonImmutable::today();
        $range = match ($key) {
            'month'   => [$today->startOfMonth(), $today->endOfMonth()],
            'quarter' => [$today->startOfQuarter(), $today->endOfQuarter()],
            '6m'      => [$today->startOfMonth()->subMonthsNoOverflow(5), $today->endOfMonth()],
            'year'    => [$today->startOfYear(), $today->endOfYear()],
            default   => null,
        };

        return $range ? [$range[0]->toDateString(), $range[1]->toDateString()] : null;
    }

    /**
     * Kỳ nhanh trùng với khoảng ngày đang chọn (để tô nút), null nếu là khoảng tự chọn.
     */
    public function activePreset(): ?string
    {
        foreach (self::PRESETS as $key) {
            if ($this->presetRange($key) === [$this->from, $this->to]) {
                return $key;
            }
        }

        return null;
    }

    /* ── Số liệu ──────────────────────────────────────────────────────── */

    #[Computed]
    public function totals(): array
    {
        return $this->report()->totals($this->filterValues());
    }

    /**
     * Kỳ đang xem và kỳ liền trước để so sánh.
     *   - Khoảng trọn tháng (kỳ nhanh): lùi đúng số tháng đó; khoảng tự chọn: lùi đúng số ngày.
     *   - Kỳ chưa kết thúc chỉ tính đến hôm nay và kỳ trước cũng cắt cùng độ dài
     *     (vd. 01-08/11 so với 01-08/10), tránh so một kỳ dở dang với một kỳ đủ.
     *   - Không chọn "Từ ngày": tính từ Lead sớm nhất và không so sánh.
     */
    #[Computed]
    public function period(): array
    {
        $f = $this->filterValues();
        $today = CarbonImmutable::today();
        $to = $f['to'] ? CarbonImmutable::parse($f['to']) : $today;
        $end = $to->greaterThan($today) ? $today : $to;
        $from = $f['from'] ? CarbonImmutable::parse($f['from']) : null;

        if (! $from) {
            $first = Lead::query()->min('created_at');
            $start = $first ? CarbonImmutable::parse($first)->startOfDay() : $end;

            return ['from' => $start->min($end), 'to' => $end, 'previous' => null, 'compare' => null];
        }

        if ($from->greaterThan($end)) {
            return ['from' => $from, 'to' => $from, 'previous' => null, 'compare' => null];
        }

        if ($from->day === 1 && $to->isSameDay($to->endOfMonth())) {
            $months = ($to->year - $from->year) * 12 + $to->month - $from->month + 1;
            $previous = [$from->subMonthsNoOverflow($months), $end->subMonthsNoOverflow($months)];
            $compare = match (true) {
                $months === 1                              => __('reports.lead_sources.compare.month'),
                $months === 3 && $from->month % 3 === 1    => __('reports.lead_sources.compare.quarter'),
                $months === 12 && $from->month === 1       => __('reports.lead_sources.compare.year'),
                default                                    => __('reports.lead_sources.compare.months', ['count' => $months]),
            };
        } else {
            $days = (int) $from->diffInDays($to) + 1;
            $previous = [$from->subDays($days), $end->subDays($days)];
            $compare = __('reports.lead_sources.compare.days', ['count' => $days]);
        }

        return ['from' => $from, 'to' => $end, 'previous' => $previous, 'compare' => $compare];
    }

    /**
     * Tổng của kỳ liền trước (cùng chi nhánh / kênh / nguồn), null nếu không so sánh.
     */
    #[Computed]
    public function previousTotals(): ?array
    {
        $previous = $this->period['previous'];
        if (! $previous) {
            return null;
        }

        return $this->report()->totals(['from' => $previous[0]->toDateString(), 'to' => $previous[1]->toDateString()]
            + $this->filterValues());
    }

    /**
     * Chỉ số theo nguồn có Lead trong phạm vi lọc, nhiều Lead trước.
     */
    #[Computed]
    public function bySource(): array
    {
        $rows = $this->report()->aggregate($this->filterValues(), 'source');
        usort($rows, fn ($a, $b) => $b['leads'] <=> $a['leads'] ?: strcmp($a['dim'], $b['dim']));

        return $rows;
    }

    /**
     * Các tháng của kỳ đang xem ('Y-m'), tối đa 36 tháng gần nhất.
     */
    #[Computed]
    public function months(): array
    {
        // Bỏ các tháng trước Lead đầu tiên của hệ thống (vd. "Năm nay" khi dữ liệu bắt đầu từ T5)
        $months = [];
        $first = Lead::query()->min('created_at');
        $cursor = $this->period['from']->startOfMonth();
        if ($first && ($firstMonth = CarbonImmutable::parse($first)->startOfMonth())->greaterThan($cursor)) {
            $cursor = $firstMonth->min($this->period['to']->startOfMonth());
        }
        $last = $this->period['to']->startOfMonth();
        while ($cursor->lessThanOrEqualTo($last)) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonth();
        }

        return array_slice($months, -36);
    }

    /**
     * Chỉ số theo tháng tạo Lead (đủ các tháng của kỳ, tháng chưa có Lead ghi 0).
     */
    #[Computed]
    public function byMonth(): array
    {
        $rows = array_column($this->report()->aggregate($this->filterValues(), 'month'), null, 'dim');

        return array_map(fn ($m) => $rows[$m] ?? $this->report()->derive(['dim' => $m]), $this->months);
    }

    /**
     * 5 thẻ KPI: giá trị, mức thay đổi so với kỳ trước, đường xu hướng theo tháng, chú thích.
     */
    #[Computed]
    public function kpis(): array
    {
        $t = $this->totals;
        $p = $this->previousTotals;
        $ls = fn ($key, $replace = []) => __('reports.lead_sources.kpi.'.$key, $replace);

        // Tăng / giảm: tỷ lệ tính bằng điểm %, số lượng & tiền tính bằng % thay đổi
        $delta = function (string $metric, bool $rate) use ($t, $p) {
            if (! $p || ! $p['leads']) {
                return null;
            }
            if ($rate) {
                return ['value' => round($t[$metric] - $p[$metric], 1), 'unit' => 'pt'];
            }

            return $p[$metric] > 0 ? ['value' => round(($t[$metric] - $p[$metric]) * 100 / $p[$metric], 1), 'unit' => 'pct'] : null;
        };

        $paid = $this->shortMoney($t['paid']);
        $bestPerLead = collect($this->bySource)->where('leads', '>=', self::MIN_LEADS)->sortByDesc('revenue_per_lead')->first();
        $bestValue = $bestPerLead ? $this->shortMoney($bestPerLead['revenue_per_lead']) : null;

        $cards = [
            ['leads', 'layers', false, [$this->fmtNum($t['leads']), ''],
                $ls('leads_hint', ['count' => count($this->bySource)])],
            ['conversion_rate', 'trending', true, [$this->fmtPct($t['conversion_rate']), ''],
                $ls('conversion_hint', ['count' => $this->fmtNum($t['converted'])])],
            ['win_rate', 'check-circle', true, [$this->fmtPct($t['win_rate']), ''],
                $ls('win_hint', ['count' => $this->fmtNum($t['won'])])],
            ['revenue', 'cash', false, $this->shortMoney($t['revenue'], true),
                $t['revenue'] > 0
                    ? $ls('revenue_hint', ['paid' => trim($paid[0].' '.$paid[1]), 'rate' => $this->fmtPct($t['paid'] * 100 / $t['revenue'], 0)])
                    : $ls('no_revenue')],
            ['revenue_per_lead', 'chart-line', false, $this->shortMoney($t['revenue_per_lead'], true),
                $bestPerLead && $bestPerLead['revenue_per_lead'] > 0
                    ? $ls('per_lead_hint', ['source' => $bestPerLead['dim'], 'value' => trim($bestValue[0].' '.$bestValue[1])])
                    : $ls('no_revenue')],
        ];

        return array_map(function ($card) use ($delta) {
            [$metric, $icon, $rate, $value, $hint] = $card;
            $change = $delta($metric, $rate);
            $color = $change && $change['value'] < 0 ? self::TREND_COLORS['down'] : self::TREND_COLORS[$rate ? 'rate' : 'count'];

            return [
                'key'   => $metric,
                'icon'  => $icon,
                'value' => $value,
                'delta' => $change,
                'hint'  => $hint,
                'color' => $color,
                'spark' => $this->sparkline(array_column($this->byMonth, $metric)),
            ];
        }, $cards);
    }

    /**
     * Cấu hình các biểu đồ (odinChart - resources/js/charts.js).
     */
    #[Computed]
    public function charts(): array
    {
        $t = $this->totals;
        $rows = $this->bySource;
        $labels = array_column($rows, 'dim');
        $colors = array_map(fn ($s) => $this->sourceColor($s), $labels);
        $sorted = function (string $metric) use ($rows) {
            usort($rows, fn ($a, $b) => $b[$metric] <=> $a[$metric] ?: $b['leads'] <=> $a['leads']);

            return $rows;
        };

        // Tỷ lệ chốt: vạch đứt = trung bình chung
        $byRate = $sorted('win_rate');
        $avg = $t['win_rate'];

        // Doanh thu: trục và nhãn rút gọn theo đơn vị của giá trị lớn nhất
        $byRevenue = $sorted('revenue');
        $max = max(array_column($rows, 'revenue') ?: [0]);
        [$divisor, $suffix] = match (true) {
            $max >= 1e9 => [1e9, ' '.__('reports.lead_sources.unit.billion')],
            $max >= 1e6 => [1e6, ' '.__('reports.lead_sources.unit.million')],
            default     => [1, ' ₫'],
        };

        // Lead mới theo tháng: mỗi nguồn 1 dãy cột chồng
        $cells = [];
        foreach ($this->report()->aggregate($this->filterValues(), 'month', 'source') as $r) {
            $cells[$r['group']][$r['dim']] = $r['leads'];
        }

        return [
            'share' => [
                'type' => 'doughnut', 'unit' => 'count', 'legend' => 'top', 'dataLabels' => 'percent', 'cutout' => '55%',
                'labels' => $labels,
                'series' => [['label' => __('reports.metric.leads'), 'data' => array_column($rows, 'leads'), 'colors' => $colors]],
            ],
            'win_rate' => [
                'type' => 'bar_h', 'unit' => 'percent', 'dataLabels' => 'end',
                'refLine' => ['value' => $avg, 'label' => __('reports.lead_sources.chart.average', ['value' => $this->fmtPct($avg)])],
                'labels' => array_column($byRate, 'dim'),
                'series' => [[
                    'label'  => __('reports.metric.win_rate'),
                    'data'   => array_column($byRate, 'win_rate'),
                    'colors' => array_map(fn ($r) => self::RATE_COLORS[$r['win_rate'] >= $avg ? 'above' : 'below'], $byRate),
                ]],
            ],
            'revenue' => [
                'type' => 'bar_h', 'unit' => 'money', 'dataLabels' => 'end', 'divisor' => $divisor, 'suffix' => $suffix,
                'labels' => array_column($byRevenue, 'dim'),
                'series' => [[
                    'label'       => __('reports.metric.revenue'),
                    'data'        => array_column($byRevenue, 'revenue'),
                    'colors'      => array_map(fn ($r) => $this->sourceColor($r['dim']), $byRevenue),
                    'valueLabels' => array_map(fn ($r) => number_format($r['revenue'] / $divisor, $divisor > 1 ? 2 : 0, ',', '.').$suffix, $byRevenue),
                ]],
            ],
            'monthly' => [
                'type' => 'stacked', 'unit' => 'count', 'legend' => 'top',
                'labels' => array_map(fn ($m) => $this->monthLabel($m), $this->months),
                'series' => array_map(fn ($s) => [
                    'label' => $s,
                    'color' => $this->sourceColor($s),
                    'data'  => array_map(fn ($m) => $cells[$s][$m] ?? 0, $this->months),
                ], $labels),
            ],
            'outcome' => [
                'type' => 'stacked_h', 'unit' => 'percent', 'max' => 100, 'legend' => 'top', 'dataLabels' => 'inside',
                'labels' => $labels,
                'series' => array_map(fn ($key) => [
                    'label'       => __('reports.lead_sources.outcome.'.$key),
                    'color'       => self::OUTCOMES[$key][0],
                    'labelColor'  => self::OUTCOMES[$key][1],
                    'data'        => array_map(fn ($r) => $r['leads'] ? round($r[$key] * 100 / $r['leads'], 1) : 0, $rows),
                    'counts'      => array_column($rows, $key),
                    'valueLabels' => array_map(fn ($r) => $r['leads'] ? round($r[$key] * 100 / $r['leads']).'%' : '', $rows),
                ], array_keys(self::OUTCOMES)),
            ],
        ];
    }

    /**
     * Dòng bảng tổng hợp: tỷ trọng, độ dài thanh số Lead, màu so với trung bình, đánh giá nguồn.
     */
    #[Computed]
    public function tableRows(): array
    {
        $t = $this->totals;
        $maxLeads = max(array_column($this->bySource, 'leads') ?: [1]);

        return array_map(fn ($r) => $r + [
            'color'  => $this->sourceColor($r['dim']),
            'share'  => $t['leads'] ? round($r['leads'] * 100 / $t['leads'], 1) : 0,
            'bar'    => round($r['leads'] * 100 / $maxLeads),
            'heat'   => [
                'conversion_rate'  => $this->heat($r['conversion_rate'], $t['conversion_rate']),
                'win_rate'         => $this->heat($r['win_rate'], $t['win_rate']),
                'revenue_per_lead' => $this->heat($r['revenue_per_lead'], $t['revenue_per_lead']),
            ],
            'rating' => $this->rating($r, $t),
        ], $this->bySource);
    }

    /**
     * Đánh giá nguồn theo trung bình của 2 tỷ số: tỷ lệ chốt và doanh thu / Lead so với mức chung.
     *   ≥ 1,5 lần → mở rộng · ≥ 0,95 → duy trì · ≥ 0,6 → tối ưu · thấp hơn → xem xét cắt giảm.
     */
    protected function rating(array $row, array $total): string
    {
        if ($row['leads'] < self::MIN_LEADS) {
            return 'few';
        }

        $ratios = [];
        foreach (['win_rate', 'revenue_per_lead'] as $metric) {
            if ($total[$metric] > 0) {
                $ratios[] = $row[$metric] / $total[$metric];
            }
        }
        if (! $ratios) {
            return 'few';
        }

        $score = array_sum($ratios) / count($ratios);

        return match (true) {
            $score >= 1.5  => 'expand',
            $score >= 0.95 => 'keep',
            $score >= 0.6  => 'optimize',
            default        => 'cut',
        };
    }

    /**
     * Màu nền ô so với trung bình: xanh = tốt hơn, đỏ = kém hơn, càng chênh càng đậm.
     */
    protected function heat(float $value, float $average): string
    {
        if ($average <= 0) {
            return '';
        }

        $diff = ($value - $average) / $average;
        $alpha = round(min(0.45, 0.04 + abs($diff) * 0.3), 3);
        $rgb = $diff >= 0 ? '42, 120, 214' : '227, 73, 72';

        return "background: rgba($rgb, $alpha)";
    }

    /**
     * Đường xu hướng nhỏ (SVG 120×36): điểm của đường và vùng tô bên dưới; cần từ 2 điểm.
     */
    protected function sparkline(array $values): ?array
    {
        if (count($values) < 2) {
            return null;
        }

        [$w, $h, $pad] = [120, 36, 3];
        $min = min($values);
        $range = max($values) - $min;
        $step = $w / (count($values) - 1);

        $points = [];
        foreach (array_values($values) as $i => $v) {
            $y = $range > 0 ? $h - $pad - ($v - $min) / $range * ($h - 2 * $pad) : $h / 2;
            $points[] = round($i * $step, 1).','.round($y, 1);
        }
        $line = implode(' ', $points);

        return ['line' => $line, 'area' => "0,$h $line $w,$h"];
    }

    /* ── Xuất Excel (popup chọn sheet / cột / xem trước - trait ReportExport) ── */

    // Chỉ 1 sheet: báo cáo theo nguồn Lead
    public const EXPORT_SHEETS = ['summary'];

    public const EXPORT_LOCKED = ['summary' => 'source'];

    // Chỉ số của sheet theo nguồn → kiểu cột
    public const EXPORT_METRICS = [
        'leads' => 'int', 'converted' => 'int', 'conversion_rate' => 'percent', 'won' => 'int', 'lost' => 'int',
        'win_rate' => 'percent', 'close_rate' => 'percent', 'revenue' => 'money', 'paid' => 'money',
        'revenue_per_lead' => 'money', 'revenue_share' => 'percent',
    ];

    // Tiêu chí riêng của popup ('' = tất cả)
    public string $exportBranch = '';

    public string $exportMethod = '';

    public string $exportSource = '';

    /**
     * Xuất Excel: Giám đốc, Sale Admin.
     */
    #[Computed]
    public function canExport(): bool
    {
        return in_array(Auth::user()->role_name, [Role::DIRECTOR, Role::SALE_ADMIN], true);
    }

    public function exportColumnCatalog(): array
    {
        return [
            'summary' => ['source' => 'label'] + self::EXPORT_METRICS + ['note' => 'text'],
        ];
    }

    /**
     * Tiêu đề cột chỉ số dùng chung nhóm "metric".
     */
    public function exportLabel(string $key, array $replace = []): string
    {
        [$sheet, $column] = array_pad(explode('.', $key, 2), 2, null);
        if ($sheet === 'summary' && isset(self::EXPORT_METRICS[$column])) {
            $key = 'metric.'.$column;
        }

        return __('reports.lead_sources.export.'.$key, $replace);
    }

    public function exportFilterFields(): array
    {
        return [
            ['model' => 'exportBranch', 'label' => __('reports.branch'), 'all' => __('reports.all_branches'),
                'options' => $this->branches->pluck('branch_name', 'branch_id')->all()],
            ['model' => 'exportMethod', 'label' => __('reports.method'), 'all' => __('reports.lead_sources.export.all_methods'),
                'options' => collect(Lead::CONTACT_METHODS)->mapWithKeys(fn ($m) => [$m => $this->label('method', $m)])->all()],
            ['model' => 'exportSource', 'label' => __('reports.dimension.source'), 'all' => __('reports.all'),
                'options' => array_combine($this->allSources, $this->allSources)],
        ];
    }

    protected function resetExportFilters(): void
    {
        $this->reset('exportBranch', 'exportMethod', 'exportSource');
    }

    protected function exportCriteria(): array
    {
        $branch = $this->branches->firstWhere('branch_id', $this->exportBranch);
        $method = in_array($this->exportMethod, Lead::CONTACT_METHODS, true) ? $this->exportMethod : null;
        $source = in_array($this->exportSource, $this->allSources, true) ? $this->exportSource : null;

        $parts = array_filter([
            $branch ? __('reports.branch').': '.$branch->branch_name : null,
            $method ? __('reports.method').': '.$this->label('method', $method) : null,
            $source ? __('reports.dimension.source').': '.$source : null,
        ]);

        return [
            ['branch' => $branch?->branch_id, 'method' => $method, 'sources' => $source ? [$source] : null],
            $parts ? implode(' · ', $parts) : $this->exportLabel('scope_all'),
        ];
    }

    /**
     * Số liệu Lead tạo trong kỳ theo tiêu chí, nhóm theo nguồn.
     */
    protected function exportData(array $period, array $filters, array $sheets): array
    {
        $f = ['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()] + $filters;

        return [
            'total'   => $this->report()->totals($f),
            'sources' => $this->sorted($this->report()->aggregate($f, 'source')),
        ];
    }

    /**
     * Dòng tổng cộng rồi từng nguồn (kèm ghi chú đánh giá nguồn).
     */
    protected function exportRows(string $sheet, array $data): array
    {
        $total = $data['total'];
        $metrics = fn (array $r) => array_intersect_key($r, self::EXPORT_METRICS) + [
            'revenue_share' => $total['revenue'] > 0 ? round($r['revenue'] * 100 / $total['revenue'], 1) : null,
        ];

        $notes = $this->sourceNotes($data['sources']);
        $rows = [['total', ['source' => $this->exportLabel('total')] + $metrics($total)]];
        foreach ($data['sources'] as $r) {
            $rows[] = ['item', ['source' => $r['dim'], 'note' => $notes[$r['dim']] ?? null] + $metrics($r)];
        }

        return $rows;
    }

    protected function exportSheetMeta(string $sheet, array $data, array $period): array
    {
        return ['unit' => $this->exportLabel('unit')];
    }

    protected function exportFileName(): string
    {
        return 'bao-cao-chat-luong-nguon-lead';
    }

    /**
     * Năm chọn được trong popup xuất: từ năm có Lead sớm nhất đến năm hiện tại.
     */
    public function exportYears(): array
    {
        $first = Lead::query()->min('created_at');
        $current = (int) now()->year;

        return range($current, $first ? min($current, (int) CarbonImmutable::parse($first)->year) : $current);
    }

    /**
     * Ghi chú cho nguồn nổi bật (khi có từ 2 nguồn): chuyển đổi cao / thấp nhất, doanh thu cao nhất.
     */
    protected function sourceNotes(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn ($r) => $r['leads'] > 0));
        if (count($rows) < 2) {
            return [];
        }

        $pick = function (string $metric, bool $max) use ($rows) {
            // Bằng nhau thì nguồn nhiều Lead hơn được chọn (tỷ lệ đáng tin hơn)
            usort($rows, fn ($a, $b) => ($max ? $b[$metric] <=> $a[$metric] : $a[$metric] <=> $b[$metric]) ?: $b['leads'] <=> $a['leads']);

            return $rows[0]['dim'];
        };

        $notes = [];
        $notes[$pick('conversion_rate', true)][] = __('reports.lead_sources.export.note_best_conversion');
        if (max(array_column($rows, 'revenue')) > 0) {
            $notes[$pick('revenue', true)][] = __('reports.lead_sources.export.note_best_revenue');
        }
        $notes[$pick('conversion_rate', false)][] = __('reports.lead_sources.export.note_worst_conversion');

        return array_map(fn ($items) => implode('; ', $items), $notes);
    }

    protected function sorted(array $rows): array
    {
        usort($rows, fn ($a, $b) => $b['leads'] <=> $a['leads'] ?: strcmp($a['dim'], $b['dim']));

        return $rows;
    }

    /* ── Danh mục cho bộ lọc ──────────────────────────────────────────── */

    #[Computed]
    public function branches(): Collection
    {
        return Branch::query()->orderBy('branch_id')->get(['branch_id', 'branch_name']);
    }

    /**
     * Toàn bộ nguồn (không theo bộ lọc) - cho ô chọn nguồn khi xuất Excel.
     */
    #[Computed]
    public function allSources(): array
    {
        return Lead::SOURCES;
    }

    /* ── Hỗ trợ ───────────────────────────────────────────────────────── */

    protected function report(): LeadSourceReport
    {
        return app(LeadSourceReport::class);
    }

    /**
     * Bộ lọc đã kiểm tra (giá trị không hợp lệ bị bỏ qua).
     */
    protected function filterValues(): array
    {
        return [
            'from'   => $this->validDate($this->from) ? $this->from : null,
            'to'     => $this->validDate($this->to) ? $this->to : null,
            'branch' => $this->branches->contains('branch_id', $this->branch) ? $this->branch : null,
            'method' => in_array($this->method, Lead::CONTACT_METHODS, true) ? $this->method : null,
            'sources' => array_values(array_intersect(Lead::SOURCES, $this->sources)) ?: null,
        ];
    }

    public function sourceColor(string $source): string
    {
        return self::SOURCE_COLORS[$source] ?? self::OTHER_COLOR;
    }

    public function fmtNum(float|int $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    public function fmtPct(float|int $value, int $decimals = 1): string
    {
        return number_format((float) $value, $decimals, ',', '.').'%';
    }

    /**
     * Tiền rút gọn → [số, đơn vị]: 3,49 tỷ · 2,72 tr · 950.000 (kèm ₫ khi $currency).
     */
    public function shortMoney(float|int $value, bool $currency = false): array
    {
        $value = (float) $value;
        foreach ([[1e9, 'billion'], [1e6, 'million']] as [$base, $unit]) {
            if (abs($value) >= $base) {
                $label = __('reports.lead_sources.unit.'.$unit);

                return [number_format($value / $base, 2, ',', '.'), $currency ? $label.' ₫' : $label];
            }
        }

        return [number_format($value, 0, ',', '.'), $currency ? '₫' : ''];
    }

    /**
     * Nhãn tháng trên biểu đồ: T5 (cùng năm) hoặc T5/26 (kỳ trải qua nhiều năm).
     */
    protected function monthLabel(string $month): string
    {
        [$year, $m] = explode('-', $month);
        $sameYear = count(array_unique(array_map(fn ($x) => substr($x, 0, 4), $this->months))) === 1;

        return __('reports.lead_sources.month_short', ['month' => (int) $m]).($sameYear ? '' : '/'.substr($year, 2));
    }

    protected function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }

    /**
     * Nhãn hiển thị của 1 giá trị theo chiều phân tích.
     */
    public function label(string $dimension, string $value): string
    {
        return match ($dimension) {
            'method' => __('leads.method.'.$value),
            'status' => __('leads.status_name.'.$value),
            'branch' => $this->branches->firstWhere('branch_id', $value)?->branch_name ?? $value,
            'month'  => preg_match('/^(\d{4})-(\d{2})$/', $value, $m) ? "$m[2]/$m[1]" : $value,
            default  => $value,
        };
    }

    public function render()
    {
        return view('livewire.reports.lead-sources')->title(__('reports.lead_sources.title'));
    }
}
