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
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard & Báo cáo → Phân tích chất lượng nguồn Lead (Giám đốc, Sale Admin).
 *
 * Phần 1 - biểu đồ cố định: 4 thẻ KPI, Số Lead & Lead chốt theo nguồn, doanh thu theo nguồn,
 *          bảng xếp hạng nguồn.
 * Phần 2 - biểu đồ linh hoạt: người dùng chọn loại biểu đồ, chiều phân tích, chia nhóm,
 *          chỉ số và các giá trị cần vẽ, bấm "Vẽ biểu đồ" để vẽ.
 * Bộ lọc chung (thời gian, chi nhánh, kênh liên hệ) áp dụng cho cả 2 phần.
 * Xuất Excel: popup chọn kỳ, tiêu chí, sheet, cột và xem trước (trait ReportExport) - Giám đốc, Sale Admin.
 */
#[Layout('layouts.app')]
class LeadSources extends Component
{
    use ReportExport;

    // Bảng màu phân loại (thứ tự cố định); màu đi theo thực thể, không theo thứ hạng
    const PALETTE = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];

    const OTHER_COLOR = '#9ca3af';

    // Số nhóm tối đa trên 1 biểu đồ; phần còn lại gộp vào "Khác"
    const MAX_SERIES = 8;

    const CHART_TYPES = ['bar', 'bar_h', 'stacked', 'line', 'area', 'pie', 'doughnut'];

    const ROUND_TYPES = ['pie', 'doughnut'];

    const SORTS = ['value_desc', 'value_asc', 'label'];

    const LIMITS = [0, 5, 10, 20];

    /* ── Bộ lọc chung ─────────────────────────────────────────────────── */

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $branch = '';

    #[Url(except: '')]
    public string $method = '';

    const FILTERS = ['from', 'to', 'branch', 'method'];

    /* ── Biểu đồ linh hoạt: lựa chọn đang sửa ─────────────────────────── */

    public string $chartType = 'bar';

    public string $dimension = 'source';

    public string $breakdown = '';

    public array $metrics = ['leads'];

    // Giá trị của chiều phân tích được chọn để vẽ; rỗng = tất cả
    public array $values = [];

    public string $sort = 'value_desc';

    public int $limit = 0;

    // Lựa chọn đã bấm "Vẽ biểu đồ" (biểu đồ chỉ vẽ lại khi bấm nút)
    #[Locked]
    public array $applied = [];

    #[Locked]
    public array $builderErrors = [];

    public function mount(): void
    {
        $this->draw();
    }

    public function resetFilters(): void
    {
        $this->reset(...self::FILTERS);
    }

    public function hasFilters(): bool
    {
        foreach (self::FILTERS as $filter) {
            if ($this->$filter !== '') {
                return true;
            }
        }

        return false;
    }

    /* ── Biểu đồ linh hoạt ────────────────────────────────────────────── */

    public function updatedChartType(): void
    {
        // Biểu đồ tròn: 1 chỉ số cộng dồn được, không chia nhóm
        if (in_array($this->chartType, self::ROUND_TYPES, true)) {
            $this->breakdown = '';
            $additive = array_values(array_filter($this->metrics, fn ($m) => $this->unitOf($m) !== 'percent'));
            $this->metrics = [$additive[0] ?? 'leads'];
        }
    }

    public function updatedDimension(): void
    {
        $this->values = [];
        if ($this->breakdown === $this->dimension) {
            $this->breakdown = '';
        }
    }

    public function updatedBreakdown(): void
    {
        // Có chia nhóm: chỉ giữ 1 chỉ số
        if ($this->breakdown !== '') {
            $this->metrics = array_slice($this->metrics, 0, 1) ?: ['leads'];
        }
    }

    public function selectAllValues(): void
    {
        $this->values = [];
    }

    public function draw(): void
    {
        $errors = $this->validateBuilder();
        $this->builderErrors = $errors;

        if ($errors) {
            return;
        }

        $this->applied = [
            'chartType' => $this->chartType,
            'dimension' => $this->dimension,
            'breakdown' => $this->breakdown,
            'metrics'   => array_values($this->metrics),
            'values'    => array_values($this->values),
            'sort'      => $this->sort,
            'limit'     => $this->limit,
        ];
    }

    /**
     * Kiểm tra tổ hợp lựa chọn; trả về danh sách lỗi (rỗng = hợp lệ).
     */
    protected function validateBuilder(): array
    {
        $errors = [];
        $round = in_array($this->chartType, self::ROUND_TYPES, true);

        if (! in_array($this->chartType, self::CHART_TYPES, true)
            || ! isset(LeadSourceReport::DIMENSIONS[$this->dimension])
            || ($this->breakdown !== '' && ! in_array($this->breakdown, $this->breakdownOptions(), true))
            || ! in_array($this->sort, self::SORTS, true)
            || ! in_array($this->limit, self::LIMITS, true)) {
            return [__('reports.builder.err_invalid')];
        }

        $this->metrics = array_values(array_unique(array_filter(
            $this->metrics, fn ($m) => is_string($m) && isset(LeadSourceReport::METRICS[$m])
        )));

        if (! $this->metrics) {
            return [__('reports.builder.err_no_metric')];
        }

        $units = array_unique(array_map(fn ($m) => $this->unitOf($m), $this->metrics));

        // Không dùng biểu đồ 2 trục: các chỉ số trên cùng 1 biểu đồ phải cùng đơn vị
        if (count($units) > 1) {
            $errors[] = __('reports.builder.err_unit');
        }

        if ($round) {
            if (count($this->metrics) > 1) {
                $errors[] = __('reports.builder.err_round_one');
            }
            if (in_array('percent', $units, true)) {
                $errors[] = __('reports.builder.err_round_percent');
            }
        }

        if ($this->breakdown !== '' && count($this->metrics) > 1) {
            $errors[] = __('reports.builder.err_breakdown_one');
        }

        if ($this->chartType === 'stacked') {
            if (in_array('percent', $units, true) || in_array('revenue_per_lead', $this->metrics, true)) {
                $errors[] = __('reports.builder.err_stack_rate');
            }
            if ($this->breakdown === '' && count($this->metrics) < 2) {
                $errors[] = __('reports.builder.err_stack_need');
            }
        }

        // Chỉ giữ giá trị còn tồn tại trong danh sách
        $available = array_column($this->valueOptions, 'value');
        $this->values = array_values(array_intersect($this->values, $available));

        return $errors;
    }

    /**
     * Chiều chia nhóm hợp lệ với chiều phân tích hiện tại.
     */
    public function breakdownOptions(): array
    {
        return array_values(array_filter(
            array_keys(LeadSourceReport::DIMENSIONS),
            fn ($d) => $d !== $this->dimension && ! in_array($d, LeadSourceReport::TIME_DIMENSIONS, true)
        ));
    }

    /**
     * Các giá trị của chiều phân tích (để chọn giá trị cần vẽ). Chiều thời gian dùng bộ lọc ngày.
     */
    #[Computed]
    public function valueOptions(): array
    {
        if (in_array($this->dimension, LeadSourceReport::TIME_DIMENSIONS, true)) {
            return [];
        }

        $rows = $this->report()->aggregate($this->filterValues(), $this->dimension);
        usort($rows, fn ($a, $b) => $b['leads'] <=> $a['leads'] ?: strcmp($a['dim'], $b['dim']));

        return array_map(fn ($row) => [
            'value' => $row['dim'],
            'label' => $this->label($this->dimension, $row['dim']),
            'leads' => $row['leads'],
        ], $rows);
    }

    /**
     * Cấu hình biểu đồ linh hoạt theo lựa chọn đã áp dụng + bảng số liệu đi kèm.
     */
    #[Computed]
    public function customChart(): ?array
    {
        if (! $this->applied) {
            return null;
        }

        $a = $this->applied;
        $dim = $a['dimension'];
        $time = in_array($dim, LeadSourceReport::TIME_DIMENSIONS, true);
        $rows = $this->report()->aggregate($this->filterValues(), $dim, $a['breakdown'] ?: null);

        if ($a['values'] && ! $time) {
            $rows = array_values(array_filter($rows, fn ($r) => in_array($r['dim'], $a['values'], true)));
        }

        if (! $rows) {
            return ['empty' => true];
        }

        $sortMetric = $a['metrics'][0];

        // Thứ tự các giá trị trên trục: theo thời gian / tên / giá trị chỉ số đầu tiên (cộng theo nhóm)
        $dims = $this->sumBy($rows, 'dim');
        if ($time || $a['sort'] === 'label') {
            ksort($dims, SORT_NATURAL);
        } else {
            uasort($dims, fn ($x, $y) => $a['sort'] === 'value_asc'
                ? $x[$sortMetric] <=> $y[$sortMetric]
                : $y[$sortMetric] <=> $x[$sortMetric]);
        }
        $keys = array_keys($dims);
        if ($a['limit'] && ! $time) {
            $keys = array_slice($keys, 0, $a['limit']);
        }

        $labels = array_map(fn ($k) => $this->label($dim, (string) $k), $keys);
        $unit = $this->unitOf($sortMetric);
        $round = in_array($a['chartType'], self::ROUND_TYPES, true);

        if ($a['breakdown']) {
            // Mỗi nhóm 1 dãy; quá MAX_SERIES nhóm thì gộp phần nhỏ vào "Khác"
            $metric = $sortMetric;
            $groups = $this->sumBy($rows, 'group');
            uasort($groups, fn ($x, $y) => $y['leads'] <=> $x['leads']);
            $groupKeys = array_keys($groups);
            $kept = count($groupKeys) > self::MAX_SERIES ? array_slice($groupKeys, 0, self::MAX_SERIES - 1) : $groupKeys;
            $cells = [];
            foreach ($rows as $row) {
                $g = in_array($row['group'], $kept, true) ? $row['group'] : '__other';
                $cells[$g][$row['dim']][] = $row;
            }

            $colors = $this->colorMap($a['breakdown'], $kept);
            $series = [];
            foreach (array_merge($kept, isset($cells['__other']) ? ['__other'] : []) as $g) {
                $series[] = [
                    'label' => $g === '__other' ? __('reports.other') : $this->label($a['breakdown'], (string) $g),
                    'color' => $g === '__other' ? self::OTHER_COLOR : $colors[$g],
                    'data'  => array_map(fn ($k) => isset($cells[$g][$k])
                        ? $this->combine($cells[$g][$k])[$metric] : 0, $keys),
                ];
            }
            $title = __('reports.metric.'.$metric).' · '.__('reports.dimension.'.$dim).' × '.__('reports.dimension.'.$a['breakdown']);
        } elseif ($round) {
            // Tròn: mỗi giá trị 1 lát, màu theo thực thể; quá MAX_SERIES lát thì gộp "Khác"
            $metric = $sortMetric;
            $kept = count($keys) > self::MAX_SERIES ? array_slice($keys, 0, self::MAX_SERIES - 1) : $keys;
            $rest = array_diff($keys, $kept);
            $colors = $this->colorMap($dim, array_map('strval', $kept));
            $data = array_map(fn ($k) => $dims[$k][$metric], $kept);
            $sliceColors = array_map(fn ($k) => $colors[(string) $k], $kept);
            $labels = array_map(fn ($k) => $this->label($dim, (string) $k), $kept);
            if ($rest) {
                $labels[] = __('reports.other');
                $data[] = array_sum(array_map(fn ($k) => $dims[$k][$metric], $rest));
                $sliceColors[] = self::OTHER_COLOR;
            }
            $series = [['label' => __('reports.metric.'.$metric), 'data' => $data, 'colors' => $sliceColors]];
            $title = __('reports.metric.'.$metric).' · '.__('reports.dimension.'.$dim);
        } else {
            // Mỗi chỉ số 1 dãy, màu cố định theo chỉ số
            $metricKeys = array_keys(LeadSourceReport::METRICS);
            $series = array_map(fn ($m) => [
                'label' => __('reports.metric.'.$m),
                'color' => self::PALETTE[array_search($m, $metricKeys, true) % count(self::PALETTE)],
                'data'  => array_map(fn ($k) => $dims[$k][$m], $keys),
            ], $a['metrics']);
            $title = implode(', ', array_map(fn ($m) => __('reports.metric.'.$m), $a['metrics'])).' · '.__('reports.dimension.'.$dim);
        }

        return [
            'empty' => false,
            'title' => $title,
            'spec'  => [
                'type'   => $a['chartType'],
                'unit'   => $unit,
                'labels' => array_values($labels),
                'series' => $series,
            ],
        ];
    }

    /* ── Biểu đồ cố định ──────────────────────────────────────────────── */

    #[Computed]
    public function totals(): array
    {
        return $this->report()->totals($this->filterValues());
    }

    /**
     * Chỉ số theo từng nguồn, sắp theo số Lead giảm dần.
     */
    #[Computed]
    public function bySource(): array
    {
        $rows = $this->report()->aggregate($this->filterValues(), 'source');
        usort($rows, fn ($a, $b) => $b['leads'] <=> $a['leads'] ?: $b['revenue'] <=> $a['revenue']);

        return $rows;
    }

    /**
     * 2 biểu đồ cố định – trả lời 2 câu hỏi chính về chất lượng nguồn:
     *   quality: nguồn nào nhiều Lead nhưng ít chốt (Số Lead cạnh Lead chốt thành công)
     *   revenue: nguồn nào mang về nhiều doanh thu nhất
     */
    #[Computed]
    public function fixedCharts(): array
    {
        $rows = $this->bySource;
        $metricKeys = array_keys(LeadSourceReport::METRICS);
        $metricColor = fn ($m) => self::PALETTE[array_search($m, $metricKeys, true) % count(self::PALETTE)];

        $quality = [
            'type'   => 'bar_h',
            'unit'   => 'count',
            'labels' => array_column($rows, 'dim'),
            'series' => array_map(fn ($m) => [
                'label' => __('reports.metric.'.$m), 'color' => $metricColor($m),
                'data'  => array_column($rows, $m),
            ], ['leads', 'won']),
        ];

        // Bỏ nguồn chưa có doanh thu, sắp giảm dần
        $withRevenue = array_values(array_filter($rows, fn ($r) => $r['revenue'] > 0));
        usort($withRevenue, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        $revenue = [
            'type'   => 'bar_h',
            'unit'   => 'money',
            'labels' => array_column($withRevenue, 'dim'),
            'series' => [[
                'label' => __('reports.metric.revenue'), 'color' => self::PALETTE[0],
                'data'  => array_column($withRevenue, 'revenue'),
            ]],
        ];

        return ['quality' => $quality, 'revenue' => $revenue];
    }

    /* ── Xuất Excel (popup chọn sheet / cột / xem trước - trait ReportExport) ── */

    // Sheet: theo nguồn · theo kênh & chi nhánh · theo tháng · Lead mới nguồn × tháng · danh sách Lead
    const EXPORT_SHEETS = ['summary', 'breakdown', 'months', 'matrix', 'leads'];

    const EXPORT_LOCKED = ['summary' => 'source', 'breakdown' => 'criteria', 'months' => 'month', 'matrix' => 'source', 'leads' => 'name'];

    // Mặc định: sheet tổng hợp với các cột trả lời "nguồn nào đáng đầu tư"; danh sách Lead chọn thêm khi cần đối chiếu
    const EXPORT_DEFAULT_SHEETS = ['summary', 'breakdown', 'months', 'matrix'];

    const EXPORT_DEFAULT_COLUMNS = [
        'summary'   => ['source', 'leads', 'converted', 'conversion_rate', 'won', 'win_rate', 'close_rate', 'revenue', 'revenue_per_lead', 'revenue_share', 'note'],
        'breakdown' => ['criteria', 'leads', 'conversion_rate', 'won', 'win_rate', 'revenue', 'revenue_per_lead'],
        'months'    => ['month', 'leads', 'converted', 'conversion_rate', 'won', 'win_rate', 'revenue'],
        'matrix'    => ['source', 'months', 'sum'],
        'leads'     => ['name', 'created', 'source', 'method', 'branch', 'owner', 'outcome', 'revenue'],
    ];

    // Chỉ số dùng chung cho các sheet tổng hợp → kiểu cột
    const EXPORT_METRICS = [
        'leads' => 'int', 'converted' => 'int', 'conversion_rate' => 'percent', 'won' => 'int', 'lost' => 'int',
        'win_rate' => 'percent', 'close_rate' => 'percent', 'revenue' => 'money', 'paid' => 'money',
        'expected' => 'money', 'revenue_per_lead' => 'money', 'revenue_share' => 'percent',
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
            'summary'   => ['source' => 'label'] + self::EXPORT_METRICS + ['note' => 'text'],
            'breakdown' => ['criteria' => 'label'] + self::EXPORT_METRICS,
            'months'    => ['month' => 'label'] + self::EXPORT_METRICS,
            'matrix'    => ['source' => 'label', 'months' => 'int', 'sum' => 'int'],
            'leads'     => [
                'id' => 'text', 'name' => 'label', 'created' => 'text', 'source' => 'text', 'method' => 'text',
                'branch' => 'text', 'status' => 'text', 'owner' => 'text', 'outcome' => 'text', 'revenue' => 'money',
            ],
        ];
    }

    /**
     * Tiêu đề cột của các sheet tổng hợp dùng chung nhóm "metric".
     */
    public function exportLabel(string $key, array $replace = []): string
    {
        [$sheet, $column] = array_pad(explode('.', $key, 2), 2, null);
        if (in_array($sheet, ['summary', 'breakdown', 'months'], true) && isset(self::EXPORT_METRICS[$column])) {
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
     * Số liệu Lead tạo trong kỳ theo tiêu chí; chỉ tính phần có sheet cần.
     */
    protected function exportData(array $period, array $filters, array $sheets): array
    {
        $f = ['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()] + $filters;
        $need = fn (string ...$names) => (bool) array_intersect($names, $sheets);

        // Tháng: đủ các tháng của kỳ (tháng chưa có Lead ghi 0)
        $byMonth = $need('months', 'matrix') ? array_column($this->report()->aggregate($f, 'month'), null, 'dim') : [];
        $cells = [];
        if ($need('matrix')) {
            foreach ($this->report()->aggregate($f, 'month', 'source') as $r) {
                $cells[$r['group']][$r['dim']] = $r['leads'];
            }
        }

        return [
            'total'   => $this->report()->totals($f),
            'sources' => $need('summary', 'matrix') ? $this->sorted($this->report()->aggregate($f, 'source')) : [],
            'methods' => $need('breakdown') ? $this->sorted($this->report()->aggregate($f, 'method')) : [],
            'branchs' => $need('breakdown') ? $this->sorted($this->report()->aggregate($f, 'branch')) : [],
            'months'  => array_map(fn ($m) => $byMonth[$m] ?? $this->report()->derive(['dim' => $m]), $period['months']),
            'cells'   => $cells,
            'leads'   => $need('leads') ? $this->report()->leads($f) : [],
        ];
    }

    protected function exportRows(string $sheet, array $data): array
    {
        $total = $data['total'];
        $metrics = fn (array $r) => array_intersect_key($r, self::EXPORT_METRICS) + [
            'revenue_share' => $total['revenue'] > 0 ? round($r['revenue'] * 100 / $total['revenue'], 1) : null,
        ];

        switch ($sheet) {
            case 'summary':
                $notes = $this->sourceNotes($data['sources']);
                $rows = [['total', ['source' => $this->exportLabel('total')] + $metrics($total)]];
                foreach ($data['sources'] as $r) {
                    $rows[] = ['item', ['source' => $r['dim'], 'note' => $notes[$r['dim']] ?? null] + $metrics($r)];
                }

                return $rows;

            case 'breakdown':
                // Mỗi phần (A kênh, B chi nhánh) cộng lại đúng bằng tổng cộng
                $rows = [['total', ['criteria' => $this->exportLabel('total')] + $metrics($total)]];
                foreach (['method' => $data['methods'], 'branch' => $data['branchs']] as $i => $items) {
                    $rows[] = ['section', ['_stt' => $i === 'method' ? 'A' : 'B', 'criteria' => mb_strtoupper($this->exportLabel('by_'.$i))]];
                    foreach ($items as $r) {
                        $rows[] = ['item', ['criteria' => $this->label($i, $r['dim'])] + $metrics($r)];
                    }
                }

                return $rows;

            case 'months':
                $rows = [['total', ['month' => $this->exportLabel('total')] + $metrics($total)]];
                foreach ($data['months'] as $r) {
                    $rows[] = ['item', ['month' => $this->exportLabel('month', ['month' => $this->label('month', $r['dim'])])] + $metrics($r)];
                }

                return $rows;

            case 'matrix':
                $months = array_column($data['months'], 'dim');
                $rows = [['total', ['source' => $this->exportLabel('total'), 'sum' => $total['leads']]
                    + array_combine(array_map(fn ($m) => 'm:'.$m, $months), array_column($data['months'], 'leads'))]];
                foreach ($data['sources'] as $r) {
                    $rows[] = ['item', ['source' => $r['dim'], 'sum' => $r['leads']]
                        + array_combine(array_map(fn ($m) => 'm:'.$m, $months), array_map(fn ($m) => $data['cells'][$r['dim']][$m] ?? 0, $months))];
                }

                return $rows;

            default:
                return array_map(fn ($l) => ['item', [
                    'created' => CarbonImmutable::parse($l['created'])->format('d/m/Y H:i'),
                    'method'  => $this->label('method', $l['method']),
                    'branch'  => $this->label('branch', $l['branch']),
                    'status'  => $this->label('status', $l['status']),
                    'outcome' => __('reports.lead_conversion.outcome.'.$l['outcome']),
                ] + $l], $data['leads']);
        }
    }

    /**
     * Cột "Các tháng" của sheet nguồn × tháng: mỗi tháng của kỳ 1 cột.
     */
    protected function expandExportColumn(string $sheet, string $column, array $data): ?array
    {
        if ($sheet !== 'matrix' || $column !== 'months') {
            return null;
        }

        return array_map(fn ($r) => [$this->label('month', $r['dim']), 'int', 'm:'.$r['dim']], $data['months']);
    }

    protected function exportSheetMeta(string $sheet, array $data, array $period): array
    {
        return ['unit' => $this->exportLabel(in_array($sheet, ['matrix'], true) ? 'unit_trend' : 'unit')];
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
     * Toàn bộ nguồn (không theo bộ lọc), sắp theo tên - làm khoá màu cố định cho từng nguồn.
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
     * Bộ lọc chung đã kiểm tra (giá trị không hợp lệ bị bỏ qua).
     */
    protected function filterValues(): array
    {
        return [
            'from'   => $this->validDate($this->from) ? $this->from : null,
            'to'     => $this->validDate($this->to) ? $this->to : null,
            'branch' => $this->branches->contains('branch_id', $this->branch) ? $this->branch : null,
            'method' => in_array($this->method, Lead::CONTACT_METHODS, true) ? $this->method : null,
        ];
    }

    protected function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }

    public function unitOf(string $metric): string
    {
        return LeadSourceReport::METRICS[$metric] ?? 'count';
    }

    /**
     * Gộp các dòng theo 1 khoá (dim / group) rồi tính lại chỉ số.
     */
    protected function sumBy(array $rows, string $key): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row[$key]][] = $row;
        }

        return array_map(fn ($items) => $this->combine($items), $grouped);
    }

    /**
     * Cộng các chỉ số gốc của nhiều dòng rồi tính lại chỉ số dẫn xuất (tỷ lệ không cộng thẳng được).
     */
    protected function combine(array $items): array
    {
        $sum = [];
        foreach (['leads', 'converted', 'won', 'lost', 'revenue', 'paid', 'expected'] as $k) {
            $sum[$k] = array_sum(array_column($items, $k));
        }

        return $this->report()->derive($sum);
    }

    /**
     * Màu cố định cho từng giá trị: nguồn theo danh sách toàn bộ nguồn, chiều khác theo danh mục cố định.
     */
    protected function colorMap(string $dimension, array $values): array
    {
        $catalog = match ($dimension) {
            'source' => $this->allSources,
            'method' => Lead::CONTACT_METHODS,
            'status' => Lead::STATUSES,
            'branch' => $this->branches->pluck('branch_id')->all(),
            default  => $values,
        };

        $map = [];
        foreach ($values as $value) {
            $i = array_search($value, $catalog, true);
            $map[$value] = $i !== false && $i < count(self::PALETTE) ? self::PALETTE[$i] : self::OTHER_COLOR;
        }

        return $map;
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
            'week'   => preg_match('/^(\d{4})-(\d{2})$/', $value, $m)
                ? __('reports.week_label', ['week' => (int) $m[2], 'year' => $m[1]]) : $value,
            default  => $value,
        };
    }

    public function render()
    {
        return view('livewire.reports.lead-sources')->title(__('reports.lead_sources.title'));
    }
}
