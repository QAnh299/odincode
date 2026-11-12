<?php

namespace App\Livewire\Reports;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\SalesTeam;
use App\Services\SalesPerformanceReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

/**
 * Dashboard & Báo cáo → Hiệu quả nhân viên Sale (giao diện Sale Admin), 2 tab:
 *   - Đội kinh doanh: thẻ KPI (Lead đã giao, Lead chưa liên hệ > 24h, phản hồi đầu tiên, tỷ lệ liên hệ được,
 *     cơ hội tồn đọng), tình trạng xử lý Lead đã giao theo đội, thời gian phản hồi đầu tiên theo tuần / tháng
 *     so với SLA, doanh số 6 tháng so với chỉ tiêu, tỷ lệ thắng theo đội, bảng xếp hạng đội.
 *   - Nhân viên kinh doanh: bảng xếp hạng salesperson + việc cần xử lý (dùng lại của báo cáo cũ).
 *
 * Số liệu, phạm vi theo vai trò, xếp hạng và xuất Excel kế thừa từ SalesPerformance; lớp này thêm
 * bộ lọc chi nhánh (Employees.branch_id), cách chia thời gian của biểu đồ phản hồi và các biểu đồ mới.
 */
#[Layout('layouts.app')]
class SalesTeamPerformance extends SalesPerformance
{
    public const TABS = ['teams', 'employees'];

    // Biểu đồ tròn cơ cấu theo đội: chỉ số chọn được ở dropdown
    public const SHARES = ['revenue', 'students'];

    // Tab Nhân viên - biểu đồ tròn: nhiều người hơn số này thì giữ (số này - 1) người cao nhất, còn lại gộp "Nhân viên khác"
    public const EMP_SHARE_MAX = 10;

    public const EMP_OTHER_COLOR = '#c3cad5';

    // Tab Nhân viên - biểu đồ top: số người, các chỉ số chọn được (→ đơn vị)
    public const EMP_TOP = 10;

    public const EMP_METRICS = ['revenue' => 'money', 'won' => 'count', 'close_rate' => 'percent', 'students' => 'count'];

    // Xếp hạng theo tỷ lệ thắng chỉ tính người có ít nhất số cơ hội đóng (Won + Lost) này (ít quá thì tỷ lệ không đáng tin)
    public const EMP_MIN_CLOSED = 5;

    // Màu cố định của từng đội (theo thứ tự đội trong phạm vi) - dùng chung cho mọi biểu đồ và bảng xếp hạng;
    // đủ 12 màu khác nhau để biểu đồ tròn phân biệt được từng đội (nhiều đội hơn thì lặp lại)
    public const PALETTE = [
        '#2a78d6', '#eb6834', '#16a34a', '#9333ea', '#0891b2', '#db2777',
        '#d99a0b', '#b91c1c', '#65a30d', '#92400e', '#475569', '#6366f1',
    ];

    // Tình trạng xử lý Lead đã giao (4 nhóm, cộng lại = Lead đã giao) → [khoá chỉ số, màu cột, màu chữ trong cột]
    public const STATUS_SERIES = [
        'won'       => ['status_won', '#1f9d4f', '#ffffff'],
        'progress'  => ['status_progress', '#7fb0ea', '#14213d'],
        'unreached' => ['status_unreached', '#eaa3a3', '#14213d'],
        'new'       => ['status_new', '#cfd4dc', '#14213d'],
    ];

    // Biểu đồ tình trạng xử lý: quá số đội này thì cột mảnh lại cho gọn
    public const STATUS_COMPACT_TEAMS = 5;

    // Biểu đồ theo tháng: kỳ ngắn hơn 2 tháng thì xem 6 tháng gần nhất, kỳ dài thì tối đa số tháng này
    public const MAX_TREND_MONTHS = 12;

    #[Url(except: 'teams')]
    public string $tab = 'teams';

    // Kỳ báo cáo (Y-m-d); mặc định tháng này
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        if ($this->from === '' && $this->to === '') {
            $this->preset($this->currentMonth());
        }
    }

    // Chi nhánh ('' = tất cả): lọc theo chi nhánh của nhân viên
    #[Url(except: '')]
    public string $branch = '';

    // Chỉ số của biểu đồ cơ cấu theo đội (xem SHARES)
    #[Url(except: 'revenue')]
    public string $share = 'revenue';

    // Tab Nhân viên: chỉ số của biểu đồ tròn cơ cấu (SHARES) và biểu đồ top nhân viên (EMP_METRICS)
    public string $empShare = 'revenue';

    public string $empMetric = 'revenue';

    public function updatedTab(): void
    {
        // Tab đội không có bộ lọc salesperson
        if ($this->activeTab() === 'teams') {
            $this->reset('employee');
        }
    }

    public function updatedBranch(): void
    {
        $this->reset('team', 'employee', 'employeeTeam');
    }

    public function resetFilters(): void
    {
        parent::resetFilters();
        $this->reset('branch', 'share');
        $this->preset($this->currentMonth());
    }

    /**
     * Có lọc khác mặc định (kỳ khác tháng hiện tại, chọn đội / chi nhánh / salesperson...).
     */
    public function hasFilters(): bool
    {
        return parent::hasFilters() || [$this->from, $this->to] !== $this->presetRange($this->currentMonth()) || $this->branch !== '';
    }

    /* ── Kỳ báo cáo (Từ ngày / Đến ngày / Kỳ nhanh) ───────────────────── */

    /**
     * Kỳ nhanh theo tháng: chọn 1 tháng (Y-m, trong danh sách months) → Từ ngày / Đến ngày = đầu / cuối tháng đó.
     */
    public function preset(string $month): void
    {
        if ($range = $this->presetRange($month)) {
            [$this->from, $this->to] = $range;
        }
    }

    public function presetRange(string $month): ?array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return null;
        }
        $start = CarbonImmutable::parse($month.'-01');

        return [$start->toDateString(), $start->endOfMonth()->toDateString()];
    }

    protected function currentMonth(): string
    {
        return CarbonImmutable::today()->format('Y-m');
    }

    /**
     * Tháng trùng đúng với kỳ đang xem (để chọn sẵn trong ô Kỳ nhanh), null nếu là khoảng tự chọn.
     */
    public function activePreset(): ?string
    {
        $month = $this->period['from']->format('Y-m');

        return $this->presetRange($month) === [$this->period['from']->toDateString(), $this->period['until']->toDateString()]
            ? $month : null;
    }

    /**
     * Kỳ đang xem và kỳ liền trước để so sánh (cùng cách tính với báo cáo Chất lượng nguồn Lead):
     *   - from / until: khoảng đã chọn (trống = tháng này); to: until nhưng không quá hôm nay.
     *   - Khoảng trọn tháng: kỳ trước lùi đúng số tháng; khoảng tự chọn: lùi đúng số ngày.
     *   - Kỳ chưa kết thúc chỉ so cùng độ dài (vd. 01-09/11 so với 01-09/10).
     *   - months: các tháng nằm trong kỳ (Y-m) - để cộng chỉ tiêu tháng của đội.
     */
    #[Computed]
    public function period(): array
    {
        $today = CarbonImmutable::today();
        $valid = fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        $from = $valid($this->from) ? CarbonImmutable::parse($this->from) : null;
        $until = $valid($this->to) ? CarbonImmutable::parse($this->to) : null;
        $from ??= ($until ?? $today)->startOfMonth();
        $until ??= $from->greaterThan($today) ? $from->endOfMonth() : $today->endOfMonth();
        if ($until->lessThan($from)) {
            $until = $from;
        }
        $to = $until->greaterThan($today) ? $today->max($from) : $until;

        if ($from->day === 1 && $until->isSameDay($until->endOfMonth())) {
            $count = ($until->year - $from->year) * 12 + $until->month - $from->month + 1;
            $previous = [$from->subMonthsNoOverflow($count), $to->subMonthsNoOverflow($count)];
            $compare = match (true) {
                $count === 1                            => __('reports.lead_sources.compare.month'),
                $count === 3 && $from->month % 3 === 1  => __('reports.lead_sources.compare.quarter'),
                $count === 12 && $from->month === 1     => __('reports.lead_sources.compare.year'),
                default                                 => __('reports.lead_sources.compare.months', ['count' => $count]),
            };
        } else {
            $days = (int) $from->diffInDays($until) + 1;
            $previous = [$from->subDays($days), $to->subDays($days)];
            $compare = __('reports.lead_sources.compare.days', ['count' => $days]);
        }

        $months = [];
        for ($m = $from->startOfMonth(); $m->lessThanOrEqualTo($until); $m = $m->addMonth()) {
            $months[] = $m->format('Y-m');
        }

        return ['from' => $from, 'until' => $until, 'to' => $to, 'previous' => $previous, 'compare' => $compare, 'months' => $months];
    }

    /**
     * Tháng "đang xem" (dùng cho các biểu đồ theo tháng): tháng cuối của kỳ.
     */
    #[Computed]
    public function activeMonth(): string
    {
        return $this->period['to']->format('Y-m');
    }

    /**
     * Bộ lọc của kỳ đang xem (thay cho bộ lọc 1 tháng của báo cáo cũ - tham số tháng bỏ qua).
     */
    protected function filterValues(string $month): array
    {
        return $this->filterRange($this->period['from']->toDateString(), $this->period['to']->toDateString());
    }

    /**
     * Tổng của kỳ liền trước (cùng đội / chi nhánh).
     */
    #[Computed]
    public function previousTotals(): array
    {
        [$from, $to] = $this->period['previous'];

        return $this->report()->totals($this->filterRange($from->toDateString(), $to->toDateString()));
    }

    /**
     * Chỉ tiêu của kỳ: cộng chỉ tiêu các tháng nằm trong kỳ của từng đội.
     */
    #[Computed]
    public function targets(): array
    {
        return $this->targetsForMonths($this->period['months'], $this->filterValues($this->activeMonth));
    }

    protected function rankTargets(array $filters): array
    {
        return $this->targetsForMonths($this->period['months'], $filters);
    }

    /**
     * Các tháng của biểu đồ theo tháng: kỳ từ 2 tháng thì đúng các tháng của kỳ (tối đa MAX_TREND_MONTHS),
     * ngắn hơn thì 6 tháng gần nhất tính đến tháng cuối của kỳ.
     */
    protected function trailingMonths(string $month): array
    {
        $months = $this->period['months'];

        return count($months) >= 2 ? array_slice($months, -self::MAX_TREND_MONTHS) : parent::trailingMonths($month);
    }

    public function activeTab(): string
    {
        return in_array($this->tab, self::TABS, true) ? $this->tab : 'teams';
    }

    public function activeShare(): string
    {
        return in_array($this->share, self::SHARES, true) ? $this->share : 'revenue';
    }

    public function activeEmpShare(): string
    {
        return in_array($this->empShare, self::SHARES, true) ? $this->empShare : 'revenue';
    }

    public function activeEmpMetric(): string
    {
        return isset(self::EMP_METRICS[$this->empMetric]) ? $this->empMetric : 'revenue';
    }

    /* ── Chi nhánh ────────────────────────────────────────────────────── */

    #[Computed]
    public function branches(): Collection
    {
        return Branch::query()->orderBy('branch_name')->get(['branch_id', 'branch_name']);
    }

    protected function branchValue(): ?string
    {
        return $this->branches->contains('branch_id', $this->branch) ? $this->branch : null;
    }

    /**
     * Đội chọn được: chọn chi nhánh thì chỉ còn các đội có nhân viên thuộc chi nhánh đó.
     */
    #[Computed]
    public function teams(): Collection
    {
        $teams = SalesTeam::query()->orderBy('team_name')->get(['team_id', 'team_name']);
        if (! $branch = $this->branchValue()) {
            return $teams;
        }

        $ids = Employee::query()->where('branch_id', $branch)->whereNotNull('team_id')->distinct()->pluck('team_id')->all();

        return $teams->whereIn('team_id', $ids)->values();
    }

    #[Computed]
    public function employeeOptions(): Collection
    {
        return $this->teamScope === null ? collect() : $this->salespeople($this->teamScope ?: null);
    }

    protected function salespeople(?string $team): Collection
    {
        return Employee::query()
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALESPERSON))
            ->when($team !== null, fn ($q) => $q->where('team_id', $team))
            ->when($this->branchValue(), fn ($q, $branch) => $q->where('branch_id', $branch))
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'team_id', 'status']);
    }

    protected function filterRange(string $from, string $to): array
    {
        return parent::filterRange($from, $to) + ['branch' => $this->branchValue()];
    }

    /* ── Số liệu tab Đội kinh doanh ───────────────────────────────────── */

    /**
     * Lead đang xử lý đã giao quá 24 giờ mà chưa liên hệ lần nào (hiện tại, không theo tháng).
     */
    #[Computed]
    public function uncontacted(): int
    {
        return $this->report()->uncontacted($this->filterValues($this->activeMonth));
    }

    /**
     * Chỉ số theo đội × tháng của 6 tháng gần nhất (doanh số, tỷ lệ thắng, phản hồi theo tháng).
     */
    #[Computed]
    public function teamMonthly(): array
    {
        return $this->report()->aggregate(
            $this->monthsFilter(array_column($this->trend, 'month'), $this->filterValues($this->activeMonth)),
            'team_month'
        );
    }

    /**
     * Đội vẽ thành từng dãy riêng trên biểu đồ: khi phạm vi có 2..MAX_TREND_TEAMS đội và không lọc 1 salesperson.
     * Nhiều đội hơn thì rối → biểu đồ gộp chung 1 dãy.
     */
    #[Computed]
    public function seriesTeams(): Collection
    {
        $teams = $this->employee === '' ? $this->scopeTeams : collect();

        return $teams->count() > 1 && $teams->count() <= self::MAX_TREND_TEAMS ? $teams->values() : collect();
    }

    /**
     * Màu cố định của 1 đội (theo thứ tự trong phạm vi đang xem) - dùng chung cho biểu đồ và bảng xếp hạng.
     */
    public function teamColor(string $teamId): string
    {
        $index = $this->scopeTeams->search(fn ($t) => $t->team_id === $teamId);

        return self::PALETTE[($index === false ? 0 : $index) % count(self::PALETTE)];
    }

    /**
     * Tiền rút gọn cho bảng: 1,42 tỷ · 175,5 tr · 500.000 ₫.
     */
    public function shortMoney(?float $value): string
    {
        $value = (float) $value;

        return match (true) {
            abs($value) >= 1e9 => number_format($value / 1e9, 2, ',', '.').' '.__('reports.sales_team.billion'),
            abs($value) >= 1e6 => number_format($value / 1e6, 1, ',', '.').' '.__('reports.sales_team.million'),
            default            => number_format($value, 0, ',', '.').' ₫',
        };
    }

    /**
     * Tên đội rút gọn cho chú thích hẹp: bỏ từ đầu chung của mọi tên (ODIN Sales 1 → Sales 1).
     */
    protected function shortTeamNames(array $names): array
    {
        $first = array_map(fn ($n) => explode(' ', $n, 2), $names);
        $common = count($names) > 1 && count(array_unique(array_map(fn ($p) => $p[0], $first))) === 1
            && ! in_array(null, array_map(fn ($p) => $p[1] ?? null, $first), true);

        return $common ? array_map(fn ($p) => $p[1], $first) : $names;
    }

    public function shortMonth(string $month): string
    {
        return 'T'.(int) substr($month, 5, 2);
    }

    /**
     * Thẻ KPI: [key, icon, value, unit, delta [hướng, chữ, hậu tố], spark, color, hint, alert].
     */
    #[Computed]
    public function kpis(): array
    {
        $t = $this->totals;
        $p = $this->previousTotals;
        $trend = $this->trend;
        $tk = fn ($key, $replace = []) => __('reports.sales_team.'.$key, $replace);
        $dec = fn ($v) => number_format((float) $v, 1, ',', '.');
        $num = fn ($v) => number_format((float) $v, 0, ',', '.');

        // Lead đã giao: tách theo đội (≤ 3 đội thì liệt kê, nhiều hơn thì nêu đội cao nhất)
        $teams = array_values(array_filter($this->byTeam, fn ($r) => $r['assigned'] > 0));
        usort($teams, fn ($a, $b) => $b['assigned'] <=> $a['assigned']);
        $short = $this->shortTeamNames(array_column($teams, 'name'));
        $assignedHint = match (true) {
            ! $teams          => $tk('kpi.assigned_none'),
            count($teams) <= 3 => implode(' · ', array_map(fn ($r, $name) => $name.': '.$num($r['assigned']), $teams, $short)),
            default           => $tk('kpi.assigned_top', ['team' => $short[0], 'count' => $num($teams[0]['assigned'])]),
        };

        $sla = SalesPerformanceReport::RESPONSE_SLA_HOURS;

        $cards = [
            [
                'key' => 'assigned', 'icon' => 'inbox', 'value' => $num($t['assigned']), 'unit' => null,
                'delta' => $this->kpiDelta($t['assigned'], $p['assigned'], 'pct', 1, $this->period['compare']),
                'spark' => $this->sparkline(array_column($trend, 'assigned')), 'color' => 'count',
                'hint' => $assignedHint, 'alert' => false,
            ],
            [
                'key' => 'uncontacted', 'icon' => 'alert', 'value' => $num($this->uncontacted), 'unit' => null,
                'delta' => null, 'spark' => null, 'color' => 'down',
                'hint' => $tk('kpi.uncontacted_hint'), 'alert' => $this->uncontacted > 0,
            ],
            [
                'key' => 'response', 'icon' => 'clock',
                'value' => $t['avg_response_hours'] === null ? '-' : $dec($t['avg_response_hours']),
                'unit' => $t['avg_response_hours'] === null ? null : $tk('hours_unit'),
                'delta' => $this->kpiDelta($t['avg_response_hours'], $p['avg_response_hours'], 'hours', -1, null),
                'spark' => $this->sparkline(array_column($trend, 'avg_response_hours')), 'color' => 'rate',
                'hint' => $tk('kpi.response_hint', ['hours' => $sla]),
                'alert' => false, 'over_sla' => $t['avg_response_hours'] !== null && $t['avg_response_hours'] > $sla,
            ],
            [
                'key' => 'contact_rate', 'icon' => 'phone',
                'value' => $t['contact_rate'] === null ? '-' : $dec($t['contact_rate']).'%', 'unit' => null,
                'delta' => $this->kpiDelta($t['contact_rate'], $p['contact_rate'], 'points', 1, null),
                'spark' => $this->sparkline(array_column($trend, 'contact_rate')), 'color' => 'rate',
                'hint' => $tk('kpi.contact_rate_hint', ['ok' => $num($t['contacted_ok']), 'total' => $num($t['contacted'])]),
                'alert' => false,
            ],
        ];

        // Màu đường xu hướng giống báo cáo Chất lượng nguồn Lead: số lượng xanh dương, tỷ lệ xanh lá, xấu đi thì đỏ
        return array_map(fn ($card) => ['color' => LeadSources::TREND_COLORS[($card['delta'][0] ?? null) === 'down' ? 'down' : $card['color']]] + $card, $cards);
    }

    /**
     * Mức thay đổi so với tháng trước: [hướng (up | down | flat), chữ, hậu tố].
     * $mode: pct = % thay đổi, points = điểm %, hours = số giờ; $dir: 1 cao là tốt, -1 thấp là tốt.
     */
    protected function kpiDelta(?float $cur, ?float $prev, string $mode, int $dir, ?string $suffix): ?array
    {
        if ($cur === null || $prev === null || ($mode === 'pct' && $prev == 0)) {
            return null;
        }

        $change = $mode === 'pct' ? ($cur - $prev) * 100 / $prev : $cur - $prev;
        $value = number_format(abs($change), 1, ',', '.');
        $text = match ($mode) {
            'pct'    => $value.'%',
            'points' => __('reports.sales_team.points', ['value' => $value]),
            default  => $value.'h',
        };
        if (abs($change) < 0.05) {
            return ['flat', __('reports.sales_team.same'), $suffix];
        }
        $good = $change * $dir > 0;
        if ($mode === 'hours') {
            $text .= ' ('.__('reports.sales_team.'.($good ? 'better' : 'worse')).')';
        }

        return [$good ? 'up' : 'down', ($change > 0 ? '▲ ' : '▼ ').$text, $suffix];
    }

    /**
     * Đường xu hướng nhỏ (SVG 120×36), bỏ qua tháng không có số liệu; cần từ 2 điểm.
     */
    protected function sparkline(array $values): ?array
    {
        $values = array_values(array_filter($values, fn ($v) => $v !== null));
        if (count($values) < 2) {
            return null;
        }

        [$w, $h, $pad] = [120, 36, 3];
        $min = min($values);
        $range = max($values) - $min;
        $step = $w / (count($values) - 1);

        $points = [];
        foreach ($values as $i => $v) {
            $y = $range > 0 ? $h - $pad - ($v - $min) / $range * ($h - 2 * $pad) : $h / 2;
            $points[] = round($i * $step, 1).','.round($y, 1);
        }
        $line = implode(' ', $points);

        return ['line' => $line, 'area' => "0,$h $line $w,$h"];
    }

    /* ── Biểu đồ tab Đội kinh doanh ───────────────────────────────────── */

    #[Computed]
    public function teamCharts(): array
    {
        return [
            'status'   => $this->statusChart(),
            'share'    => $this->shareChart(),
            'revenue'  => $this->revenueChart(),
            'win_rate' => $this->winRateChart(),
        ];
    }

    /**
     * Lead đã giao trong tháng theo đội, chia theo tình trạng hiện tại (cột ngang chồng).
     * Lọc 1 salesperson → 1 cột của người đó.
     */
    protected function statusChart(): array
    {
        if ($this->byTeam) {
            $rows = array_values(array_filter($this->byTeam, fn ($r) => $r['assigned'] > 0));
            usort($rows, fn ($a, $b) => $b['assigned'] <=> $a['assigned']);
            $labels = array_column($rows, 'name');
        } else {
            $rows = $this->totals['assigned'] > 0 ? [$this->totals] : [];
            $labels = $rows ? [$this->employeeOptions->firstWhere('employee_id', $this->employee)?->full_name ?? ''] : [];
        }

        $series = [];
        foreach (self::STATUS_SERIES as $key => [$metric, $color, $text]) {
            $series[] = [
                'label' => __('reports.sales_team.status.'.$key), 'color' => $color, 'labelColor' => $text,
                'data'  => array_map(fn ($r) => $r[$metric], $rows),
            ];
        }

        return ['type' => 'stacked_h', 'unit' => 'count', 'labels' => $labels, 'series' => $series,
            'legend' => 'top', 'htmlLegend' => true, 'fontSize' => 13, 'dataLabels' => 'inside', 'boldCategories' => true,
            'barMax' => count($rows) > self::STATUS_COMPACT_TEAMS ? 26 : 40, 'barPercent' => 0.8];
    }

    /**
     * Cơ cấu theo đội (biểu đồ tròn) của doanh thu / học viên mới trong tháng: mỗi đội 1 phần, màu cố định của đội.
     * 'legend' = chú thích dưới hình tròn (mọi đội trong phạm vi, kể cả đội chưa có số liệu): [name, color].
     */
    protected function shareChart(): array
    {
        $metric = $this->activeShare();
        $teams = $this->byTeam;
        usort($teams, fn ($a, $b) => $b[$metric] <=> $a[$metric] ?: strnatcmp($a['name'], $b['name']));
        $total = array_sum(array_column($teams, $metric));
        $colors = array_map(fn ($t) => $this->teamColor($t['id']), $teams);
        $money = $metric === 'revenue';

        return [
            'type'       => 'doughnut',
            'unit'       => $money ? 'money' : 'count',
            'labels'     => array_column($teams, 'name'),
            'series'     => [['label' => __('reports.sales_team.share.'.$metric), 'data' => array_column($teams, $metric), 'colors' => $colors]],
            'htmlLegend' => true,
            'dataLabels' => 'percent',
            'cutout'     => '62%',
            'center'     => ['value' => $money ? $this->shortMoney($total) : number_format($total, 0, ',', '.'),
                'label' => __('reports.sales_team.share_total.'.$metric)],
            'legend'     => array_map(fn ($t, $c) => ['name' => $t['name'], 'color' => $c], $teams, $colors),
            'hasData'    => $total > 0,
        ];
    }

    /**
     * Doanh số 6 tháng: cột chồng theo đội (đóng góp từng đội) + đường đứt = tổng chỉ tiêu.
     */
    protected function revenueChart(): array
    {
        $trend = $this->trend;
        $months = array_column($trend, 'month');

        $series = $this->teamSeries($months, fn () => $this->teamMonthly, 'revenue', 0.0)
            ?: [['label' => __('reports.sales_performance.metric.revenue'), 'color' => self::PALETTE[0], 'data' => array_column($trend, 'revenue')]];

        $targets = array_column($trend, 'target');
        $hasTarget = $this->employee === '' && array_sum($targets) > 0;

        // Trục rút gọn: tỷ khi số lớn nhất từ 1 tỷ, còn lại triệu
        $peak = max([0, ...array_column($trend, 'revenue'), ...($hasTarget ? $targets : [])]);
        [$divisor, $suffix] = $peak >= 1e9 ? [1e9, ' '.__('reports.sales_team.billion')] : [1e6, ' '.__('reports.sales_team.million')];

        if ($hasTarget) {
            $series[] = [
                'label' => __('reports.sales_performance.metric.target'), 'type' => 'line', 'dash' => true, 'color' => '#111827',
                'data' => $targets,
                'pointLabels' => array_map(fn ($v) => $v ? number_format($v / $divisor, 1, ',', '.') : '', $targets),
            ];
        }

        return ['type' => 'stacked', 'unit' => 'money', 'labels' => array_map(fn ($m) => $this->monthLabel($m), $months),
            'series' => $series, 'legend' => 'top', 'htmlLegend' => true, 'fontSize' => 13, 'divisor' => $divisor, 'suffix' => $suffix,
            'tickDecimals' => $divisor === 1e9 ? 1 : 0, 'maxTicks' => 8, 'barMax' => 72, 'barPercent' => 0.85];
    }

    /**
     * Tỷ lệ thắng 6 tháng theo đội (theo tháng chốt).
     */
    protected function winRateChart(): array
    {
        $trend = $this->trend;
        $months = array_column($trend, 'month');

        $series = $this->teamSeries($months, fn () => $this->teamMonthly, 'close_rate', null)
            ?: [['label' => __('reports.sales_performance.metric.close_rate'), 'color' => self::PALETTE[0], 'data' => array_column($trend, 'close_rate')]];

        return ['type' => 'line', 'unit' => 'percent', 'labels' => array_map(fn ($m) => $this->shortMonth($m), $months),
            'series' => $series, 'legend' => 'top', 'htmlLegend' => true, 'fontSize' => 13, 'endLabels' => true, 'spanGaps' => true, 'fit' => true,
            'straight' => true, 'maxTicks' => 11];
    }

    /**
     * Mỗi đội của seriesTeams 1 dãy: giá trị $metric theo từng mốc (khoá "team_id|mốc" của $data()).
     * Không tách theo đội được → [].
     */
    protected function teamSeries(array $buckets, callable $data, string $metric, ?float $empty): array
    {
        if ($this->seriesTeams->isEmpty()) {
            return [];
        }

        $rows = $data();

        return $this->seriesTeams->map(fn ($team) => [
            'label' => $team->team_name,
            'color' => $this->teamColor($team->team_id),
            'data'  => array_map(fn ($b) => $rows[$team->team_id.'|'.$b][$metric] ?? $empty, $buckets),
        ])->all();
    }

    /* ── Biểu đồ tab Nhân viên kinh doanh ─────────────────────────────── */

    #[Computed]
    public function employeeCharts(): array
    {
        return [
            'share' => $this->employeeShareChart(),
            'top'   => $this->employeeTopChart(),
        ];
    }

    /**
     * Cơ cấu doanh thu / học viên mới theo nhân viên (biểu đồ tròn), lớn → nhỏ.
     * Nhiều hơn EMP_SHARE_MAX người thì gộp phần nhỏ thành "Nhân viên khác" (37 lát thì không đọc được).
     * 'legend' = chú thích dưới hình tròn: [name, color].
     */
    protected function employeeShareChart(): array
    {
        $metric = $this->activeEmpShare();
        $people = array_values(array_filter($this->employeeView, fn ($r) => $r[$metric] > 0));
        usort($people, fn ($a, $b) => $b[$metric] <=> $a[$metric] ?: strcmp($a['name'], $b['name']));
        $total = array_sum(array_column($people, $metric));

        $slices = [];
        foreach ($people as $i => $person) {
            if (count($people) > self::EMP_SHARE_MAX && $i === self::EMP_SHARE_MAX - 1) {
                $rest = array_slice($people, $i);
                $slices[] = ['name' => __('reports.sales_team.emp.other_people', ['count' => count($rest)]),
                    'color' => self::EMP_OTHER_COLOR, 'value' => array_sum(array_column($rest, $metric))];
                break;
            }
            $slices[] = ['name' => $person['name'], 'color' => self::PALETTE[$i % count(self::PALETTE)], 'value' => $person[$metric]];
        }

        $money = $metric === 'revenue';

        return [
            'type'       => 'doughnut',
            'unit'       => $money ? 'money' : 'count',
            'labels'     => array_column($slices, 'name'),
            'series'     => [['label' => __('reports.sales_team.share.'.$metric), 'data' => array_column($slices, 'value'),
                'colors' => array_column($slices, 'color')]],
            'htmlLegend' => true,
            'dataLabels' => 'percent',
            'cutout'     => '62%',
            'center'     => ['value' => $money ? $this->shortMoney($total) : number_format($total, 0, ',', '.'),
                'label' => __('reports.sales_team.share_total.'.$metric)],
            'legend'     => array_map(fn ($s) => ['name' => $s['name'], 'color' => $s['color']], $slices),
            'hasData'    => $total > 0,
        ];
    }

    /**
     * Top EMP_TOP nhân viên theo chỉ số chọn (thanh ngang), mỗi thanh màu của đội người đó.
     * Tỷ lệ thắng chỉ xét người có từ EMP_MIN_CLOSED cơ hội đóng. 'legend' = các đội có mặt: [name, color].
     */
    protected function employeeTopChart(): array
    {
        $metric = $this->activeEmpMetric();
        $unit = self::EMP_METRICS[$metric];
        $people = array_values(array_filter($this->employeeView, fn ($r) => $r[$metric] > 0
            && ($metric !== 'close_rate' || $r['won'] + $r['lost'] >= self::EMP_MIN_CLOSED)));
        usort($people, fn ($a, $b) => $b[$metric] <=> $a[$metric] ?: $b['revenue'] <=> $a['revenue']);
        $people = array_slice($people, 0, self::EMP_TOP);

        $color = fn ($r) => $r['team_id'] !== '' ? $this->teamColor($r['team_id']) : self::EMP_OTHER_COLOR;
        $label = fn ($v) => match ($unit) {
            'money'   => $this->shortMoney($v),
            'percent' => number_format($v, 1, ',', '.').'%',
            default   => number_format($v, 0, ',', '.'),
        };

        $legend = [];
        foreach ($people as $r) {
            $legend[$r['team_id']] ??= ['name' => $r['team'] ?? __('reports.sales_performance.export.no_team'), 'color' => $color($r)];
        }

        // Trục tiền rút gọn cùng kiểu với nhãn đầu thanh (48,5 tr / 1,2 tỷ)
        $peak = max([0, ...array_column($people, $metric)]);
        $axis = $unit !== 'money' ? [] : ($peak >= 1e9
            ? ['divisor' => 1e9, 'suffix' => ' '.__('reports.sales_team.billion')]
            : ['divisor' => 1e6, 'suffix' => ' '.__('reports.sales_team.million')]);

        return $axis + [
            'type'       => 'bar_h',
            'unit'       => $unit,
            'labels'     => array_column($people, 'name'),
            'series'     => [[
                'label'       => __('reports.sales_team.emp.metric.'.$metric),
                'data'        => array_column($people, $metric),
                'colors'      => array_map($color, $people),
                'valueLabels' => array_map($label, array_column($people, $metric)),
            ]],
            'dataLabels' => 'end',
            'fontSize'   => 13,
            'barMax'     => 22,
            'barPercent' => 0.8,
            'max'        => $unit === 'percent' ? 100 : null,
            'legend'     => array_values($legend),
            'count'      => count($people),
        ];
    }

    public function render()
    {
        return view('livewire.reports.sales-team-performance')->title(__('reports.sales_team.title'));
    }
}
