<?php

namespace App\Livewire\Reports;

use App\Models\Employee;
use App\Models\Role;
use App\Models\SalesTeam;
use App\Services\SalesPerformanceReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard & Báo cáo → Phân tích hiệu quả Sales Team / Salesperson theo tháng
 * (Giám đốc, Sale Admin: tất cả đội; Sale Leader: đội của mình).
 *
 * - Team: so với chỉ tiêu tháng (TeamSalesTargets: doanh thu, học viên mới, Lead mới),
 *         xếp hạng theo % đạt chỉ tiêu doanh thu.
 * - Salesperson: xếp hạng theo chỉ số người xem chọn (mặc định doanh thu); mỗi ô được so với
 *         trung bình đội của người đó (xanh = tốt hơn, đỏ = kém hơn).
 * - Thẻ KPI so với tháng trước; xu hướng 6 tháng gần nhất.
 * Không dùng điểm tổng hợp. Định nghĩa và thời điểm tính từng chỉ số: xem App\Services\SalesPerformanceReport.
 */
#[Layout('layouts.app')]
class SalesPerformance extends Component
{
    // Màu cố định theo chỉ số (bảng màu chung của trang báo cáo)
    const COLORS = [
        'assigned'             => '#2a78d6',
        'processed'            => '#eda100',
        'moved'                => '#6250d6',
        'won'                  => '#1baf7a',
        'revenue'              => '#2a78d6',
        'paid'                 => '#1baf7a',
        'target'               => '#c3cad5',
        'contacts'             => '#2a78d6',
        'unreachable'          => '#eb6834',
        'backward'             => '#e34948',
        'processed_rate'       => '#eda100',
        'close_rate'           => '#1baf7a',
        'contact_success_rate' => '#2a78d6',
        'reached'              => '#2a78d6',
    ];

    // Chỉ số dùng để xếp hạng salesperson → chiều sắp (desc: cao là tốt, asc: thấp là tốt)
    const SORTS = [
        'revenue'            => 'desc',
        'won'                => 'desc',
        'close_rate'         => 'desc',
        'processed_rate'     => 'desc',
        'assigned'           => 'desc',
        'contacts'           => 'desc',
        'avg_response_hours' => 'asc',
        'avg_close_days'     => 'asc',
    ];

    /**
     * Cột bảng salesperson → [chiều so sánh với trung bình đội, loại].
     * Chiều: 1 = cao là tốt, -1 = thấp là tốt, 0 = không tô màu (khối lượng, không phải hiệu quả).
     * Loại: count = so với trung bình mỗi người trong đội, rate = so với tỷ lệ chung của đội.
     */
    const COLUMNS = [
        'assigned'             => [0, 'count'],
        'processed_rate'       => [1, 'rate'],
        'moved_rate'           => [1, 'rate'],
        'won'                  => [1, 'count'],
        'close_rate'           => [1, 'rate'],
        'revenue'              => [1, 'count'],
        'paid'                 => [0, 'count'],
        'avg_response_hours'   => [-1, 'rate'],
        'avg_close_days'       => [-1, 'rate'],
        'contacts'             => [0, 'count'],
        'contact_success_rate' => [1, 'rate'],
        'unreachable'          => [-1, 'count'],
        'backward'             => [-1, 'count'],
    ];

    const TREND_MONTHS = 6;

    /* ── Bộ lọc ───────────────────────────────────────────────────────── */

    // Tháng đánh giá (Y-m) - luôn đọc qua activeMonth
    #[Url(except: '')]
    public string $month = '';

    // Đội (Giám đốc, Sale Admin) - '' = Tất cả đội
    #[Url(except: '')]
    public string $team = '';

    // Salesperson - '' = Tất cả; chỉ áp dụng khi có trong employeeOptions
    #[Url(except: '')]
    public string $employee = '';

    #[Url(except: 'revenue')]
    public string $sort = 'revenue';

    public function updatedTeam(): void
    {
        $this->employee = '';
    }

    public function resetFilters(): void
    {
        $this->reset('month', 'team', 'employee', 'sort');
    }

    public function hasFilters(): bool
    {
        return $this->month !== '' || $this->team !== '' || $this->employee !== '' || $this->sort !== 'revenue';
    }

    /* ── Phạm vi theo vai trò ─────────────────────────────────────────── */

    #[Computed]
    public function me(): ?Employee
    {
        return Auth::user()->employee;
    }

    #[Computed]
    public function canFilterTeam(): bool
    {
        return in_array(Auth::user()->role_name, [Role::DIRECTOR, Role::SALE_ADMIN], true);
    }

    #[Computed]
    public function teams(): Collection
    {
        return SalesTeam::query()->orderBy('team_name')->get(['team_id', 'team_name']);
    }

    /**
     * Đội đang xem: Giám đốc / Sale Admin = đội đã chọn ('' = tất cả); Sale Leader = đội của mình
     * (không thuộc đội nào → null và không thấy dữ liệu).
     */
    #[Computed]
    public function teamScope(): ?string
    {
        if ($this->canFilterTeam) {
            return $this->teams->contains('team_id', $this->team) ? $this->team : '';
        }

        return $this->me?->team_id;
    }

    /**
     * Các đội trong phạm vi đang xem.
     */
    #[Computed]
    public function scopeTeams(): Collection
    {
        return match ($this->teamScope) {
            null    => collect(),
            ''      => $this->teams,
            default => $this->teams->where('team_id', $this->teamScope)->values(),
        };
    }

    /**
     * Salesperson trong phạm vi đang xem (cho bộ lọc và để hiện cả người chưa có số liệu).
     */
    #[Computed]
    public function employeeOptions(): Collection
    {
        if ($this->teamScope === null) {
            return collect();
        }

        return Employee::query()
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALESPERSON))
            ->when($this->teamScope !== '', fn ($q) => $q->where('team_id', $this->teamScope))
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'team_id', 'status']);
    }

    /* ── Tháng ────────────────────────────────────────────────────────── */

    /**
     * Các tháng chọn được (mới nhất trước): từ tháng có Lead được giao sớm nhất đến tháng hiện tại.
     */
    #[Computed]
    public function months(): array
    {
        $now = CarbonImmutable::now()->startOfMonth();
        $first = DB::table('Opportunities')->min('conversion_date');
        $start = $first ? CarbonImmutable::parse($first)->startOfMonth() : $now;
        $last = DB::table('Opportunities')->max('conversion_date');
        $end = $last ? max($now, CarbonImmutable::parse($last)->startOfMonth()) : $now;

        $months = [];
        for ($m = $end; $m >= $start; $m = $m->subMonth()) {
            $months[] = $m->format('Y-m');
        }

        return $months;
    }

    #[Computed]
    public function activeMonth(): string
    {
        return in_array($this->month, $this->months, true) ? $this->month : CarbonImmutable::now()->format('Y-m');
    }

    #[Computed]
    public function previousMonth(): string
    {
        return CarbonImmutable::parse($this->activeMonth.'-01')->subMonth()->format('Y-m');
    }

    public function monthLabel(string $month): string
    {
        return preg_match('/^(\d{4})-(\d{2})$/', $month, $m) ? "$m[2]/$m[1]" : $month;
    }

    /* ── Số liệu ──────────────────────────────────────────────────────── */

    #[Computed]
    public function totals(): array
    {
        return $this->report()->totals($this->filterValues($this->activeMonth));
    }

    #[Computed]
    public function previousTotals(): array
    {
        return $this->report()->totals($this->filterValues($this->previousMonth));
    }

    /**
     * Chỉ tiêu tháng của các đội trong phạm vi (không áp dụng khi đang lọc 1 salesperson).
     */
    #[Computed]
    public function targets(): array
    {
        return $this->targetsFor($this->activeMonth);
    }

    /**
     * Chỉ số theo salesperson, kèm hạng theo chỉ số đang chọn và so sánh với trung bình đội.
     * Người chưa có số liệu trong tháng vẫn hiện (đang làm việc), xếp cuối và không có hạng.
     */
    #[Computed]
    public function byEmployee(): array
    {
        $filters = $this->filterValues($this->activeMonth);
        $rows = $this->report()->aggregate($filters, 'employee');

        if (! $filters['employee']) {
            foreach ($this->employeeOptions as $option) {
                if (! isset($rows[$option->employee_id]) && $option->isActive()) {
                    $rows[$option->employee_id] = $this->report()->derive([]);
                }
            }
        }

        $people = Employee::query()->whereIn('employee_id', array_keys($rows))
            ->get(['employee_id', 'full_name', 'team_id'])->keyBy('employee_id');
        $teamNames = $this->teams->pluck('team_name', 'team_id');

        foreach ($rows as $id => &$row) {
            $row['id'] = (string) $id;
            $row['name'] = $people[$id]->full_name ?? $id;
            $row['team_id'] = $people[$id]->team_id ?? '';
            $row['team'] = $teamNames[$row['team_id']] ?? null;
            $row['active'] = $row['assigned'] > 0 || $row['won'] + $row['lost'] > 0 || $row['revenue'] > 0;
        }
        unset($row);

        $rows = array_values($rows);
        $this->rank($rows, $this->activeSort());
        $this->compareWithTeam($rows);

        return $rows;
    }

    /**
     * Chỉ số theo đội trong phạm vi + chỉ tiêu, xếp hạng theo % đạt chỉ tiêu doanh thu.
     */
    #[Computed]
    public function byTeam(): array
    {
        if ($this->employee !== '' || $this->scopeTeams->isEmpty()) {
            return [];
        }

        $data = $this->report()->aggregate($this->filterValues($this->activeMonth), 'team');
        $members = collect($this->byEmployee)->groupBy('team_id');

        $rows = [];
        foreach ($this->scopeTeams as $team) {
            $row = $data[$team->team_id] ?? $this->report()->derive([]);
            $row['id'] = $team->team_id;
            $row['name'] = $team->team_name;
            $row['members'] = count($members[$team->team_id] ?? []);
            $row['target'] = $this->targets[$team->team_id] ?? [];
            foreach (SalesPerformanceReport::TARGETS as $metric) {
                $goal = $row['target'][$metric] ?? null;
                $row['attain'][$metric] = $goal ? round($row[$metric] * 100 / $goal, 1) : null;
            }
            $rows[] = $row;
        }

        // Có chỉ tiêu xếp trước, % đạt giảm dần; rồi doanh thu
        usort($rows, fn ($a, $b) => [$b['attain']['revenue'] !== null, $b['attain']['revenue'] ?? 0, $b['revenue']]
            <=> [$a['attain']['revenue'] !== null, $a['attain']['revenue'] ?? 0, $a['revenue']]);
        foreach ($rows as $i => &$row) {
            $row['rank'] = $i + 1;
        }
        unset($row);

        return $rows;
    }

    #[Computed]
    public function stages(): array
    {
        return $this->report()->funnelStages();
    }

    /**
     * Xu hướng 6 tháng tính đến tháng đang xem.
     */
    #[Computed]
    public function trend(): array
    {
        $end = CarbonImmutable::parse($this->activeMonth.'-01');
        $months = [];
        for ($i = self::TREND_MONTHS - 1; $i >= 0; $i--) {
            $months[] = $end->subMonths($i)->format('Y-m');
        }

        $filters = ['from' => $months[0].'-01', 'to' => $end->endOfMonth()->toDateString()] + $this->filterValues($this->activeMonth);
        $data = $this->report()->aggregate($filters, 'month');

        return array_map(fn ($m) => ($data[$m] ?? $this->report()->derive([])) + [
            'month'  => $m,
            'target' => array_sum(array_column($this->targetsFor($m), 'revenue')),
        ], $months);
    }

    #[Computed]
    public function charts(): array
    {
        $people = array_values(array_filter($this->byEmployee, fn ($r) => $r['active']));
        $names = array_column($people, 'name');
        $label = fn ($m) => __('reports.sales_performance.metric.'.$m);
        $series = fn (array $rows, array $metrics) => array_map(fn ($m) => [
            'label' => $label($m), 'color' => self::COLORS[$m], 'data' => array_column($rows, $m),
        ], $metrics);

        $charts = [
            'pipeline' => ['type' => 'bar', 'unit' => 'count', 'labels' => $names,
                'series' => $series($people, ['assigned', 'processed', 'moved', 'won'])],
            'revenue'  => ['type' => 'bar', 'unit' => 'money', 'labels' => $names,
                'series' => $series($people, ['revenue', 'paid'])],
            'rates'    => ['type' => 'bar', 'unit' => 'percent', 'labels' => $names,
                'series' => $series($people, ['processed_rate', 'close_rate', 'contact_success_rate'])],
            'quality'  => ['type' => 'bar', 'unit' => 'count', 'labels' => $names,
                'series' => $series($people, ['contacts', 'unreachable', 'backward'])],
        ];

        // Đội: doanh thu so với chỉ tiêu
        $teams = $this->byTeam;
        if ($teams) {
            $charts['team'] = ['type' => 'bar', 'unit' => 'money', 'labels' => array_column($teams, 'name'),
                'series' => [
                    ['label' => $label('revenue'), 'color' => self::COLORS['revenue'], 'data' => array_column($teams, 'revenue')],
                    ['label' => $label('target'), 'color' => self::COLORS['target'],
                        'data' => array_map(fn ($t) => $t['target']['revenue'] ?? 0, $teams)],
                ]];
        }

        // Phễu chuyển đổi theo stage (Lead được giao trong tháng)
        $t = $this->totals;
        $charts['funnel'] = ['type' => 'bar_h', 'unit' => 'count', 'labels' => array_column($this->stages, 'name'),
            'series' => [['label' => $label('reached'), 'color' => self::COLORS['reached'],
                'data' => array_map(fn ($s) => $t['stages'][$s['order']]['reached'], $this->stages)]]];

        // Xu hướng 6 tháng
        $trend = $this->trend;
        $months = array_map(fn ($r) => $this->monthLabel($r['month']), $trend);
        $charts['trend_count'] = ['type' => 'line', 'unit' => 'count', 'labels' => $months,
            'series' => $series($trend, ['assigned', 'processed', 'won'])];
        $revenueSeries = $series($trend, ['revenue']);
        if ($this->employee === '' && array_sum(array_column($trend, 'target')) > 0) {
            $revenueSeries[] = ['label' => $label('target'), 'color' => self::COLORS['target'], 'data' => array_column($trend, 'target')];
        }
        $charts['trend_revenue'] = ['type' => 'bar', 'unit' => 'money', 'labels' => $months, 'series' => $revenueSeries];

        return $charts;
    }

    /* ── Hỗ trợ ───────────────────────────────────────────────────────── */

    public function activeSort(): string
    {
        return isset(self::SORTS[$this->sort]) ? $this->sort : 'revenue';
    }

    /**
     * Sắp và gán hạng: người có số liệu xếp trước theo chỉ số chọn (không có giá trị thì xuống cuối),
     * hoà thì xét doanh thu, rồi số Lead chốt. Người chưa có số liệu không có hạng.
     */
    protected function rank(array &$rows, string $metric): void
    {
        $desc = self::SORTS[$metric] === 'desc';

        usort($rows, function ($a, $b) use ($metric, $desc) {
            $x = $a[$metric];
            $y = $b[$metric];
            $byMetric = match (true) {
                $x === null && $y === null => 0,
                $x === null => 1,
                $y === null => -1,
                default => $desc ? $y <=> $x : $x <=> $y,
            };

            return $b['active'] <=> $a['active']
                ?: $byMetric
                ?: $b['revenue'] <=> $a['revenue']
                ?: $b['won'] <=> $a['won']
                ?: strcmp($a['name'], $b['name']);
        });

        $rank = 0;
        foreach ($rows as &$row) {
            $row['rank'] = $row['active'] ? ++$rank : null;
        }
        unset($row);
    }

    /**
     * Gắn trạng thái so với trung bình đội cho từng ô: compare[col] = 'up' (tốt hơn) | 'down' (kém hơn) | null.
     * Chỉ so khi đội có từ 2 người có số liệu.
     */
    protected function compareWithTeam(array &$rows): void
    {
        $groups = [];
        foreach ($rows as $row) {
            if ($row['active']) {
                $groups[$row['team_id']][] = $row;
            }
        }

        $references = [];
        foreach ($groups as $teamId => $members) {
            if (count($members) < 2) {
                continue;
            }
            $combined = $this->report()->combine($members);
            foreach (self::COLUMNS as $col => [$direction, $kind]) {
                $value = $combined[$col];
                $references[$teamId][$col] = $kind === 'count' && $value !== null ? $value / count($members) : $value;
            }
        }

        foreach ($rows as &$row) {
            $row['compare'] = [];
            foreach (self::COLUMNS as $col => [$direction]) {
                $reference = $references[$row['team_id']][$col] ?? null;
                $value = $row[$col];
                $row['compare'][$col] = ! $row['active'] || ! $direction || $reference === null || $value === null
                    || abs($value - $reference) < 0.05
                    ? null
                    : (($value - $reference) * $direction > 0 ? 'up' : 'down');
            }
        }
        unset($row);
    }

    /**
     * Chỉ tiêu của các đội trong phạm vi cho 1 tháng (không áp dụng khi đang lọc 1 salesperson).
     */
    protected function targetsFor(string $month): array
    {
        if ($this->employee !== '' || $this->scopeTeams->isEmpty()) {
            return [];
        }

        return $this->report()->targets($month, $this->scopeTeams->pluck('team_id')->all());
    }

    protected function report(): SalesPerformanceReport
    {
        return app(SalesPerformanceReport::class);
    }

    /**
     * Bộ lọc của 1 tháng (giá trị không hợp lệ bị bỏ qua). Sale Leader không có đội → không thấy gì.
     */
    protected function filterValues(string $month): array
    {
        $start = CarbonImmutable::parse($month.'-01');

        return [
            'from'     => $start->toDateString(),
            'to'       => $start->endOfMonth()->toDateString(),
            'team'     => $this->teamScope === null ? '__none__' : ($this->teamScope ?: null),
            'employee' => $this->employeeOptions->contains('employee_id', $this->employee) ? $this->employee : null,
        ];
    }

    public function render()
    {
        return view('livewire.reports.sales-performance')->title(__('reports.sales_performance.title'));
    }
}
