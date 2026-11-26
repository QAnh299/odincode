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

#[Layout('layouts.app')]
class SalesTeamPerformance extends SalesPerformance
{
    public const TABS = ['teams', 'employees'];

    public const SHARES = ['revenue', 'students'];

    public const EMP_SHARE_MAX = 10;

    public const EMP_OTHER_COLOR = '#c3cad5';

    public const EMP_TOP = 10;

    public const EMP_METRICS = ['revenue' => 'money', 'won' => 'count', 'close_rate' => 'percent', 'students' => 'count'];

    public const EMP_MIN_CLOSED = 5;

    public const PALETTE = [
        '#2a78d6', '#eb6834', '#16a34a', '#9333ea', '#0891b2', '#db2777',
        '#d99a0b', '#b91c1c', '#65a30d', '#92400e', '#475569', '#6366f1',
    ];

    public const STATUS_SERIES = [
        'won'       => ['status_won', '#1f9d4f', '#ffffff'],
        'progress'  => ['status_progress', '#7fb0ea', '#14213d'],
        'unreached' => ['status_unreached', '#eaa3a3', '#14213d'],
        'new'       => ['status_new', '#cfd4dc', '#14213d'],
    ];

    public const STATUS_COMPACT_TEAMS = 5;

    public const MAX_TREND_MONTHS = 12;

    #[Url(except: 'teams')]
    public string $tab = 'teams';

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

    #[Url(except: '')]
    public string $branch = '';

    #[Url(except: 'revenue')]
    public string $share = 'revenue';

    public string $empShare = 'revenue';

    public string $empMetric = 'revenue';

    public function updatedTab(): void
    {
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

    public function hasFilters(): bool
    {
        return parent::hasFilters() || [$this->from, $this->to] !== $this->presetRange($this->currentMonth()) || $this->branch !== '';
    }

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

    public function activePreset(): ?string
    {
        $month = $this->period['from']->format('Y-m');

        return $this->presetRange($month) === [$this->period['from']->toDateString(), $this->period['until']->toDateString()]
            ? $month : null;
    }

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

    #[Computed]
    public function activeMonth(): string
    {
        return $this->period['to']->format('Y-m');
    }

    protected function filterValues(string $month): array
    {
        return $this->filterRange($this->period['from']->toDateString(), $this->period['to']->toDateString());
    }

    #[Computed]
    public function previousTotals(): array
    {
        [$from, $to] = $this->period['previous'];

        return $this->report()->totals($this->filterRange($from->toDateString(), $to->toDateString()));
    }

    #[Computed]
    public function targets(): array
    {
        return $this->targetsForMonths($this->period['months'], $this->filterValues($this->activeMonth));
    }

    protected function rankTargets(array $filters): array
    {
        return $this->targetsForMonths($this->period['months'], $filters);
    }

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

    #[Computed]
    public function branches(): Collection
    {
        return Branch::query()->orderBy('branch_name')->get(['branch_id', 'branch_name']);
    }

    protected function branchValue(): ?string
    {
        return $this->branches->contains('branch_id', $this->branch) ? $this->branch : null;
    }

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

    #[Computed]
    public function uncontacted(): int
    {
        return $this->report()->uncontacted($this->filterValues($this->activeMonth));
    }

    #[Computed]
    public function teamMonthly(): array
    {
        return $this->report()->aggregate(
            $this->monthsFilter(array_column($this->trend, 'month'), $this->filterValues($this->activeMonth)),
            'team_month'
        );
    }

    #[Computed]
    public function seriesTeams(): Collection
    {
        $teams = $this->employee === '' ? $this->scopeTeams : collect();

        return $teams->count() > 1 && $teams->count() <= self::MAX_TREND_TEAMS ? $teams->values() : collect();
    }

    public function teamColor(string $teamId): string
    {
        $index = $this->scopeTeams->search(fn ($t) => $t->team_id === $teamId);

        return self::PALETTE[($index === false ? 0 : $index) % count(self::PALETTE)];
    }

    public function shortMoney(?float $value): string
    {
        $value = (float) $value;

        return match (true) {
            abs($value) >= 1e9 => number_format($value / 1e9, 2, ',', '.').' '.__('reports.sales_team.billion'),
            abs($value) >= 1e6 => number_format($value / 1e6, 1, ',', '.').' '.__('reports.sales_team.million'),
            default            => number_format($value, 0, ',', '.').' ₫',
        };
    }

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

    public const KPI_DRILLS = ['assigned' => 'assigned', 'uncontacted' => 'uncontacted', 'response' => 'response', 'contact_rate' => 'contacted'];

    #[Computed]
    public function kpis(): array
    {
        $t = $this->totals;
        $p = $this->previousTotals;
        $trend = $this->trend;
        $tk = fn ($key, $replace = []) => __('reports.sales_team.'.$key, $replace);
        $dec = fn ($v) => number_format((float) $v, 1, ',', '.');
        $num = fn ($v) => number_format((float) $v, 0, ',', '.');

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

        return array_map(fn ($card) => ['color' => LeadSources::TREND_COLORS[($card['delta'][0] ?? null) === 'down' ? 'down' : $card['color']]] + $card, $cards);
    }

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

    #[Computed]
    public function teamCharts(): array
    {
        return $this->drillCharts([
            'status'   => $this->statusChart(),
            'share'    => $this->shareChart(),
            'revenue'  => $this->revenueChart(),
            'win_rate' => $this->winRateChart(),
        ]);
    }

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

        $drills = array_map(fn ($s) => array_map(
            fn ($r) => ['kind' => $s[0]] + (isset($r['id']) ? ['team' => $r['id']] : []), $rows
        ), array_values(self::STATUS_SERIES));

        return ['type' => 'stacked_h', 'drills' => $drills, 'unit' => 'count', 'labels' => $labels, 'series' => $series,
            'legend' => 'top', 'htmlLegend' => true, 'fontSize' => 13, 'dataLabels' => 'inside', 'boldCategories' => true,
            'barMax' => count($rows) > self::STATUS_COMPACT_TEAMS ? 26 : 40, 'barPercent' => 0.8];
    }

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
            'drills'     => [array_map(fn ($t) => ['kind' => $metric, 'team' => $t['id']], $teams)],
            'htmlLegend' => true,
            'dataLabels' => 'percent',
            'cutout'     => '62%',
            'center'     => ['value' => $money ? $this->shortMoney($total) : number_format($total, 0, ',', '.'),
                'label' => __('reports.sales_team.share_total.'.$metric)],
            'legend'     => array_map(fn ($t, $c) => ['name' => $t['name'], 'color' => $c], $teams, $colors),
            'hasData'    => $total > 0,
        ];
    }

    protected function revenueChart(): array
    {
        $trend = $this->trend;
        $months = array_column($trend, 'month');

        $series = $this->teamSeries($months, fn () => $this->teamMonthly, 'revenue', 0.0)
            ?: [['label' => __('reports.sales_performance.metric.revenue'), 'color' => self::PALETTE[0], 'data' => array_column($trend, 'revenue')]];

        $targets = array_column($trend, 'target');
        $hasTarget = $this->employee === '' && array_sum($targets) > 0;

        $peak = max([0, ...array_column($trend, 'revenue'), ...($hasTarget ? $targets : [])]);
        [$divisor, $suffix] = $peak >= 1e9 ? [1e9, ' '.__('reports.sales_team.billion')] : [1e6, ' '.__('reports.sales_team.million')];

        if ($hasTarget) {
            $series[] = [
                'label' => __('reports.sales_performance.metric.target'), 'type' => 'line', 'dash' => true, 'color' => '#111827',
                'data' => $targets,
                'pointLabels' => array_map(fn ($v) => $v ? number_format($v / $divisor, 1, ',', '.') : '', $targets),
            ];
        }

        return ['type' => 'stacked', 'unit' => 'money', 'drills' => $this->teamDrills('revenue', $months, $this->seriesTeams), 'labels' => array_map(fn ($m) => $this->monthLabel($m), $months),
            'series' => $series, 'legend' => 'top', 'htmlLegend' => true, 'fontSize' => 13, 'divisor' => $divisor, 'suffix' => $suffix,
            'tickDecimals' => $divisor === 1e9 ? 1 : 0, 'maxTicks' => 8, 'barMax' => 72, 'barPercent' => 0.85];
    }

    protected function winRateChart(): array
    {
        $trend = $this->trend;
        $months = array_column($trend, 'month');

        $series = $this->teamSeries($months, fn () => $this->teamMonthly, 'close_rate', null)
            ?: [['label' => __('reports.sales_performance.metric.close_rate'), 'color' => self::PALETTE[0], 'data' => array_column($trend, 'close_rate')]];

        return ['type' => 'line', 'unit' => 'percent', 'drills' => $this->teamDrills('closed', $months, $this->seriesTeams), 'labels' => array_map(fn ($m) => $this->shortMonth($m), $months),
            'series' => $series, 'legend' => 'top', 'htmlLegend' => true, 'fontSize' => 13, 'endLabels' => true, 'spanGaps' => true, 'fit' => true,
            'straight' => true, 'maxTicks' => 11];
    }

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

    #[Computed]
    public function employeeCharts(): array
    {
        return $this->drillCharts([
            'share' => $this->employeeShareChart(),
            'top'   => $this->employeeTopChart(),
        ]);
    }

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
                    'color' => self::EMP_OTHER_COLOR, 'value' => array_sum(array_column($rest, $metric)),
                    'employees' => array_column($rest, 'id')];
                break;
            }
            $slices[] = ['name' => $person['name'], 'color' => self::PALETTE[$i % count(self::PALETTE)], 'value' => $person[$metric],
                'employees' => [$person['id']]];
        }

        $money = $metric === 'revenue';

        return [
            'type'       => 'doughnut',
            'unit'       => $money ? 'money' : 'count',
            'labels'     => array_column($slices, 'name'),
            'series'     => [['label' => __('reports.sales_team.share.'.$metric), 'data' => array_column($slices, 'value'),
                'colors' => array_column($slices, 'color')]],
            'drills'     => [array_map(fn ($s) => ['kind' => $metric, 'employees' => $s['employees']], $slices)],
            'htmlLegend' => true,
            'dataLabels' => 'percent',
            'cutout'     => '62%',
            'center'     => ['value' => $money ? $this->shortMoney($total) : number_format($total, 0, ',', '.'),
                'label' => __('reports.sales_team.share_total.'.$metric)],
            'legend'     => array_map(fn ($s) => ['name' => $s['name'], 'color' => $s['color']], $slices),
            'hasData'    => $total > 0,
        ];
    }

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
            'drills'     => [array_map(fn ($r) => ['kind' => self::COLUMN_DRILLS[$metric] ?? $metric, 'employees' => [$r['id']]], $people)],
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
