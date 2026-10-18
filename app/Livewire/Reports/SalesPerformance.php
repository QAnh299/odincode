<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\ExportPeriod;
use App\Models\Employee;
use App\Models\Role;
use App\Models\SalesTeam;
use App\Services\ReportDocumentWriter;
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
    use ExportPeriod;

    // Màu cố định theo chỉ số (bảng màu chung của trang báo cáo)
    const COLORS = [
        'revenue'              => '#2a78d6',
        'target'               => '#c3cad5',
        'reached'              => '#2a78d6',
    ];

    // Số dòng hiện trong bảng team / salesperson ('all' = tất cả, bảng có thanh cuộn)
    const TOPS = ['5', '10', 'all'];

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

    // Số dòng hiện ở bảng team / salesperson (xem TOPS)
    public string $teamTop = '5';

    public string $employeeTop = '5';

    // Đội của bảng salesperson ('' = tất cả đội trong phạm vi)
    public string $employeeTeam = '';

    public function updatedTeam(): void
    {
        $this->reset('employee', 'employeeTeam');
    }

    public function resetFilters(): void
    {
        $this->reset('month', 'team', 'employee', 'sort', 'employeeTeam');
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
        return $this->employeeRows($this->filterValues($this->activeMonth));
    }

    protected function employeeRows(array $filters): array
    {
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
     * Đội chọn được ở bảng salesperson: chỉ khi phạm vi có từ 2 đội và không lọc 1 salesperson.
     */
    #[Computed]
    public function employeeTeamOptions(): Collection
    {
        return $this->employee === '' && $this->scopeTeams->count() > 1 ? $this->scopeTeams : collect();
    }

    /**
     * Dòng bảng salesperson theo đội chọn ở đầu bảng; lọc 1 đội thì xếp hạng lại trong đội.
     */
    #[Computed]
    public function employeeView(): array
    {
        if (! $this->employeeTeamOptions->contains('team_id', $this->employeeTeam)) {
            return $this->byEmployee;
        }

        $rows = array_values(array_filter($this->byEmployee, fn ($r) => $r['team_id'] === $this->employeeTeam));
        $rank = 0;
        foreach ($rows as &$row) {
            $row['rank'] = $row['active'] ? ++$rank : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * Dòng tổng cộng của bảng salesperson (theo đội đang chọn ở bảng).
     */
    #[Computed]
    public function employeeViewTotals(): array
    {
        return count($this->employeeView) === count($this->byEmployee)
            ? $this->totals
            : $this->report()->combine($this->employeeView);
    }

    /**
     * Chỉ số theo đội trong phạm vi + chỉ tiêu, xếp hạng theo % đạt chỉ tiêu doanh thu.
     */
    #[Computed]
    public function byTeam(): array
    {
        return $this->teamRows($this->filterValues($this->activeMonth), $this->byEmployee, $this->targets);
    }

    /**
     * $people = dòng salesperson cùng kỳ (đếm số thành viên), $targets = chỉ tiêu cùng kỳ theo đội.
     */
    protected function teamRows(array $filters, array $people, array $targets): array
    {
        if ($this->employee !== '' || $this->scopeTeams->isEmpty()) {
            return [];
        }

        $data = $this->report()->aggregate($filters, 'team');
        $members = collect($people)->groupBy('team_id');

        $rows = [];
        foreach ($this->scopeTeams as $team) {
            $row = $data[$team->team_id] ?? $this->report()->derive([]);
            $row['id'] = $team->team_id;
            $row['name'] = $team->team_name;
            $row['members'] = count($members[$team->team_id] ?? []);
            $row['target'] = $targets[$team->team_id] ?? [];
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
        return $this->trendRows($this->trailingMonths($this->activeMonth));
    }

    /**
     * TREND_MONTHS tháng liên tiếp kết thúc ở $month.
     */
    protected function trailingMonths(string $month): array
    {
        $end = CarbonImmutable::parse($month.'-01');
        $months = [];
        for ($i = self::TREND_MONTHS - 1; $i >= 0; $i--) {
            $months[] = $end->subMonths($i)->format('Y-m');
        }

        return $months;
    }

    /**
     * Chỉ số từng tháng (Y-m liên tiếp) kèm tổng chỉ tiêu doanh thu của các đội đang xem.
     */
    protected function trendRows(array $months): array
    {
        $end = CarbonImmutable::parse(end($months).'-01');
        $filters = $this->filterRange($months[0].'-01', $end->endOfMonth()->toDateString());
        $data = $this->report()->aggregate($filters, 'month');

        return array_map(fn ($m) => ($data[$m] ?? $this->report()->derive([])) + [
            'month'  => $m,
            'target' => array_sum(array_column($this->targetsFor($m), 'revenue')),
        ], $months);
    }

    /**
     * Dòng hiện trong bảng: $top dòng đầu (đã xếp hạng) hoặc tất cả.
     */
    public function topRows(array $rows, string $top): array
    {
        return in_array($top, self::TOPS, true) && $top !== 'all' ? array_slice($rows, 0, (int) $top) : $rows;
    }

    /**
     * Chỉ giữ biểu đồ giúp đọc nhanh tình hình (chi tiết từng người đã có ở bảng):
     * doanh thu so với chỉ tiêu theo đội, phễu Stage, xu hướng doanh thu 6 tháng.
     */
    #[Computed]
    public function charts(): array
    {
        $label = fn ($m) => __('reports.sales_performance.metric.'.$m);
        $charts = [];

        // Đội: doanh thu so với chỉ tiêu (chỉ khi có từ 2 đội để so sánh)
        $teams = $this->byTeam;
        if (count($teams) > 1) {
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

        // Xu hướng doanh thu 6 tháng
        $trend = $this->trend;
        $months = array_map(fn ($r) => $this->monthLabel($r['month']), $trend);
        $revenueSeries = [['label' => $label('revenue'), 'color' => self::COLORS['revenue'], 'data' => array_column($trend, 'revenue')]];
        if ($this->employee === '' && array_sum(array_column($trend, 'target')) > 0) {
            $revenueSeries[] = ['label' => $label('target'), 'color' => self::COLORS['target'], 'data' => array_column($trend, 'target')];
        }
        $charts['trend_revenue'] = ['type' => 'bar', 'unit' => 'money', 'labels' => $months, 'series' => $revenueSeries];

        return $charts;
    }

    /* ── Xuất Excel ───────────────────────────────────────────────────── */

    /**
     * Xuất Excel: Giám đốc, Sale Admin.
     */
    #[Computed]
    public function canExport(): bool
    {
        return $this->canFilterTeam;
    }

    /**
     * Báo cáo tổng hợp của kỳ chọn trong popup (tháng / quý / kỳ lũy kế / năm), đội và salesperson theo bộ lọc:
     * Sales Team so với chỉ tiêu cộng dồn của kỳ, salesperson theo từng đội, kết quả từng tháng của kỳ
     * (xuất 1 tháng thì là xu hướng 6 tháng tính đến tháng đó).
     */
    public function export(ReportDocumentWriter $writer)
    {
        abort_unless($this->canExport, 403);

        $period = $this->exportPeriod();
        if (! $period) {
            return null;
        }

        $filters = $this->filterRange($period['from']->toDateString(), $period['to']->toDateString());
        $c = fn ($key, $replace = []) => __('reports.sales_performance.export.'.$key, $replace);
        $m = fn ($key) => __('reports.sales_performance.metric.'.$key);
        $employee = $this->employeeOptions->firstWhere('employee_id', $filters['employee']);
        $scope = [$period['range'], $c('scope_team').': '.($this->teamScope ? $this->scopeTeams->first()->team_name : __('reports.sales_performance.all_teams'))
            .($employee ? ' · '.$c('scope_employee').': '.$employee->full_name : '')];
        $notes = array_values(array_diff_key(__('reports.sales_performance.method'), array_flip(['title', 'compare'])));
        $attain = fn ($value, $goal) => $goal ? round($value * 100 / $goal, 1) : null;
        $sheets = [];

        // Sheet 1: Sales Team so với chỉ tiêu (không có khi đang lọc 1 salesperson)
        $people = $this->employeeRows($filters);
        $teams = $this->teamRows($filters, $people, $this->employee !== '' ? [] : $this->targetsForMonths($period['months']));
        if ($teams) {
            $target = fn (array $r, string $k) => $r['target'][$k] ?? null;
            $teamCells = fn (array $r, $rank) => [$r['members'], $r['assigned'], $target($r, 'assigned'), $attain($r['assigned'], $target($r, 'assigned')),
                $r['won'], $r['close_rate'], $r['students'], $target($r, 'students'), $attain($r['students'], $target($r, 'students')),
                $r['revenue'], $target($r, 'revenue'), $attain($r['revenue'], $target($r, 'revenue')), $r['paid'], $rank];

            $sum = $this->report()->combine($teams) + ['members' => array_sum(array_column($teams, 'members'))];
            foreach (SalesPerformanceReport::TARGETS as $metric) {
                $goals = array_filter(array_map(fn ($t) => $t['target'][$metric] ?? null, $teams), fn ($v) => $v !== null);
                $sum['target'][$metric] = $goals ? array_sum($goals) : null;
            }

            $rows = [['total', array_merge(['', $c('total')], $teamCells($sum, null))]];
            foreach ($teams as $n => $r) {
                $rows[] = ['item', array_merge([$n + 1, $r['name']], $teamCells($r, $r['rank']))];
            }

            $sheets[] = [
                'name'    => $c('sheet_team'),
                'title'   => $c('title_team'),
                'period'  => $period['label'],
                'scope'   => $scope,
                'unit'    => $c('unit'),
                'columns' => [[$c('stt'), 'stt'], [$c('team'), 'label'], [__('reports.sales_performance.members'), 'int'],
                    [$m('assigned'), 'int'], [$c('target_assigned'), 'int'], [$c('attain'), 'percent'],
                    [$m('won'), 'int'], [$m('close_rate'), 'percent'],
                    [$c('students'), 'int'], [$c('target_students'), 'int'], [$c('attain'), 'percent'],
                    [$m('revenue'), 'money'], [$c('target_revenue'), 'money'], [$c('attain'), 'percent'],
                    [$m('paid'), 'money'], [__('reports.sales_performance.rank'), 'int']],
                'rows'    => $rows,
                'notes'   => array_merge([$c('note_team_rank')], $notes),
            ];
        }

        // Sheet 2: salesperson nhóm theo đội (dòng đội = cộng các thành viên)
        $personCells = fn (array $r) => [$r['assigned'], $r['processed_rate'], $r['moved_rate'], $r['won'], $r['lost'], $r['close_rate'],
            $r['students'], $r['revenue'], $r['paid'], $r['avg_response_hours'], $r['avg_close_days'],
            $r['contacts'], $r['contact_success_rate'], $r['unreachable'], $r['backward'], $r['rank'] ?? null];

        $groups = collect($people)->groupBy('team_id');
        $order = $teams ? array_column($teams, 'id') : $this->scopeTeams->pluck('team_id')->all();
        $order = array_merge(array_values(array_intersect($order, $groups->keys()->all())),
            array_values(array_diff($groups->keys()->all(), $order)));

        $rows = [['total', array_merge(['', $c('total')], array_slice($personCells($this->report()->combine($people)), 0, -1), [null])]];
        foreach ($order as $i => $teamId) {
            $members = $groups[$teamId]->all();
            $name = $members[0]['team'] ?? $c('no_team');
            $rows[] = ['group', array_merge([$this->roman($i + 1), $name], array_slice($personCells($this->report()->combine($members)), 0, -1), [null])];
            foreach ($members as $n => $r) {
                $rows[] = ['item', array_merge([$n + 1, $r['name']], $personCells($r))];
            }
        }

        $sheets[] = [
            'name'    => $c('sheet_employee'),
            'title'   => $c('title_employee'),
            'period'  => $period['label'],
            'scope'   => $scope,
            'unit'    => $c('unit'),
            'columns' => [[$c('stt'), 'stt'], [$c('employee'), 'label'],
                [$m('assigned'), 'int'], [$m('processed_rate'), 'percent'], [$m('moved_rate'), 'percent'],
                [$m('won'), 'int'], [$c('lost'), 'int'], [$m('close_rate'), 'percent'], [$c('students'), 'int'],
                [$m('revenue'), 'money'], [$m('paid'), 'money'], [$c('response_hours'), 'decimal'], [$c('close_days'), 'decimal'],
                [$m('contacts'), 'int'], [$m('contact_success_rate'), 'percent'], [$m('unreachable'), 'int'], [$m('backward'), 'int'],
                [__('reports.sales_performance.rank'), 'int']],
            'rows'    => $rows,
            'notes'   => array_merge([$c('note_employee_rank', ['metric' => mb_strtolower($m($this->activeSort()))])], $notes),
        ];

        // Sheet 3: từng tháng của kỳ; kỳ 1 tháng thì xu hướng 6 tháng
        $single = count($period['months']) === 1;
        $trend = $this->trendRows($single ? $this->trailingMonths($period['months'][0]) : $period['months']);
        $trendCells = fn (array $r, $goal) => [$r['assigned'], $r['processed'], $r['won'], $r['close_rate'], $r['students'],
            $r['revenue'], $goal ?: null, $attain($r['revenue'], $goal), $r['paid']];
        $goalSum = array_sum(array_column($trend, 'target'));
        $rows = [['total', array_merge(['', $c('total')], $trendCells($this->report()->combine($trend), $goalSum))]];
        foreach ($trend as $n => $r) {
            $rows[] = ['item', array_merge([$n + 1, $c('month', ['month' => $this->monthLabel($r['month'])])], $trendCells($r, $r['target']))];
        }
        $sheets[] = [
            'name'    => $c($single ? 'sheet_trend' : 'sheet_months'),
            'title'   => $c($single ? 'title_trend' : 'title_months'),
            'period'  => $single ? $c('period_trend', ['from' => $this->monthLabel($trend[0]['month']), 'to' => $this->monthLabel(end($trend)['month'])]) : $period['label'],
            'scope'   => $scope,
            'unit'    => $c('unit'),
            'columns' => [[$c('stt'), 'stt'], [$c('month_col'), 'label'],
                [$m('assigned'), 'int'], [$m('processed'), 'int'], [$m('won'), 'int'], [$m('close_rate'), 'percent'], [$c('students'), 'int'],
                [$m('revenue'), 'money'], [$c('target_revenue'), 'money'], [$c('attain'), 'percent'], [$m('paid'), 'money']],
            'rows'    => $rows,
        ];

        $path = $writer->write($writer->document($sheets));

        $this->exportOpen = false;

        return response()->download($path, 'bao-cao-hieu-qua-sales-'.$period['slug'].'.xlsx')->deleteFileAfterSend();
    }

    /**
     * Năm chọn được trong popup xuất: các năm có trong danh sách tháng đánh giá.
     */
    public function exportYears(): array
    {
        return array_values(array_unique(array_map(fn ($m) => (int) substr($m, 0, 4), $this->months)));
    }

    protected function roman(int $number): string
    {
        $map = ['X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $text = '';
        foreach ($map as $symbol => $value) {
            while ($number >= $value) {
                $text .= $symbol;
                $number -= $value;
            }
        }

        return $text;
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

    /**
     * Chỉ tiêu cộng dồn các tháng của 1 kỳ: [team_id => [chỉ số => tổng]].
     */
    protected function targetsForMonths(array $months): array
    {
        $sum = [];
        foreach ($months as $month) {
            foreach ($this->targetsFor($month) as $team => $values) {
                foreach ($values as $metric => $value) {
                    $sum[$team][$metric] = ($sum[$team][$metric] ?? 0) + $value;
                }
            }
        }

        return $sum;
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

        return $this->filterRange($start->toDateString(), $start->endOfMonth()->toDateString());
    }

    /**
     * Bộ lọc của 1 khoảng ngày (Y-m-d), cùng phạm vi đội / salesperson.
     */
    protected function filterRange(string $from, string $to): array
    {
        return [
            'from'     => $from,
            'to'       => $to,
            'team'     => $this->teamScope === null ? '__none__' : ($this->teamScope ?: null),
            'employee' => $this->employeeOptions->contains('employee_id', $this->employee) ? $this->employee : null,
        ];
    }

    public function render()
    {
        return view('livewire.reports.sales-performance')->title(__('reports.sales_performance.title'));
    }
}
