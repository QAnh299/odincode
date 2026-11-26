<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\ReportDrill;
use App\Livewire\Reports\Concerns\ReportExport;
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

#[Layout('layouts.app')]
class SalesPerformance extends Component
{
    use ReportDrill;
    use ReportExport;

    public const COLORS = [
        'revenue' => '#2a78d6',
        'target'  => '#c3cad5',
    ];

    public const TEAM_COLORS = ['#2a78d6', '#16a34a', '#d97706', '#9333ea', '#0891b2', '#db2777'];

    public const TOPS = ['5', '10', 'all'];

    public const SORTS = [
        'revenue'            => 'desc',
        'won'                => 'desc',
        'close_rate'         => 'desc',
        'avg_order'          => 'desc',
        'open'               => 'desc',
        'activity'           => 'desc',
        'reach_rate'         => 'desc',
        'show_rate'          => 'desc',
        'avg_response_hours' => 'asc',
    ];

    public const COLUMN_GROUPS = [
        'result' => [
            'revenue'    => [1, 'count'],
            'won'        => [1, 'count'],
            'close_rate' => [1, 'rate'],
            'avg_order'  => [1, 'rate'],
        ],
        'pipeline' => [
            'open' => [0, 'count'],
        ],
        'effort' => [
            'activity'           => [1, 'count'],
            'reach_rate'         => [1, 'rate'],
            'show_rate'          => [1, 'rate'],
            'avg_response_hours' => [-1, 'rate'],
        ],
    ];

    public const TREND_MONTHS = 6;

    public const MAX_TREND_TEAMS = 5;

    #[Url(except: '')]
    public string $month = '';

    #[Url(except: '')]
    public string $team = '';

    #[Url(except: '')]
    public string $employee = '';

    #[Url(except: 'revenue')]
    public string $sort = 'revenue';

    public string $teamTop = '5';

    public string $employeeTop = '10';

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

    #[Computed]
    public function teamScope(): ?string
    {
        if ($this->canFilterTeam) {
            return $this->teams->contains('team_id', $this->team) ? $this->team : '';
        }

        return $this->me?->team_id;
    }

    #[Computed]
    public function scopeTeams(): Collection
    {
        return match ($this->teamScope) {
            null    => collect(),
            ''      => $this->teams,
            default => $this->teams->where('team_id', $this->teamScope)->values(),
        };
    }

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

    #[Computed]
    public function totals(): array
    {
        $filters = $this->filterValues($this->activeMonth);

        return $this->report()->totals($filters) + ($this->report()->pipeline($filters, 'all')['all'] ?? ['open' => 0, 'open_value' => 0.0]);
    }

    #[Computed]
    public function previousTotals(): array
    {
        return $this->report()->totals($this->filterValues($this->previousMonth));
    }

    #[Computed]
    public function targets(): array
    {
        return $this->targetsFor($this->activeMonth, $this->filterValues($this->activeMonth));
    }

    #[Computed]
    public function targetTotals(): array
    {
        $sum = [];
        foreach (['revenue', 'students'] as $metric) {
            $goals = array_filter(array_column($this->targets, $metric));
            $sum[$metric] = $goals ? array_sum($goals) : null;
        }

        return $sum;
    }

    #[Computed]
    public function alerts(): array
    {
        return $this->report()->alerts($this->filterValues($this->activeMonth));
    }

    #[Computed]
    public function byEmployee(): array
    {
        return $this->employeeRows($this->filterValues($this->activeMonth), true);
    }

    protected function employeeRows(array $filters, bool $current = false): array
    {
        $rows = $this->report()->aggregate($filters, 'employee');
        $pipeline = $current ? $this->report()->pipeline($filters, 'employee') : [];
        $alerts = $current ? $this->alerts : [];

        if (! $filters['employee']) {
            foreach ($this->salespeople($filters['team']) as $option) {
                if (! isset($rows[$option->employee_id]) && $option->isActive()) {
                    $rows[$option->employee_id] = $this->report()->derive([]);
                }
            }
        }
        foreach (array_keys($pipeline + $alerts) as $id) {
            $rows[$id] ??= $this->report()->derive([]);
        }

        $people = Employee::query()->whereIn('employee_id', array_keys($rows))
            ->get(['employee_id', 'full_name', 'team_id', 'status'])->keyBy('employee_id');
        $teamNames = $this->teams->pluck('team_name', 'team_id');

        foreach ($rows as $id => &$row) {
            $row['id'] = (string) $id;
            $row['name'] = $people[$id]->full_name ?? $id;
            $row['team_id'] = $people[$id]->team_id ?? '';
            $row['team'] = $teamNames[$row['team_id']] ?? null;
            $row['resigned'] = isset($people[$id]) && ! $people[$id]->isActive();
            $row['open'] = $pipeline[$id]['open'] ?? 0;
            $row['open_value'] = $pipeline[$id]['open_value'] ?? 0.0;
            $row['alerts'] = $alerts[$id] ?? [];
            $row['active'] = $row['assigned'] > 0 || $row['won'] + $row['lost'] > 0 || $row['revenue'] > 0 || $row['activity'] > 0;
        }
        unset($row);

        $teamRevenue = [];
        foreach ($rows as $row) {
            $teamRevenue[$row['team_id']] = ($teamRevenue[$row['team_id']] ?? 0) + $row['revenue'];
        }
        foreach ($rows as &$row) {
            $total = $teamRevenue[$row['team_id']];
            $row['share'] = $total > 0 ? round($row['revenue'] * 100 / $total, 1) : null;
        }
        unset($row);

        $rows = array_values($rows);
        $this->rank($rows, $this->activeSort());
        $this->compareWithTeam($rows);

        return $rows;
    }

    #[Computed]
    public function employeeTeamOptions(): Collection
    {
        return $this->employee === '' && $this->scopeTeams->count() > 1 ? $this->scopeTeams : collect();
    }

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

    #[Computed]
    public function employeeViewTotals(): array
    {
        $rows = $this->employeeView;

        return $this->report()->combine($rows) + [
            'open'       => array_sum(array_column($rows, 'open')),
            'open_value' => array_sum(array_column($rows, 'open_value')),
        ];
    }

    #[Computed]
    public function byTeam(): array
    {
        $filters = $this->filterValues($this->activeMonth);
        $rows = $this->teamRows($filters, $this->byEmployee, $this->targets);

        $ranks = [];
        if ($rows && ($filters['team'] ?? null)) {
            $all = ['team' => null] + $filters;
            $ranks = array_column($this->teamRows($all, [], $this->rankTargets($all)), 'rank', 'id');
        }

        foreach ($rows as &$row) {
            $members = array_filter($this->byEmployee, fn ($r) => $r['team_id'] === $row['id']);
            $row['open'] = array_sum(array_column($members, 'open'));
            $row['open_value'] = array_sum(array_column($members, 'open_value'));
            $row['flagged'] = array_sum(array_map(fn ($r) => $r['alerts']['flagged'] ?? 0, $members));
            $row['rank'] = $ranks[$row['id']] ?? $row['rank'];
        }
        unset($row);

        return $rows;
    }

    protected function rankTargets(array $filters): array
    {
        return $this->targetsFor($this->activeMonth, $filters);
    }

    protected function teamRows(array $filters, array $people, array $targets): array
    {
        $teams = $this->filterTeams($filters);
        if ($teams->isEmpty()) {
            return [];
        }

        $data = $this->report()->aggregate($filters, 'team');
        $members = collect($people)->where('resigned', false)->groupBy('team_id');

        $rows = [];
        foreach ($teams as $team) {
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

        usort($rows, fn ($a, $b) => [$b['attain']['revenue'] !== null, $b['attain']['revenue'] ?? 0, $b['revenue']]
            <=> [$a['attain']['revenue'] !== null, $a['attain']['revenue'] ?? 0, $a['revenue']]);
        foreach ($rows as $i => &$row) {
            $row['rank'] = $i + 1;
        }
        unset($row);

        return $rows;
    }

    #[Computed]
    public function alertRows(): array
    {
        $rows = array_values(array_filter($this->byEmployee, fn ($r) => ($r['alerts']['flagged'] ?? 0) > 0));
        usort($rows, fn ($a, $b) => $b['alerts']['flagged'] <=> $a['alerts']['flagged'] ?: strcmp($a['name'], $b['name']));

        return $rows;
    }

    #[Computed]
    public function trend(): array
    {
        return $this->trendRows($this->trailingMonths($this->activeMonth), $this->filterValues($this->activeMonth));
    }

    protected function trailingMonths(string $month): array
    {
        $end = CarbonImmutable::parse($month.'-01');
        $months = [];
        for ($i = self::TREND_MONTHS - 1; $i >= 0; $i--) {
            $months[] = $end->subMonths($i)->format('Y-m');
        }

        return $months;
    }

    protected function trendRows(array $months, array $filters): array
    {
        $data = $this->report()->aggregate($this->monthsFilter($months, $filters), 'month');

        return array_map(fn ($m) => ($data[$m] ?? $this->report()->derive([])) + [
            'month'  => $m,
            'target' => array_sum(array_column($this->targetsFor($m, $filters), 'revenue')),
        ], $months);
    }

    protected function monthsFilter(array $months, array $filters): array
    {
        $end = CarbonImmutable::parse(end($months).'-01');

        return ['from' => $months[0].'-01', 'to' => $end->endOfMonth()->toDateString()] + $filters;
    }

    public function topRows(array $rows, string $top): array
    {
        return in_array($top, self::TOPS, true) && $top !== 'all' ? array_slice($rows, 0, (int) $top) : $rows;
    }

    #[Computed]
    public function charts(): array
    {
        $label = fn ($m) => __('reports.sales_performance.metric.'.$m);
        $charts = [];

        $trend = $this->trend;
        $months = array_map(fn ($r) => $this->monthLabel($r['month']), $trend);
        $revenueSeries = [['label' => $label('revenue'), 'color' => self::COLORS['revenue'], 'data' => array_column($trend, 'revenue')]];
        if ($this->employee === '' && array_sum(array_column($trend, 'target')) > 0) {
            $revenueSeries[] = ['label' => $label('target'), 'color' => self::COLORS['target'], 'data' => array_column($trend, 'target')];
        }
        $charts['trend_revenue'] = ['type' => 'bar', 'unit' => 'money', 'labels' => $months, 'series' => $revenueSeries,
            'drills' => [$this->monthDrills('revenue', array_column($trend, 'month'))]];

        $teams = $this->employee === '' ? $this->scopeTeams : collect();
        if ($teams->count() > 1 && $teams->count() <= self::MAX_TREND_TEAMS) {
            $data = $this->report()->aggregate($this->monthsFilter(array_column($trend, 'month'), $this->filterValues($this->activeMonth)), 'team_month');
            $series = $teams->values()->map(fn ($team, $i) => [
                'label' => $team->team_name,
                'color' => self::TEAM_COLORS[$i % count(self::TEAM_COLORS)],
                'data'  => array_map(fn ($r) => $data[$team->team_id.'|'.$r['month']]['close_rate'] ?? null, $trend),
            ])->all();
        } else {
            $series = [['label' => $label('close_rate'), 'color' => self::COLORS['revenue'], 'data' => array_column($trend, 'close_rate')]];
        }
        $charts['trend_close'] = ['type' => 'line', 'unit' => 'percent', 'labels' => $months, 'series' => $series,
            'drills' => $this->teamDrills('closed', array_column($trend, 'month'), count($series) > 1 ? $teams : collect())];

        return $this->drillCharts($charts);
    }

    public const COLUMN_DRILLS = [
        'revenue' => 'revenue', 'won' => 'won', 'close_rate' => 'closed', 'avg_order' => 'revenue', 'open' => 'open',
        'activity' => 'activity', 'reach_rate' => 'care', 'show_rate' => 'appts', 'avg_response_hours' => 'response',
    ];

    public const CURRENT_DRILLS = ['open', 'uncontacted', 'flagged', ...SalesPerformanceReport::ALERTS];

    protected array $drillEmployeeTeams = [];

    protected function drillUrl(string $kind, array $params): ?string
    {
        $page = self::DRILL_PAGES[$kind] ?? null;
        $f = $this->filterValues($this->activeMonth);
        if ($page === null || ($f['branch'] ?? null) || $f['team'] === '__none__') {
            return null;
        }

        $team = $this->drillText($params, 'team');
        if ($team !== null && $f['team'] !== null && $team !== $f['team']) {
            return null;
        }
        $team ??= $f['team'];

        $ids = array_values(array_filter((array) ($params['employees'] ?? []), 'is_string'));
        if (count($ids) > 1 || ($ids && $f['employee'] && $ids[0] !== $f['employee'])) {
            return null;
        }
        $employee = $ids[0] ?? $f['employee'];
        if ($employee !== null) {
            $team = $this->drillEmployeeTeams[$employee] ??= (string) Employee::query()->whereKey($employee)->value('team_id');
            if ($team === '' || ($f['team'] !== null && $team !== $f['team'])) {
                return null;
            }
        }

        $team = $this->canFilterTeam ? $team : null;

        $month = $this->drillMonth($params);
        [$from, $to] = $month ? $this->drillMonthRange($month) : [$f['from'], $f['to']];

        return match ($kind) {
            'open'     => $employee === null ? null : $this->listUrlFor($page, ['team' => $team, 'employee' => $employee]),
            'revenue'  => $this->listUrlFor($page, ['created_from' => $from, 'created_to' => $to, 'team' => $team, 'employee' => $employee]),
            'students' => $this->listUrlFor($page, ['from' => $from, 'to' => $to, 'team' => $team, 'employee' => $employee]),
        };
    }

    public const DRILL_PAGES = ['open' => 'opportunities', 'revenue' => 'orders', 'students' => 'students'];

    protected function drillKinds(): array
    {
        $who = ['customer' => 'text', 'employee' => 'text', 'team' => 'text'];
        $assigned = $who + ['source' => 'text', 'assigned_at' => 'date', 'stage' => 'text', 'status' => 'text', 'contacts' => 'int', 'response_hours' => 'decimal'];
        $closed = $who + ['assigned_at' => 'date', 'closed_at' => 'date', 'status' => 'text', 'close_days' => 'decimal'];
        $activity = $who + ['performed_at' => 'date', 'activity' => 'text', 'result' => 'text', 'stage' => 'text'];
        $open = $who + ['source' => 'text', 'assigned_at' => 'date', 'stage' => 'text', 'expected_value' => 'money'];

        return array_fill_keys(array_keys(SalesPerformanceReport::RECORDS_ASSIGNED), $assigned)
            + array_fill_keys(array_keys(SalesPerformanceReport::RECORDS_CLOSED), $closed)
            + ['revenue' => ['order_id' => 'text'] + $who + ['ordered_at' => 'date', 'total_amount' => 'money', 'paid_amount' => 'money']]
            + ['students' => ['student_id' => 'text', 'student_name' => 'text', 'employee' => 'text', 'team' => 'text', 'enrolled_at' => 'date']]
            + array_fill_keys(['activity', 'care', 'appts'], $activity)
            + array_fill_keys(self::CURRENT_DRILLS, $open);
    }

    protected function drillList(string $kind, array $params): array
    {
        $filters = $this->filterValues($this->activeMonth);
        $month = $this->drillMonth($params);
        if ($month !== null) {
            $filters = $this->monthsFilter([$month], $filters);
        }
        $team = $this->drillText($params, 'team');
        $ids = array_values(array_filter((array) ($params['employees'] ?? []), 'is_string'));

        $rows = $this->report()->records($kind, $filters + ['only_team' => $team, 'only_employees' => $ids]);
        $person = count($ids) === 1 ? $rows[0]['employee'] ?? null : null;

        $rows = array_map(fn ($r) => [
            '_lead'        => $r['lead_id'],
            '_opportunity' => $r['opportunity_id'],
            '_order'       => $r['order_id'] ?? null,
            '_student'     => $r['student_id'] ?? null,
            'assigned_at'  => $r['assigned_at'] ?? $r['at'],
            'closed_at'    => $r['at'],
            'performed_at' => $r['at'],
            'ordered_at'   => $r['at'],
            'enrolled_at'  => $r['at'],
            'status'       => isset($r['status']) ? __('opportunities.status_name.'.$r['status']) : null,
            'activity'     => match ($r['activity'] ?? null) {
                null    => null,
                'care'  => __('reports.drill.care'),
                default => __('opportunities.appointment_type_name.'.$r['activity']),
            },
            'result'       => match ($r['activity'] ?? null) {
                null    => null,
                'care'  => __('opportunities.care_result.'.$r['result']),
                default => __('reports.drill.appointment_result.'.$r['result']),
            },
        ] + $r, $rows);

        return [$rows, [
            $this->scopeTeams->firstWhere('team_id', $team)?->team_name,
            count($ids) > 1 ? __('reports.sales_team.emp.other_people', ['count' => count($ids)]) : $person,
            match (true) {
                in_array($kind, self::CURRENT_DRILLS, true) => __('reports.drill.now'),
                $month !== null => $this->monthLabel($month),
                default => $this->drillPeriodLabel($filters['from'], $filters['to']),
            },
        ]];
    }

    protected function monthDrills(string $kind, array $months, ?string $team = null): array
    {
        return array_map(fn ($m) => ['kind' => $kind, 'month' => $m] + ($team === null ? [] : ['team' => $team]), $months);
    }

    protected function teamDrills(string $kind, array $months, Collection $teams): array
    {
        return $teams->isEmpty()
            ? [$this->monthDrills($kind, $months)]
            : $teams->values()->map(fn ($t) => $this->monthDrills($kind, $months, $t->team_id))->all();
    }

    public const EXPORT_SHEETS = ['teams', 'employees'];

    public const EXPORT_LOCKED = ['teams' => 'team', 'employees' => 'employee'];

    public string $exportTeam = '';

    public string $exportEmployee = '';

    #[Computed]
    public function canExport(): bool
    {
        return $this->canFilterTeam;
    }

    public function exportColumnCatalog(): array
    {
        return [
            'teams' => [
                'team' => 'label', 'members' => 'int',
                'revenue' => 'money', 'target_revenue' => 'money', 'attain_revenue' => 'percent',
                'students' => 'int', 'target_students' => 'int', 'attain_students' => 'percent',
                'won' => 'int', 'lost' => 'int', 'close_rate' => 'percent', 'avg_order' => 'money',
                'avg_close_days' => 'decimal', 'care' => 'int', 'appts' => 'int', 'rank' => 'int',
            ],
            'employees' => [
                'employee' => 'label', 'team' => 'text', 'revenue' => 'money', 'share' => 'percent',
                'won' => 'int', 'lost' => 'int', 'close_rate' => 'percent', 'avg_order' => 'money', 'students' => 'int',
                'activity' => 'int', 'care' => 'int', 'reach_rate' => 'percent', 'appts' => 'int', 'show_rate' => 'percent',
                'avg_response_hours' => 'decimal', 'avg_close_days' => 'decimal', 'transfers_out' => 'int', 'rank' => 'int',
            ],
        ];
    }

    public function exportLabel(string $key, array $replace = []): string
    {
        return __('reports.sales_performance.export.'.$key, $replace);
    }

    public function exportFilterFields(): array
    {
        return [
            ['model' => 'exportTeam', 'label' => __('reports.sales_performance.team'), 'all' => __('reports.sales_performance.all_teams'),
                'options' => $this->teams->pluck('team_name', 'team_id')->all()],
            ['model' => 'exportEmployee', 'label' => __('reports.sales_performance.employee'), 'all' => __('reports.all'),
                'options' => $this->exportEmployeeOptions->mapWithKeys(fn ($e) => [$e->employee_id => $e->full_name
                    .($e->isActive() ? '' : ' ('.__('reports.sales_performance.resigned').')')])->all()],
        ];
    }

    #[Computed]
    public function exportEmployeeOptions(): Collection
    {
        return $this->salespeople($this->teams->contains('team_id', $this->exportTeam) ? $this->exportTeam : null);
    }

    public function updatedExportTeam(): void
    {
        $this->exportEmployee = '';
    }

    protected function resetExportFilters(): void
    {
        $this->reset('exportTeam', 'exportEmployee');
    }

    protected function exportCriteria(): array
    {
        $team = $this->teams->firstWhere('team_id', $this->exportTeam);
        $employee = $this->exportEmployeeOptions->firstWhere('employee_id', $this->exportEmployee);

        $parts = array_filter([
            $team ? __('reports.sales_performance.team').': '.$team->team_name : null,
            $employee ? __('reports.sales_performance.employee').': '.$employee->full_name : null,
        ]);

        return [
            ['team' => $team?->team_id, 'employee' => $employee?->employee_id],
            $parts ? implode(' · ', $parts) : $this->exportLabel('scope_all'),
        ];
    }

    protected function exportData(array $period, array $filters, array $sheets): array
    {
        $f = ['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()] + $filters;
        $people = $this->employeeRows($f);

        return [
            'people' => $people,
            'teams'  => $this->teamRows($f, $people, $this->targetsForMonths($period['months'], $f)),
        ];
    }

    protected function exportRows(string $sheet, array $data): array
    {
        return $sheet === 'teams'
            ? $this->teamExportRows($data['teams'])
            : $this->employeeExportRows($data['people'], $data['teams']);
    }

    protected function exportSheetMeta(string $sheet, array $data, array $period): array
    {
        return ['unit' => $this->exportLabel('unit'), 'notes' => [$this->exportLabel('note_credit')]];
    }

    protected function exportFileName(): string
    {
        return 'bao-cao-hieu-qua-sales';
    }

    public function exportYears(): array
    {
        return array_values(array_unique(array_map(fn ($m) => (int) substr($m, 0, 4), $this->months)));
    }

    protected function teamExportRows(array $teams): array
    {
        if (! $teams) {
            return [];
        }

        $cells = function (array $r) {
            $goal = fn ($k) => $r['target'][$k] ?? null;

            return [
                'members'        => $r['members'],
                'revenue'        => $r['revenue'],
                'target_revenue' => $goal('revenue'),
                'attain_revenue' => $goal('revenue') ? round($r['revenue'] * 100 / $goal('revenue'), 1) : null,
                'students'       => $r['students'],
                'target_students' => $goal('students'),
                'attain_students' => $goal('students') ? round($r['students'] * 100 / $goal('students'), 1) : null,
                'won'            => $r['won'],
                'lost'           => $r['lost'],
                'close_rate'     => $r['close_rate'],
                'avg_order'      => $r['avg_order'],
                'avg_close_days' => $r['avg_close_days'],
                'care'           => $r['care'],
                'appts'          => $r['appts'],
                'rank'           => $r['rank'] ?? null,
            ];
        };

        $sum = $this->report()->combine($teams) + ['members' => array_sum(array_column($teams, 'members'))];
        foreach (['revenue', 'students'] as $metric) {
            $goals = array_filter(array_map(fn ($t) => $t['target'][$metric] ?? null, $teams), fn ($v) => $v !== null);
            $sum['target'][$metric] = $goals ? array_sum($goals) : null;
        }

        $rows = [['total', ['team' => $this->exportLabel('total')] + $cells($sum)]];
        foreach ($teams as $r) {
            $rows[] = ['item', ['team' => $r['name']] + $cells($r)];
        }

        return $rows;
    }

    protected function employeeExportRows(array $people, array $teams): array
    {
        if (! $people) {
            return [];
        }

        $cells = fn (array $r, bool $person) => [
            'team'               => $person ? $r['team'] : null,
            'revenue'            => $r['revenue'],
            'share'              => $person ? $r['share'] : null,
            'won'                => $r['won'],
            'lost'               => $r['lost'],
            'close_rate'         => $r['close_rate'],
            'avg_order'          => $r['avg_order'],
            'students'           => $r['students'],
            'activity'           => $r['activity'],
            'care'               => $r['care'],
            'reach_rate'         => $r['reach_rate'],
            'appts'              => $r['appts'],
            'show_rate'          => $r['show_rate'],
            'avg_response_hours' => $r['avg_response_hours'],
            'avg_close_days'     => $r['avg_close_days'],
            'transfers_out'      => $r['transfers_out'],
            'rank'               => $person ? $r['rank'] : null,
        ];

        $groups = collect($people)->groupBy('team_id');
        $order = array_column($teams, 'id');
        $order = array_merge(
            array_values(array_intersect($order, $groups->keys()->all())),
            array_values(array_diff($groups->keys()->all(), $order))
        );

        $rows = [['total', ['employee' => $this->exportLabel('total')] + $cells($this->report()->combine($people), false)]];
        foreach ($order as $i => $teamId) {
            $members = $groups[$teamId]->all();
            $rows[] = ['group', ['_stt' => $this->roman($i + 1), 'employee' => $members[0]['team'] ?? $this->exportLabel('no_team')]
                + $cells($this->report()->combine($members), false)];
            foreach ($members as $r) {
                $rows[] = ['item', ['employee' => $r['name'].($r['resigned'] ? ' ('.__('reports.sales_performance.resigned').')' : '')]
                    + $cells($r, true)];
            }
        }

        return $rows;
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

    public function activeSort(): string
    {
        return isset(self::SORTS[$this->sort]) ? $this->sort : 'revenue';
    }

    public static function columns(): array
    {
        return array_merge(...array_values(self::COLUMN_GROUPS));
    }

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

    protected function compareWithTeam(array &$rows): void
    {
        $columns = self::columns();
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
            $combined = $this->report()->combine($members) + ['open' => array_sum(array_column($members, 'open'))];
            foreach ($columns as $col => [$direction, $kind]) {
                $value = $combined[$col];
                $references[$teamId][$col] = $kind === 'count' && $value !== null ? $value / count($members) : $value;
            }
        }

        foreach ($rows as &$row) {
            $row['compare'] = [];
            foreach ($columns as $col => [$direction]) {
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

    protected function filterTeams(array $filters): Collection
    {
        return match (true) {
            (bool) ($filters['employee'] ?? null) => collect(),
            ($filters['team'] ?? null) !== null    => $this->teams->where('team_id', $filters['team'])->values(),
            default                                => $this->teams,
        };
    }

    protected function salespeople(?string $team): Collection
    {
        return Employee::query()
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALESPERSON))
            ->when($team !== null, fn ($q) => $q->where('team_id', $team))
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'team_id', 'status']);
    }

    protected function targetsFor(string $month, array $filters): array
    {
        $teams = $this->filterTeams($filters);

        return $teams->isEmpty() ? [] : $this->report()->targets($month, $teams->pluck('team_id')->all());
    }

    protected function targetsForMonths(array $months, array $filters): array
    {
        $sum = [];
        foreach ($months as $month) {
            foreach ($this->targetsFor($month, $filters) as $team => $values) {
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

    protected function filterValues(string $month): array
    {
        $start = CarbonImmutable::parse($month.'-01');

        return $this->filterRange($start->toDateString(), $start->endOfMonth()->toDateString());
    }

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
