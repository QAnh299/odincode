<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\ExportPeriod;
use App\Livewire\Reports\Concerns\ReportDrill;
use App\Models\Branch;
use App\Models\CareResult;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\Role;
use App\Models\SalesTeam;
use App\Services\LeadConversionReport;
use App\Services\ReportDocumentWriter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class LeadConversion extends Component
{
    use ExportPeriod {
        openExport as protected openExportPeriod;
    }
    use ReportDrill;

    public const TABS = ['overview', 'sources', 'velocity', 'care', 'cohort'];

    public const PALETTE = LeadSources::PALETTE;

    public const COLORS = [
        'leads'            => '#2a78d6',
        'opportunities'    => '#6250d6',
        'won'              => '#1baf7a',
        'open'             => '#eda100',
        'lost'             => '#e34948',
        'archived'         => '#9ca3af',
        'new'              => '#c3cad5',
        'conversion_rate'  => '#6250d6',
        'win_rate'         => '#1baf7a',
        'close_rate'       => '#eda100',
        'Success'          => '#1baf7a',
        'Failed'           => '#e34948',
        'Unreachable'      => '#eda100',
    ];

    public const RESPONSE_BUCKETS = ['h1' => 1, 'h4' => 4, 'h24' => 24, 'd3' => 72, 'more' => INF];

    public const CLOSE_BUCKETS = ['d7' => 7, 'd14' => 14, 'd30' => 30, 'd60' => 60, 'more' => INF];

    public const CONTACT_BUCKETS = [0, 1, 2, 3, 4, 5];

    public const COHORT_ROWS = 12;

    public const COHORT_LINES = 6;

    public const STUCK_LIMIT = 50;

    public const COHORT_METRICS = ['won', 'converted', 'student'];

    public const PANELS = [
        'funnel'         => 'source',
        'share'          => 'share',
        'outcomes'       => 'method',
        'trend_count'    => 'period',
        'trend_rate'     => 'source',
        'scatter'        => 'rate',
        'rates'          => 'method',
        'methods'        => 'source',
        'response_count' => 'source',
        'response_rate'  => 'source',
        'close'          => 'source',
        'activities'     => 'source',
        'touches'        => 'source',
    ];

    public const EXPORT_SHEETS = ['summary', 'leads', 'care', 'appointments'];

    public const EXPORT_LOCKED = ['summary' => 'source', 'leads' => 'name', 'care' => 'at', 'appointments' => 'at'];

    public const EXPORT_PREVIEW_ROWS = 50;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $branch = '';

    #[Url(except: '')]
    public string $team = '';

    #[Url(except: '')]
    public string $employee = '';

    #[Url(except: 'won')]
    public string $cohortMetric = 'won';

    public array $chart = [];

    public string $exportBranch = '';

    public string $exportSource = '';

    public string $exportTeam = '';

    public string $exportEmployee = '';

    public array $exportSheets = [];

    public array $exportColumns = [];

    public string $exportTab = 'summary';

    public const FILTERS = ['from', 'to', 'branch', 'team', 'employee'];

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'overview';
    }

    public function updatedTeam(): void
    {
        $this->employee = '';
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

    public function activeTab(): string
    {
        return in_array($this->tab, self::TABS, true) ? $this->tab : 'overview';
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
    public function canExport(): bool
    {
        return in_array(Auth::user()->role_name, [Role::DIRECTOR, Role::SALE_ADMIN], true);
    }

    #[Computed]
    public function canFilterEmployee(): bool
    {
        return Auth::user()->role_name !== Role::SALESPERSON;
    }

    #[Computed]
    public function teams(): Collection
    {
        return SalesTeam::query()->with('leader:employee_id,branch_id')->orderBy('team_name')
            ->get(['team_id', 'team_name', 'team_leader_id']);
    }

    #[Computed]
    public function branchScope(): ?string
    {
        return $this->canFilterEmployee && $this->branches->contains('branch_id', $this->branch) ? $this->branch : null;
    }

    #[Computed]
    public function teamOptions(): Collection
    {
        if ($this->branchScope === null) {
            return $this->teams;
        }

        return $this->teams->filter(fn ($team) => $team->branchId() === $this->branchScope)->values();
    }

    public function updatedBranch(): void
    {
        unset($this->branchScope, $this->teamOptions, $this->teamScope, $this->employeeOptions);

        if ($this->team !== '' && ! $this->teamOptions->contains('team_id', $this->team)) {
            $this->team = '';
            unset($this->teamScope, $this->employeeOptions);
        }
        if ($this->employee !== '' && ! $this->employeeOptions->contains('employee_id', $this->employee)) {
            $this->employee = '';
        }
    }

    #[Computed]
    public function teamScope(): ?string
    {
        if ($this->canFilterTeam) {
            return $this->teamOptions->contains('team_id', $this->team) ? $this->team : '';
        }

        return $this->canFilterEmployee ? $this->me?->team_id : '';
    }

    #[Computed]
    public function employeeOptions(): Collection
    {
        if (! $this->canFilterEmployee) {
            return $this->me ? collect([$this->me]) : collect();
        }
        if ($this->teamScope === null) {
            return collect();
        }

        return Employee::query()
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALESPERSON))
            ->when($this->teamScope !== '', fn ($q) => $q->where('team_id', $this->teamScope))
            ->when($this->branchScope !== null, fn ($q) => $q->where('branch_id', $this->branchScope))
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'team_id', 'status']);
    }

    #[Computed]
    public function employeeScope(): ?string
    {
        if (! $this->canFilterEmployee) {
            return $this->me?->employee_id ?? '__none__';
        }

        return $this->employeeOptions->contains('employee_id', $this->employee) ? $this->employee : null;
    }

    #[Computed]
    public function seesAll(): bool
    {
        return $this->canFilterTeam && $this->teamScope === '' && $this->employeeScope === null;
    }

    #[Computed]
    public function branches(): Collection
    {
        return Branch::query()->orderBy('branch_id')->get(['branch_id', 'branch_name']);
    }

    #[Computed]
    public function sources(): array
    {
        return DB::table('Leads')->distinct()->orderBy('source_name')->pluck('source_name')->all();
    }

    #[Computed]
    public function facts(): array
    {
        return $this->report()->leads($this->filterValues());
    }

    #[Computed]
    public function summary(): array
    {
        return $this->report()->summarize($this->facts);
    }

    #[Computed]
    public function previous(): ?array
    {
        $filters = $this->filterValues();
        if (! $filters['from'] || ! $filters['to']) {
            return null;
        }

        $from = CarbonImmutable::parse($filters['from']);
        $to = CarbonImmutable::parse($filters['to']);
        $days = $from->diffInDays($to) + 1;
        $filters['to'] = $from->subDay()->toDateString();
        $filters['from'] = $from->subDays($days)->toDateString();

        return [
            'label'   => CarbonImmutable::parse($filters['from'])->format('d/m').'-'.CarbonImmutable::parse($filters['to'])->format('d/m/Y'),
            'summary' => $this->report()->summarize($this->report()->leads($filters)),
        ];
    }

    #[Computed]
    public function period(): string
    {
        $times = array_column($this->facts, 'created');
        if (! $times) {
            return 'month';
        }

        return $this->monthIndex(max($times)) - $this->monthIndex(min($times)) >= 3 ? 'month' : 'week';
    }

    #[Computed]
    public function overview(): array
    {
        $s = $this->report()->summarize($this->panelFacts('funnel'));
        $label = fn ($key) => __('reports.lead_conversion.overview.'.$key);

        $steps = [[$label($this->seesAll ? 'step_leads' : 'step_assigned'), $s['leads'], ['set' => 'all']]];
        if ($this->seesAll) {
            $steps[] = [$label('step_opportunities'), $s['opportunities'], ['set' => 'opportunities']];
        }
        foreach (array_slice($this->report()->funnelStages(), 1) as $stage) {
            $steps[] = [$stage['name'], $s['reached'][$stage['order']], ['reached' => $stage['order']]];
        }
        $steps[] = [$label('step_students'), $s['students'], ['set' => 'students']];

        $funnel = [];
        foreach ($steps as $i => [$name, $value, $drill]) {
            $funnel[] = [
                'drill'   => ['kind' => 'leads', 'panel' => 'funnel'] + $drill,
                'name'    => $name,
                'value'   => $value,
                'rate'    => $i ? LeadConversionReport::pct($value, $steps[$i - 1][1]) : null,
                'overall' => LeadConversionReport::pct($value, $s['leads']),
            ];
        }

        $metric = $this->panelValue('share');
        $bySource = [];
        foreach ($this->groupFacts(fn ($f) => $f['source']) as $source => $facts) {
            $bySource[(string) $source] = $this->report()->summarize($facts)[$metric];
        }
        arsort($bySource);
        $bySource = array_filter($bySource);
        $sources = array_map('strval', array_keys($bySource));
        $colors = $this->colorMap($this->sources, $sources);

        $o = $this->report()->summarize($this->panelFacts('outcomes'));
        $outcomes = array_values(array_filter(LeadConversionReport::OUTCOMES, fn ($key) => $o[$key] > 0));

        $trendPeriod = $this->panelValue('trend_count') ?: $this->period;
        [$countLabels, $countRows, $countKeys] = $this->trendRows($this->facts, $trendPeriod);
        [$rateLabels, $rateRows, $rateKeys] = $this->trendRows($this->panelFacts('trend_rate'), $this->period);

        $rateMetrics = $this->seesAll ? ['conversion_rate', 'win_rate'] : ['win_rate', 'close_rate'];

        return [
            'funnel'       => $funnel,
            'trend_period' => $trendPeriod,
            'charts'       => [
                'share'    => ['type' => 'pie', 'unit' => 'count', 'labels' => $sources,
                    'series' => [['label' => $this->shareLabel($metric), 'data' => array_values($bySource),
                        'colors' => array_map(fn ($source) => $colors[$source], $sources)]],
                    'drills' => [array_map(fn ($source) => ['kind' => 'leads', 'set' => self::METRIC_SETS[$metric], 'source' => $source], $sources)]],
                'outcomes' => ['type' => 'doughnut', 'unit' => 'count',
                    'labels' => array_map(fn ($key) => __('reports.lead_conversion.outcome.'.$key), $outcomes),
                    'series' => [['label' => $label('leads'), 'data' => array_map(fn ($key) => $o[$key], $outcomes),
                        'colors' => array_map(fn ($key) => self::COLORS[$key], $outcomes)]],
                    'drills' => [array_map(fn ($key) => ['kind' => 'leads', 'set' => $key, 'panel' => 'outcomes'], $outcomes)]],
                'trend_count' => ['type' => 'bar', 'unit' => 'count', 'labels' => $countLabels,
                    'series' => $this->series($countRows, ['leads', 'won']),
                    'drills' => $this->seriesDrills(['leads', 'won'], array_map(fn ($key) => ['period' => $key, 'ptype' => $trendPeriod], $countKeys))],
                'trend_rate' => ['type' => 'line', 'unit' => 'percent', 'labels' => $rateLabels,
                    'series' => $this->series($rateRows, $rateMetrics),
                    'drills' => $this->seriesDrills($rateMetrics, array_map(fn ($key) => ['period' => $key, 'ptype' => $this->period, 'panel' => 'trend_rate'], $rateKeys))],
            ],
        ];
    }

    protected function trendRows(array $facts, string $period): array
    {
        $groups = [];
        foreach ($facts as $f) {
            $groups[$this->periodKey($f['created'], $period)][] = $f;
        }
        $keys = $this->periodKeys(array_column($facts, 'created'), $period);

        return [
            array_map(fn ($key) => $this->periodLabel($key, $period), $keys),
            array_map(fn ($key) => $this->report()->summarize($groups[$key] ?? []), $keys),
            $keys,
        ];
    }

    #[Computed]
    public function bySource(): array
    {
        $rows = [];
        foreach ($this->groupFacts(fn ($f) => $f['source']) as $source => $facts) {
            $rows[] = $this->report()->summarize($facts) + ['name' => (string) $source];
        }
        usort($rows, fn ($a, $b) => [$b['leads'], $b['won']] <=> [$a['leads'], $a['won']]);

        $colors = $this->colorMap($this->sources, array_column($rows, 'name'));

        $rateMetrics = $this->seesAll ? ['conversion_rate', 'win_rate', 'close_rate'] : ['win_rate', 'close_rate'];

        $rateGroups = [];
        foreach ($this->panelFacts('rates') as $f) {
            $rateGroups[$f['source']][] = $f;
        }
        $rateRows = [];
        foreach ($rateGroups as $source => $facts) {
            $rateRows[] = $this->report()->summarize($facts) + ['name' => (string) $source];
        }
        usort($rateRows, fn ($a, $b) => $b['leads'] <=> $a['leads']);

        $methodGroups = [];
        foreach ($this->panelFacts('methods') as $f) {
            $methodGroups[$f['method']][] = $f;
        }
        $methodRows = [];
        foreach ($methodGroups as $method => $facts) {
            $methodRows[] = $this->report()->summarize($facts) + ['name' => __('leads.method.'.$method), 'key' => (string) $method];
        }
        usort($methodRows, fn ($a, $b) => $b['leads'] <=> $a['leads']);

        $yMetric = $this->panelValue('scatter');

        return [
            'rows'    => $rows,
            'total'   => $this->summary,
            'charts'  => [
                'scatter' => ['type' => 'scatter', 'unit' => 'percent', 'xUnit' => 'count',
                    'xLabel' => __('reports.lead_conversion.metric.leads'),
                    'yLabel' => __('reports.lead_conversion.metric.'.$yMetric),
                    'labels' => [],
                    'series' => array_map(fn ($r) => ['label' => $r['name'], 'color' => $colors[$r['name']],
                        'data' => [['x' => $r['leads'], 'y' => $r[$yMetric] ?? 0]]], $rows),
                    'drills' => array_map(fn ($r) => [['kind' => 'leads', 'source' => $r['name']]], $rows)],
                'rates'   => ['type' => 'bar', 'unit' => 'percent', 'labels' => array_column($rateRows, 'name'),
                    'series' => $this->series($rateRows, $rateMetrics),
                    'drills' => $this->seriesDrills($rateMetrics, array_map(fn ($r) => ['source' => $r['name'], 'panel' => 'rates'], $rateRows))],
                'methods' => ['type' => 'bar', 'unit' => 'percent', 'labels' => array_column($methodRows, 'name'),
                    'series' => $this->series($methodRows, $rateMetrics),
                    'drills' => $this->seriesDrills($rateMetrics, array_map(fn ($r) => ['method' => $r['key'], 'panel' => 'methods'], $methodRows))],
            ],
        ];
    }

    #[Computed]
    public function velocity(): array
    {
        $facts = $this->facts;
        $stages = $this->report()->funnelStages();
        $label = fn ($key, $params = []) => __('reports.lead_conversion.velocity.'.$key, $params);
        $hours = fn ($a, $b) => $a !== null && $b !== null ? max(0, $b - $a) / 3600 : null;

        $steps = [['key' => 'response', 'name' => $label('step_response'),
            'values' => array_map(fn ($f) => $hours($f['created'], $f['first_contact']), $facts)]];
        if ($this->seesAll) {
            $steps[] = ['key' => 'assign', 'name' => $label('step_assign'),
                'values' => array_map(fn ($f) => $hours($f['created'], $f['converted_at']), $facts)];
        }
        foreach (array_slice($stages, 1) as $i => $stage) {
            $from = $stages[$i];
            $steps[] = ['key' => 'stage_'.$stage['order'], 'name' => $from['name'].' → '.$stage['name'],
                'values' => array_map(fn ($f) => $hours($f['entries'][$from['order']] ?? null, $f['entries'][$stage['order']] ?? null), $facts)];
        }
        $steps[] = ['key' => 'total', 'name' => $label('step_total'),
            'values' => array_map(fn ($f) => $hours($f['created'], $f['won_at']), $facts)];

        foreach ($steps as &$step) {
            $values = array_values(array_filter($step['values'], fn ($v) => $v !== null));
            $step += [
                'count'  => count($values),
                'avg'    => LeadConversionReport::avg($values),
                'median' => LeadConversionReport::percentile($values, 50),
                'p90'    => LeadConversionReport::percentile($values, 90),
            ];
            unset($step['values']);
        }
        unset($step);

        $assigned = array_values(array_filter($facts, fn ($f) => $f['outcome'] !== LeadConversionReport::OUTCOME_NEW));
        $within24 = count(array_filter($assigned, fn ($f) => ($h = $hours($f['created'], $f['first_contact'])) !== null && $h <= 24));
        $stuck = $this->report()->stuck($facts);
        $countRows = $this->responseRows($this->panelFacts('response_count'));
        $rateRows = $this->responseRows($this->panelFacts('response_rate'));
        $open = count(array_filter($facts, fn ($f) => $f['outcome'] === LeadConversionReport::OUTCOME_OPEN));
        $untouched = count(array_filter($facts, fn ($f) => $f['outcome'] === LeadConversionReport::OUTCOME_OPEN && ! $f['first_contact']));

        return [
            'steps'     => $steps,
            'within24'  => LeadConversionReport::pct($within24, count($assigned)),
            'assigned'  => count($assigned),
            'open'      => $open,
            'untouched' => $untouched,
            'stuck'     => array_slice($stuck, 0, self::STUCK_LIMIT),
            'stuck_count' => count($stuck),
            'charts'    => [
                'response_count' => ['type' => 'bar', 'unit' => 'count', 'labels' => array_column($countRows, 'name'),
                    'series' => [['label' => __('reports.lead_conversion.metric.leads'), 'color' => self::COLORS['leads'],
                        'data' => array_column($countRows, 'leads')]],
                    'drills' => [array_map(fn ($r) => ['kind' => 'leads', 'response' => $r['key'], 'panel' => 'response_count'], $countRows)]],
                'response_rate'  => ['type' => 'bar', 'unit' => 'percent', 'labels' => array_column($rateRows, 'name'),
                    'series' => $this->series($rateRows, ['win_rate']),
                    'drills' => [array_map(fn ($r) => ['kind' => 'leads', 'set' => 'won', 'response' => $r['key'], 'panel' => 'response_rate'], $rateRows)]],
                'close'          => ['type' => 'bar', 'unit' => 'count',
                    'labels' => array_map(fn ($k) => $label('close.'.$k), array_keys(self::CLOSE_BUCKETS)),
                    'series' => [['label' => __('reports.lead_conversion.metric.won'), 'color' => self::COLORS['won'],
                        'data' => array_values($this->closeCounts($this->panelFacts('close')))]],
                    'drills' => [array_map(fn ($k) => ['kind' => 'leads', 'set' => 'won', 'close' => $k, 'panel' => 'close'], array_keys(self::CLOSE_BUCKETS))]],
            ],
        ];
    }

    protected function responseRows(array $facts): array
    {
        $groups = array_fill_keys(array_merge(array_keys(self::RESPONSE_BUCKETS), ['none']), []);
        foreach ($facts as $f) {
            if ($f['outcome'] === LeadConversionReport::OUTCOME_NEW) {
                continue;
            }
            $key = $f['first_contact']
                ? $this->bucket(max(0, $f['first_contact'] - $f['created']) / 3600, self::RESPONSE_BUCKETS)
                : 'none';
            $groups[$key][] = $f;
        }

        $rows = [];
        foreach ($groups as $key => $group) {
            $rows[] = $this->report()->summarize($group) + ['name' => __('reports.lead_conversion.velocity.response.'.$key), 'key' => $key];
        }

        return $rows;
    }

    protected function closeCounts(array $facts): array
    {
        $counts = array_fill_keys(array_keys(self::CLOSE_BUCKETS), 0);
        foreach ($facts as $f) {
            if ($f['won_at']) {
                $counts[$this->bucket(max(0, $f['won_at'] - $f['created']) / 86400, self::CLOSE_BUCKETS)]++;
            }
        }

        return $counts;
    }

    #[Computed]
    public function care(): array
    {
        $facts = $this->facts;
        $ids = array_merge(...array_column($facts, 'opportunities') ?: [[]]);
        $contacts = $this->report()->contacts($ids);
        $appointments = $this->report()->appointments($ids);
        $results = CareResult::RESULTS;

        $activities = $this->activityRows($contacts);
        $touches = $this->touchRows($this->panelFacts('touches'));

        $panelIds = array_flip(array_merge(...array_column($this->panelFacts('activities'), 'opportunities') ?: [[]]));
        $activityChart = $this->activityRows(array_filter($contacts, fn ($c) => isset($panelIds[$c['opportunity']])));

        $types = [];
        foreach ($appointments as $ap) {
            $types[$ap['type']] ??= ['total' => 0, 'Success' => 0, 'Failed' => 0, 'Scheduled' => 0];
            $types[$ap['type']]['total']++;
            $types[$ap['type']][$ap['status']] = ($types[$ap['type']][$ap['status']] ?? 0) + 1;
        }
        foreach ($types as &$t) {
            $t['rate'] = LeadConversionReport::pct($t['Success'], $t['Success'] + $t['Failed']);
        }
        unset($t);

        $heat = [];
        $hours = [];
        foreach ($contacts as $c) {
            $day = (int) date('N', $c['at']);
            $hour = (int) date('G', $c['at']);
            $hours[] = $hour;
            $heat[$day][$hour]['total'] = ($heat[$day][$hour]['total'] ?? 0) + 1;
            $heat[$day][$hour]['ok'] = ($heat[$day][$hour]['ok'] ?? 0) + (int) ($c['result'] === 'Success');
        }
        $hourRange = $hours ? range(min(min($hours), 8), max(max($hours), 20)) : range(8, 20);
        $maxCell = 0;
        foreach ($heat as $day => $cells) {
            foreach ($cells as $hour => $cell) {
                $heat[$day][$hour]['rate'] = LeadConversionReport::pct($cell['ok'], $cell['total']);
                $maxCell = max($maxCell, $cell['total']);
            }
        }

        $byOutcome = fn (array $outcomes) => LeadConversionReport::avg(array_column(
            array_filter($facts, fn ($f) => in_array($f['outcome'], $outcomes, true)),
            'contacts'
        ));
        $total = count($contacts);
        $countResult = fn ($r) => count(array_filter($contacts, fn ($c) => $c['result'] === $r));

        return [
            'total'        => $total,
            'success_rate' => LeadConversionReport::pct($countResult('Success'), $total),
            'unreachable_rate' => LeadConversionReport::pct($countResult('Unreachable'), $total),
            'per_won'      => $byOutcome([LeadConversionReport::OUTCOME_WON]),
            'per_lost'     => $byOutcome([LeadConversionReport::OUTCOME_LOST, LeadConversionReport::OUTCOME_ARCHIVED]),
            'appointment_rate' => $this->summary['appointment_rate'],
            'activities'   => $activities,
            'touches'      => $touches,
            'types'        => $types,
            'heat'         => $heat,
            'heat_hours'   => $hourRange,
            'heat_max'     => $maxCell,
            'charts'       => [
                'activities' => ['type' => 'stacked_h', 'unit' => 'percent', 'labels' => array_column($activityChart, 'name'),
                    'series' => array_map(fn ($r) => ['label' => __('reports.lead_conversion.care.result.'.$r), 'color' => self::COLORS[$r],
                        'data' => array_map(fn ($a) => $a['rate'][$r] ?? 0, $activityChart)], $results),
                    'drills' => array_map(fn ($r) => array_map(fn ($a) => ['kind' => 'contacts', 'activity' => $a['id'], 'result' => $r, 'panel' => 'activities'], $activityChart), $results)],
                'touches'    => ['type' => 'bar', 'unit' => 'percent',
                    'labels' => array_map(fn ($t) => $t['name'].' (n='.$t['leads'].')', $touches),
                    'series' => $this->series($touches, ['close_rate']),
                    'drills' => [array_map(fn ($t) => ['kind' => 'leads', 'set' => 'closed', 'touches' => $t['n'], 'panel' => 'touches'], $touches)]],
            ],
        ];
    }

    protected function activityRows(array $contacts): array
    {
        $results = CareResult::RESULTS;
        $activities = [];
        foreach (DB::table('CareActivities')->orderBy('activity_id')->pluck('activity_name', 'activity_id') as $id => $name) {
            $activities[$id] = ['id' => (string) $id, 'name' => $name, 'total' => 0] + array_fill_keys($results, 0);
        }
        foreach ($contacts as $c) {
            $activities[$c['activity']] ??= ['id' => (string) $c['activity'], 'name' => $c['activity'], 'total' => 0] + array_fill_keys($results, 0);
            $activities[$c['activity']]['total']++;
            $activities[$c['activity']][$c['result']] = ($activities[$c['activity']][$c['result']] ?? 0) + 1;
        }
        foreach ($activities as &$a) {
            foreach ($results as $r) {
                $a['rate'][$r] = LeadConversionReport::pct($a[$r], $a['total']);
            }
        }
        unset($a);

        return array_values(array_filter($activities, fn ($a) => $a['total'] > 0));
    }

    protected function touchRows(array $facts): array
    {
        $closed = [LeadConversionReport::OUTCOME_WON, LeadConversionReport::OUTCOME_LOST, LeadConversionReport::OUTCOME_ARCHIVED];
        $last = self::CONTACT_BUCKETS[array_key_last(self::CONTACT_BUCKETS)];
        $groups = array_fill_keys(self::CONTACT_BUCKETS, []);
        foreach ($facts as $f) {
            if (in_array($f['outcome'], $closed, true)) {
                $groups[min($f['contacts'], $last)][] = $f;
            }
        }

        $rows = [];
        foreach (array_filter($groups) as $n => $group) {
            $rows[] = $this->report()->summarize($group)
                + ['name' => __('reports.lead_conversion.care.touches_'.($n === $last ? 'more' : 'n'), ['n' => $n]), 'n' => $n];
        }

        return $rows;
    }

    public function activeCohortMetric(): string
    {
        $metric = in_array($this->cohortMetric, self::COHORT_METRICS, true) ? $this->cohortMetric : 'won';

        return $metric === 'converted' && ! $this->seesAll ? 'won' : $metric;
    }

    #[Computed]
    public function cohort(): array
    {
        $metric = $this->activeCohortMetric();
        $now = $this->monthIndex(time());

        $cohorts = [];
        foreach ($this->facts as $f) {
            $cohorts[$this->monthIndex($f['created'])][] = $f;
        }
        ksort($cohorts);
        $cohorts = array_slice($cohorts, -self::COHORT_ROWS, null, true);

        $width = $cohorts ? min(self::COHORT_ROWS - 1, $now - array_key_first($cohorts)) : 0;
        $eventAt = fn ($f) => match ($metric) {
            'converted' => $f['converted_at'],
            'student'   => $f['student'] ? ($f['won_at'] ?? $f['converted_at']) : null,
            default     => $f['won_at'],
        };

        $rows = [];
        foreach ($cohorts as $month => $facts) {
            $offsets = [];
            foreach ($facts as $f) {
                if ($at = $eventAt($f)) {
                    $offsets[] = max(0, $this->monthIndex($at) - $month);
                }
            }
            $cells = [];
            for ($m = 0; $m <= $width; $m++) {
                $cells[$m] = $month + $m > $now
                    ? null
                    : LeadConversionReport::pct(count(array_filter($offsets, fn ($o) => $o <= $m)), count($facts));
            }
            $rows[] = [
                'month' => $month,
                'label' => sprintf('%02d/%d', $month % 12 + 1, intdiv($month, 12)),
                'leads' => count($facts),
                'total' => LeadConversionReport::pct(count($offsets), count($facts)),
                'cells' => $cells,
            ];
        }

        $lines = array_slice($rows, -self::COHORT_LINES);

        return [
            'metric' => $metric,
            'width'  => $width,
            'rows'   => $rows,
            'chart'  => ['type' => 'line', 'unit' => 'percent', 'labels' => array_map(fn ($m) => 'M'.$m, range(0, $width)),
                'series' => array_map(fn ($r, $i) => ['label' => $r['label'], 'color' => self::PALETTE[$i % count(self::PALETTE)],
                    'data' => array_values($r['cells'])], $lines, array_keys($lines)),
                'drills' => array_map(fn ($r) => array_map(fn ($m) => ['kind' => 'leads', 'set' => 'cohort', 'cohort' => $r['month'], 'offset' => $m],
                    array_keys($r['cells'])), array_values($lines))],
        ];
    }

    public const DRILL_SETS = [
        'all', 'opportunities', 'contacted', 'won', 'open', 'lost', 'archived', 'new', 'failed', 'closed',
        'students', 'within24', 'stuck', 'untouched', 'cohort',
    ];

    public const METRIC_SETS = [
        'leads' => 'all', 'opportunities' => 'opportunities', 'conversion_rate' => 'opportunities', 'contact_rate' => 'contacted',
        'won' => 'won', 'win_rate' => 'won', 'close_rate' => 'closed', 'students' => 'students',
    ];

    protected function drillUrl(string $kind, array $params): ?string
    {
        $set = $params['set'] ?? 'all';
        if ($kind !== 'leads' || ! $this->seesAll || ! in_array($set, ['all', 'opportunities', 'won', 'students'], true)) {
            return null;
        }
        foreach (['step', 'response', 'close', 'touches', 'reached', 'offset'] as $key) {
            if (isset($params[$key])) {
                return null;
            }
        }

        $panel = $this->drillText($params, 'panel');
        if ($panel !== null) {
            $type = self::PANELS[$panel] ?? null;
            if (! in_array($type, ['source', 'method'], true)) {
                return null;
            }
            $value = $this->panelValue($panel);
            if ($value !== '') {
                if (($params[$type] ?? $value) !== $value) {
                    return null;
                }
                $params[$type] = $value;
            }
        }

        $f = $this->filterValues();
        [$from, $to] = [$f['from'], $f['to']];
        $period = $this->drillText($params, 'period');
        $cohort = is_int($params['cohort'] ?? null) ? $params['cohort'] : null;
        if ($cohort !== null) {
            [$from, $to] = $this->drillMonthRange(sprintf('%d-%02d', intdiv($cohort, 12), $cohort % 12 + 1), $from, $to);
        } elseif ($period !== null) {
            $type = in_array($params['ptype'] ?? null, ['month', 'week'], true) ? $params['ptype'] : $this->period;
            if ($type === 'month' && preg_match('/^\d{4}-\d{2}$/', $period)) {
                [$from, $to] = $this->drillMonthRange($period, $from, $to);
            } elseif ($type === 'week' && preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
                $start = CarbonImmutable::now()->setISODate((int) $m[1], (int) $m[2])->startOfWeek();
                $from = max($start->toDateString(), $from ?? '');
                $to = min($start->endOfWeek()->toDateString(), $to ?? '9999-12-31');
            } else {
                return null;
            }
        }

        $source = $this->drillText($params, 'source');
        $method = $this->drillText($params, 'method');

        if ($set === 'students') {
            return $this->listUrlFor('students', ['source' => $source, 'method' => $method, 'branch' => $f['branch'], 'lead_from' => $from, 'lead_to' => $to]);
        }

        return $this->listUrlFor('leads', [
            'status' => $set === 'all' ? null : Lead::STATUS_CONVERTED,
            'source' => $source,
            'method' => $method,
            'branch' => $f['branch'],
            'from'   => $from,
            'to'     => $to,
        ]);
    }

    protected function drillKinds(): array
    {
        $event = ['performed_at' => 'date', 'customer' => 'text', 'source' => 'text', 'employee' => 'text',
            'activity' => 'text', 'result' => 'text', 'notes' => 'text'];

        return [
            'leads' => ['customer' => 'text', 'created' => 'date', 'source' => 'text', 'method' => 'text', 'employee' => 'text',
                'outcome' => 'text', 'stage' => 'text', 'contacts' => 'int', 'response_hours' => 'decimal', 'close_days' => 'decimal'],
            'contacts'     => $event,
            'appointments' => $event,
        ];
    }

    protected function seriesDrills(array $metrics, array $points): array
    {
        return array_map(fn ($m) => array_map(fn ($p) => ['kind' => 'leads', 'set' => self::METRIC_SETS[$m] ?? 'all'] + $p, $points), $metrics);
    }

    protected function drillList(string $kind, array $params): array
    {
        $t = fn ($key, $replace = []) => __('reports.lead_conversion.'.$key, $replace);
        $int = fn ($key) => is_int($params[$key] ?? null) ? $params[$key] : null;
        $hours = fn ($a, $b) => $a !== null && $b !== null ? max(0, $b - $a) / 3600 : null;
        $scope = [$this->scopeLabel()];

        $panel = $this->drillText($params, 'panel');
        $panel = isset(self::PANELS[$panel ?? '']) ? $panel : null;
        $facts = $panel ? $this->panelFacts($panel) : $this->facts;
        if ($panel && in_array(self::PANELS[$panel], ['source', 'method'], true) && ($value = $this->panelValue($panel)) !== '') {
            $scope[] = self::PANELS[$panel] === 'method' ? __('leads.method.'.$value) : $value;
        }
        $keep = function (callable $test) use (&$facts) {
            $facts = array_values(array_filter($facts, $test));
        };

        if (($source = $this->drillText($params, 'source')) !== null) {
            $keep(fn ($f) => $f['source'] === $source);
            $scope[] = $source;
        }
        if (($method = $this->drillText($params, 'method')) !== null) {
            $keep(fn ($f) => $f['method'] === $method);
            $scope[] = __('leads.method.'.$method);
        }
        if (($period = $this->drillText($params, 'period')) !== null) {
            $type = in_array($params['ptype'] ?? null, ['month', 'week'], true) ? $params['ptype'] : $this->period;
            $keep(fn ($f) => $this->periodKey($f['created'], $type) === $period);
            $scope[] = $this->periodLabel($period, $type);
        }
        if (($cohort = $int('cohort')) !== null) {
            $keep(fn ($f) => $this->monthIndex($f['created']) === $cohort);
            $scope[] = $t('cohort.month').' '.sprintf('%02d/%d', $cohort % 12 + 1, intdiv($cohort, 12));
        }
        if (($reached = $int('reached')) !== null) {
            $keep(fn ($f) => $f['reached'] >= $reached);
            $scope[] = collect($this->report()->funnelStages())->firstWhere('order', $reached)['name'] ?? null;
        }
        $response = $this->drillText($params, 'response');
        if ($response !== null && ($response === 'none' || isset(self::RESPONSE_BUCKETS[$response]))) {
            $keep(fn ($f) => $f['outcome'] !== LeadConversionReport::OUTCOME_NEW && ($f['first_contact']
                ? $this->bucket(max(0, $f['first_contact'] - $f['created']) / 3600, self::RESPONSE_BUCKETS) : 'none') === $response);
            $scope[] = $t('velocity.chart.response_count').': '.$t('velocity.response.'.$response);
        }
        if (isset(self::CLOSE_BUCKETS[$close = $this->drillText($params, 'close') ?? ''])) {
            $keep(fn ($f) => $f['won_at'] && $this->bucket(max(0, $f['won_at'] - $f['created']) / 86400, self::CLOSE_BUCKETS) === $close);
            $scope[] = $t('velocity.close.'.$close);
        }
        if (($touches = $int('touches')) !== null) {
            $last = self::CONTACT_BUCKETS[array_key_last(self::CONTACT_BUCKETS)];
            $keep(fn ($f) => min($f['contacts'], $last) === $touches);
            $scope[] = $t('care.touches_'.($touches === $last ? 'more' : 'n'), ['n' => $touches]);
        }
        $title = null;
        if (($step = $this->drillText($params, 'step')) !== null) {
            $stages = $this->report()->funnelStages();
            $value = match (true) {
                $step === 'response' => fn ($f) => $hours($f['created'], $f['first_contact']),
                $step === 'assign'   => fn ($f) => $hours($f['created'], $f['converted_at']),
                $step === 'total'    => fn ($f) => $hours($f['created'], $f['won_at']),
                (bool) preg_match('/^stage_(\d+)$/', $step, $m) => function ($f) use ($m, $stages, $hours) {
                    $i = array_search((int) $m[1], array_column($stages, 'order'), true);

                    return $i ? $hours($f['entries'][$stages[$i - 1]['order']] ?? null, $f['entries'][$stages[$i]['order']] ?? null) : null;
                },
                default => fn () => null,
            };
            $keep(fn ($f) => $value($f) !== null);
            $title = collect($this->velocity['steps'])->firstWhere('key', $step)['name'] ?? null;
        }

        $set = in_array($params['set'] ?? null, self::DRILL_SETS, true) ? $params['set'] : 'all';
        $stuck = $set === 'stuck' ? array_flip(array_column($this->report()->stuck($facts), 'id')) : [];
        $offset = $int('offset');
        $keep(fn ($f) => $this->inDrillSet($f, $set, $stuck, $offset));
        if ($set === 'cohort') {
            $scope[] = $t('cohort.metric_'.$this->activeCohortMetric()).($offset !== null ? ' · M'.$offset : '');
        }

        if ($kind === 'leads') {
            usort($facts, fn ($a, $b) => $b['created'] <=> $a['created']);

            return [array_map(fn ($f) => [
                '_lead'          => $f['id'],
                '_opportunity'   => $f['opportunity'],
                'customer'       => $f['name'],
                'created'        => date('Y-m-d H:i:s', $f['created']),
                'source'         => $f['source'],
                'method'         => __('leads.method.'.$f['method']),
                'employee'       => $f['employee_name'],
                'outcome'        => $t('outcome.'.$f['outcome']),
                'stage'          => $f['stage_name'],
                'contacts'       => $f['contacts'],
                'response_hours' => $f['first_contact'] ? round(max(0, $f['first_contact'] - $f['created']) / 3600, 1) : null,
                'close_days'     => $f['won_at'] ? round(max(0, $f['won_at'] - $f['created']) / 86400, 1) : null,
            ], $facts), $scope, $title ?? $t('drill.set.'.$set)];
        }

        $leads = $this->leadsByOpportunity($facts);
        $ids = array_keys($leads);
        if ($kind === 'contacts') {
            $activity = $this->drillText($params, 'activity');
            $result = $this->drillText($params, 'result');
            [$day, $hour] = [$int('day'), $int('hour')];
            $events = array_filter($this->report()->contacts($ids), fn ($c) => ($activity === null || $c['activity'] === $activity)
                && ($result === null || $c['result'] === $result)
                && ($day === null || (int) date('N', $c['at']) === $day)
                && ($hour === null || (int) date('G', $c['at']) === $hour));
            $names = DB::table('CareActivities')->pluck('activity_name', 'activity_id');
            $scope[] = $activity !== null ? $names[$activity] ?? $activity : null;
            $scope[] = $result !== null ? $t('care.result.'.$result) : null;
            $scope[] = $day !== null ? $t('weekday.'.$day).($hour !== null ? ' '.$hour.'h' : '') : null;
            $label = fn ($e) => [$names[$e['activity']] ?? $e['activity'], $t('care.result.'.$e['result'])];
        } else {
            $type = $this->drillText($params, 'type');
            $status = $this->drillText($params, 'status');
            $events = array_filter($this->report()->appointments($ids), fn ($a) => ($type === null || $a['type'] === $type)
                && match ($status) {
                    null    => true,
                    'done'  => in_array($a['status'], ['Success', 'Failed'], true),
                    default => $a['status'] === $status,
                });
            $scope[] = $type !== null ? __('opportunities.appointment_type_name.'.$type) : null;
            $scope[] = $status !== null && $status !== 'done' ? __('appointments.status.'.$status) : null;
            $label = fn ($e) => [__('opportunities.appointment_type_name.'.$e['type']), __('appointments.status.'.$e['status'])];
        }
        usort($events, fn ($a, $b) => $b['at'] <=> $a['at']);
        $people = $this->employeeNames(array_column($events, 'employee'));

        return [array_map(function ($e) use ($leads, $people, $label) {
            $lead = $leads[$e['opportunity']] ?? [];
            [$activity, $result] = $label($e);

            return [
                '_lead'        => $lead['id'] ?? null,
                '_opportunity' => $e['opportunity'],
                'performed_at' => date('Y-m-d H:i:s', $e['at']),
                'customer'     => $lead['name'] ?? null,
                'source'       => $lead['source'] ?? null,
                'employee'     => $people[$e['employee']] ?? $e['employee'],
                'activity'     => $activity,
                'result'       => $result,
                'notes'        => $e['notes'],
            ];
        }, $events), $scope, $t('drill.'.$kind)];
    }

    protected function inDrillSet(array $f, string $set, array $stuck, ?int $offset): bool
    {
        $outcome = $f['outcome'];

        return match ($set) {
            'opportunities' => $outcome !== LeadConversionReport::OUTCOME_NEW,
            'contacted'     => (bool) $f['first_contact'],
            'failed'        => in_array($outcome, [LeadConversionReport::OUTCOME_LOST, LeadConversionReport::OUTCOME_ARCHIVED], true),
            'closed'        => in_array($outcome, [LeadConversionReport::OUTCOME_WON, LeadConversionReport::OUTCOME_LOST, LeadConversionReport::OUTCOME_ARCHIVED], true),
            'students'      => (bool) $f['student'],
            'within24'      => $outcome !== LeadConversionReport::OUTCOME_NEW && $f['first_contact'] && $f['first_contact'] - $f['created'] <= 86400,
            'stuck'         => isset($stuck[$f['id']]),
            'untouched'     => $outcome === LeadConversionReport::OUTCOME_OPEN && ! $f['first_contact'],
            'cohort'        => ($at = match ($this->activeCohortMetric()) {
                'converted' => $f['converted_at'],
                'student'   => $f['student'] ? ($f['won_at'] ?? $f['converted_at']) : null,
                default     => $f['won_at'],
            }) !== null && ($offset === null || max(0, $this->monthIndex($at) - $this->monthIndex($f['created'])) <= $offset),
            'all'           => true,
            default         => $outcome === $set,
        };
    }

    public function openExport(): void
    {
        $this->openExportPeriod();
        $this->reset('exportBranch', 'exportSource', 'exportTeam', 'exportEmployee');
        $this->exportTab = self::EXPORT_SHEETS[0];
        $this->exportSheets = array_fill_keys(self::EXPORT_SHEETS, true);
        $this->exportColumns = [];
        foreach (self::EXPORT_SHEETS as $sheet) {
            $this->setExportColumns($sheet, array_keys($this->exportColumnCatalog()[$sheet]));
        }
    }

    public function allExportColumns(): void
    {
        $tab = $this->activeExportTab();
        $this->setExportColumns($tab, array_keys($this->exportColumnCatalog()[$tab]));
    }

    protected function setExportColumns(string $sheet, array $on): void
    {
        $columns = array_keys($this->exportColumnCatalog()[$sheet]);
        $this->exportColumns[$sheet] = array_combine($columns, array_map(fn ($c) => in_array($c, $on, true), $columns));
    }

    public function exportMonths(): array
    {
        $now = CarbonImmutable::now();

        return range(1, $this->exportYear === (int) $now->year ? (int) $now->month : 12);
    }

    public function updatedExportYear(): void
    {
        $this->exportMonth = min($this->exportMonth, max($this->exportMonths()));
    }

    #[Computed]
    public function exportTeamOptions(): Collection
    {
        $branch = $this->branches->contains('branch_id', $this->exportBranch) ? $this->exportBranch : null;

        return $branch === null ? $this->teams : $this->teams->filter(fn ($team) => $team->branchId() === $branch)->values();
    }

    #[Computed]
    public function exportEmployeeOptions(): Collection
    {
        $team = $this->exportTeamOptions->contains('team_id', $this->exportTeam) ? $this->exportTeam : null;
        $branch = $this->branches->contains('branch_id', $this->exportBranch) ? $this->exportBranch : null;

        return Employee::query()
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALESPERSON))
            ->when($team, fn ($q) => $q->where('team_id', $team))
            ->when($branch, fn ($q) => $q->where('branch_id', $branch))
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'team_id', 'status']);
    }

    public function updatedExportBranch(): void
    {
        unset($this->exportTeamOptions, $this->exportEmployeeOptions);
        if (! $this->exportTeamOptions->contains('team_id', $this->exportTeam)) {
            $this->exportTeam = '';
        }
        if (! $this->exportEmployeeOptions->contains('employee_id', $this->exportEmployee)) {
            $this->exportEmployee = '';
        }
    }

    public function updatedExportTeam(): void
    {
        $this->exportEmployee = '';
    }

    public function exportYears(): array
    {
        $first = DB::table('Leads')->min('created_at');
        $now = (int) date('Y');
        $start = $first ? min($now, (int) substr($first, 0, 4)) : $now;

        return range($now, $start);
    }

    public function exportColumnCatalog(): array
    {
        return [
            'summary' => [
                'source' => 'label', 'leads' => 'int', 'opportunities' => 'int', 'conversion_rate' => 'percent',
                'contact_rate' => 'percent', 'appointment_rate' => 'percent', 'open' => 'int', 'won' => 'int', 'lost' => 'int',
                'archived' => 'int', 'win_rate' => 'percent', 'close_rate' => 'percent', 'students' => 'int',
                'response_median' => 'decimal', 'close_median' => 'decimal',
            ],
            'leads' => [
                'id' => 'text', 'name' => 'label', 'created' => 'text', 'source' => 'text', 'method' => 'text',
                'branch' => 'text', 'owner' => 'text', 'team' => 'text', 'opportunity' => 'text', 'outcome' => 'text',
                'stage' => 'text', 'stage_since' => 'text', 'days_in_stage' => 'decimal', 'stuck' => 'text',
                'assigned' => 'text', 'first_contact' => 'text', 'response_hours' => 'decimal', 'contacts' => 'int',
                'contacts_ok' => 'int', 'appointments_ok' => 'int', 'appointments_failed' => 'int', 'won_at' => 'text',
                'close_days' => 'decimal', 'student' => 'text',
            ],
            'care' => [
                'at' => 'text', 'lead_id' => 'text', 'lead_name' => 'label', 'source' => 'text', 'opportunity' => 'text',
                'employee' => 'text', 'activity' => 'text', 'result' => 'text', 'notes' => 'label',
            ],
            'appointments' => [
                'at' => 'text', 'id' => 'text', 'lead_id' => 'text', 'lead_name' => 'label', 'source' => 'text',
                'opportunity' => 'text', 'employee' => 'text', 'type' => 'text', 'status' => 'text', 'notes' => 'label',
            ],
        ];
    }

    public function exportColumnOn(string $sheet, string $column): bool
    {
        return $column === self::EXPORT_LOCKED[$sheet] || (bool) ($this->exportColumns[$sheet][$column] ?? true);
    }

    public function exportSheetOn(string $sheet): bool
    {
        return (bool) ($this->exportSheets[$sheet] ?? true);
    }

    public function activeExportTab(): string
    {
        return in_array($this->exportTab, self::EXPORT_SHEETS, true) ? $this->exportTab : self::EXPORT_SHEETS[0];
    }

    #[Computed]
    public function exportPreview(): ?array
    {
        $period = $this->resolveExportPeriod();
        if (! $period) {
            return null;
        }

        [$filters, $criteria] = $this->exportCriteria();
        $tab = $this->activeExportTab();
        $sheet = $this->exportSheet($tab, $this->exportRows($tab, $this->exportData($period, $filters, [$tab])), $period, $criteria);

        $items = count(array_filter($sheet['rows'], fn ($row) => $row[0] === 'item'));
        $head = count($sheet['rows']) - $items;
        $sheet['rows'] = array_slice($sheet['rows'], 0, $head + self::EXPORT_PREVIEW_ROWS);

        return [
            'sheet'    => $sheet,
            'items'    => $items,
            'shown'    => min($items, self::EXPORT_PREVIEW_ROWS),
            'document' => app(ReportDocumentWriter::class)->document([]),
        ];
    }

    public function export(ReportDocumentWriter $writer)
    {
        abort_unless($this->canExport, 403);

        $period = $this->resolveExportPeriod();
        if (! $period) {
            return null;
        }

        $sheets = array_values(array_filter(self::EXPORT_SHEETS, fn ($sheet) => $this->exportSheetOn($sheet)));
        if (! $sheets) {
            $this->addError('exportSheets', $this->exportLabel('err_no_sheet'));

            return null;
        }

        [$filters, $criteria] = $this->exportCriteria();
        $data = $this->exportData($period, $filters, $sheets);
        $path = $writer->write($writer->document(array_map(
            fn ($sheet) => $this->exportSheet($sheet, $this->exportRows($sheet, $data), $period, $criteria),
            $sheets
        )));

        $this->exportOpen = false;

        return response()->download($path, 'bao-cao-chuyen-doi-lead-'.$period['slug'].'.xlsx')->deleteFileAfterSend();
    }

    protected function resolveExportPeriod(): ?array
    {
        $this->exportType = $this->exportMonth > 0 ? 'month' : 'year';
        $this->resetErrorBag(['exportYear', 'exportType']);

        return $this->exportPeriod();
    }

    protected function exportCriteria(): array
    {
        $branch = $this->branches->firstWhere('branch_id', $this->exportBranch);
        $source = in_array($this->exportSource, $this->sources, true) ? $this->exportSource : null;
        $team = $this->exportTeamOptions->firstWhere('team_id', $this->exportTeam);
        $employee = $this->exportEmployeeOptions->firstWhere('employee_id', $this->exportEmployee);

        $parts = array_filter([
            $branch ? __('reports.branch').': '.$branch->branch_name : null,
            $source ? __('reports.dimension.source').': '.$source : null,
            $team ? __('reports.sales_performance.team').': '.$team->team_name : null,
            $employee ? __('reports.sales_performance.employee').': '.$employee->full_name : null,
        ]);

        return [
            [
                'branch'   => $branch?->branch_id,
                'source'   => $source,
                'team'     => $team?->team_id,
                'employee' => $employee?->employee_id,
            ],
            $parts ? implode(' · ', $parts) : $this->exportLabel('scope_all'),
        ];
    }

    protected function exportData(array $period, array $filters, array $sheets): array
    {
        $facts = $this->report()->leads(['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()] + $filters);
        $ids = array_merge(...array_column($facts, 'opportunities') ?: [[]]);

        return [
            'facts'        => $facts,
            'leads'        => $this->leadsByOpportunity($facts),
            'contacts'     => in_array('care', $sheets, true) ? $this->report()->contacts($ids) : [],
            'appointments' => in_array('appointments', $sheets, true) ? $this->report()->appointments($ids) : [],
        ];
    }

    protected function exportSheet(string $sheet, array $rows, array $period, string $criteria): array
    {
        $catalog = $this->exportColumnCatalog()[$sheet];
        $keys = array_values(array_filter(array_keys($catalog), fn ($column) => $this->exportColumnOn($sheet, $column)));

        $n = 0;
        $out = [];
        foreach ($rows as [$kind, $values]) {
            $out[] = [$kind, array_merge([$kind === 'item' ? ++$n : ''], array_map(fn ($key) => $values[$key] ?? null, $keys))];
        }

        return [
            'name'    => $this->exportLabel('sheet.'.$sheet),
            'title'   => $this->exportLabel('title.'.$sheet),
            'period'  => $period['label'],
            'scope'   => [$period['range'], $criteria],
            'columns' => array_merge(
                [[$this->exportLabel('stt'), 'stt']],
                array_map(fn ($key) => [$this->exportLabel($sheet.'.'.$key), $catalog[$key]], $keys)
            ),
            'rows'    => $out,
        ];
    }

    protected function exportRows(string $sheet, array $data): array
    {
        return match ($sheet) {
            'summary'      => $this->summaryRows($data['facts']),
            'leads'        => $this->leadRows($data['facts']),
            'care'         => $this->careRows($data['contacts'], $data['leads']),
            'appointments' => $this->appointmentRows($data['appointments'], $data['leads']),
        };
    }

    protected function summaryRows(array $facts): array
    {
        $groups = [];
        foreach ($facts as $f) {
            $groups[$f['source']][] = $f;
        }
        $sources = [];
        foreach ($groups as $source => $items) {
            $sources[] = $this->report()->summarize($items) + ['source' => (string) $source];
        }
        usort($sources, fn ($a, $b) => [$b['leads'], $b['won']] <=> [$a['leads'], $a['won']]);

        $rows = [['total', ['source' => __('reports.lead_conversion.total')] + $this->report()->summarize($facts)]];
        foreach ($sources as $r) {
            $rows[] = ['item', $r];
        }

        return $rows;
    }

    protected function leadRows(array $facts): array
    {
        $branches = $this->branches->pluck('branch_name', 'branch_id');
        $teams = $this->teams->pluck('team_name', 'team_id');
        $stuck = array_column($this->report()->stuck($facts), 'days', 'id');
        $now = time();
        $yes = $this->exportLabel('yes');

        return array_map(function ($f) use ($branches, $teams, $stuck, $now, $yes) {
            $open = $f['outcome'] === LeadConversionReport::OUTCOME_OPEN;

            return ['item', [
                'id'                  => $f['id'],
                'name'                => $f['name'],
                'created'             => $this->dateTime($f['created']),
                'source'              => $f['source'],
                'method'              => __('leads.method.'.$f['method']),
                'branch'              => $branches[$f['branch']] ?? $f['branch'],
                'owner'               => $f['employee_name'],
                'team'                => $teams[$f['team']] ?? $f['team'],
                'opportunity'         => $f['opportunity'],
                'outcome'             => __('reports.lead_conversion.outcome.'.$f['outcome']),
                'stage'               => $f['stage_name'],
                'stage_since'         => $open ? $this->dateTime($f['stage_since']) : null,
                'days_in_stage'       => $open && $f['stage_since'] ? round(($now - $f['stage_since']) / 86400, 1) : null,
                'stuck'               => isset($stuck[$f['id']]) ? $yes : null,
                'assigned'            => $this->dateTime($f['converted_at']),
                'first_contact'       => $this->dateTime($f['first_contact']),
                'response_hours'      => $f['first_contact'] ? round(max(0, $f['first_contact'] - $f['created']) / 3600, 1) : null,
                'contacts'            => $f['contacts'],
                'contacts_ok'         => $f['contacts_ok'],
                'appointments_ok'     => $f['appointments_ok'],
                'appointments_failed' => $f['appointments_failed'],
                'won_at'              => $this->dateTime($f['won_at']),
                'close_days'          => $f['won_at'] ? round(max(0, $f['won_at'] - $f['created']) / 86400, 1) : null,
                'student'             => $f['student'] ? $yes : null,
            ]];
        }, array_values($facts));
    }

    protected function careRows(array $contacts, array $leads): array
    {
        $people = $this->employeeNames(array_column($contacts, 'employee'));
        $activities = DB::table('CareActivities')->pluck('activity_name', 'activity_id');

        return array_map(fn ($c) => ['item', [
            'at'          => $this->dateTime($c['at']),
            'lead_id'     => $leads[$c['opportunity']]['id'] ?? null,
            'lead_name'   => $leads[$c['opportunity']]['name'] ?? null,
            'source'      => $leads[$c['opportunity']]['source'] ?? null,
            'opportunity' => $c['opportunity'],
            'employee'    => $people[$c['employee']] ?? $c['employee'],
            'activity'    => $activities[$c['activity']] ?? $c['activity'],
            'result'      => __('reports.lead_conversion.care.result.'.$c['result']),
            'notes'       => $c['notes'],
        ]], array_values($contacts));
    }

    protected function appointmentRows(array $appointments, array $leads): array
    {
        $people = $this->employeeNames(array_column($appointments, 'employee'));

        return array_map(fn ($a) => ['item', [
            'at'          => $this->dateTime($a['at']),
            'id'          => $a['id'],
            'lead_id'     => $leads[$a['opportunity']]['id'] ?? null,
            'lead_name'   => $leads[$a['opportunity']]['name'] ?? null,
            'source'      => $leads[$a['opportunity']]['source'] ?? null,
            'opportunity' => $a['opportunity'],
            'employee'    => $people[$a['employee']] ?? $a['employee'],
            'type'        => __('opportunities.appointment_type_name.'.$a['type']),
            'status'      => __('appointments.status.'.$a['status']),
            'notes'       => $a['notes'],
        ]], array_values($appointments));
    }

    public function scopeLabel(): string
    {
        $label = fn ($key, $params = []) => __('reports.lead_conversion.scope.'.$key, $params);

        return match (true) {
            $this->seesAll                => $label('all'),
            ! $this->canFilterEmployee    => $label('mine'),
            $this->employeeScope !== null => $label('employee', [
                'name' => $this->employeeOptions->firstWhere('employee_id', $this->employeeScope)?->full_name ?? $this->employeeScope]),
            (bool) $this->teamScope       => $label('team', [
                'team' => $this->teams->firstWhere('team_id', $this->teamScope)?->team_name ?? $this->teamScope]),
            default                       => $label('none'),
        };
    }

    protected function leadsByOpportunity(array $facts): array
    {
        $map = [];
        foreach ($facts as $f) {
            foreach ($f['opportunities'] as $id) {
                $map[$id] = $f;
            }
        }

        return $map;
    }

    protected function employeeNames(array $ids): array
    {
        return Employee::query()->whereIn('employee_id', array_unique(array_filter($ids)))
            ->pluck('full_name', 'employee_id')->all();
    }

    protected function dateTime(?int $time): ?string
    {
        return $time ? date('d/m/Y H:i', $time) : null;
    }

    public function exportLabel(string $key, array $replace = []): string
    {
        return __('reports.lead_conversion.export.'.$key, $replace);
    }

    public function opportunityUrl(?string $id): ?string
    {
        $name = Str::before((string) Auth::user()->homeRoute(), '.').'.opportunities.show';

        return $id && Route::has($name) ? route($name, $id) : null;
    }

    protected function report(): LeadConversionReport
    {
        return app(LeadConversionReport::class);
    }

    protected function filterValues(): array
    {
        return [
            'from'     => $this->validDate($this->from) ? $this->from : null,
            'to'       => $this->validDate($this->to) ? $this->to : null,
            'branch'   => $this->branchScope,
            'team'     => $this->teamScope === null ? '__none__' : ($this->teamScope ?: null),
            'employee' => $this->employeeScope,
        ];
    }

    protected function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }

    protected function panelOptions(string $panel): array
    {
        $t = fn ($key) => __('reports.lead_conversion.'.$key);
        $labels = fn (array $keys, callable $label) => array_combine($keys, array_map($label, $keys));

        return match (self::PANELS[$panel]) {
            'method' => ['' => __('reports.all')] + $labels(Lead::CONTACT_METHODS, fn ($method) => __('leads.method.'.$method)),
            'period' => ['' => $t('chart.period_auto'), 'month' => $t('chart.period_month'), 'week' => $t('chart.period_week')],
            'share'  => $labels(
                $this->seesAll ? ['leads', 'opportunities', 'won', 'students'] : ['leads', 'won', 'students'],
                fn ($key) => $this->shareLabel($key)
            ),
            'rate'   => $labels(
                $this->seesAll ? ['win_rate', 'close_rate', 'conversion_rate'] : ['win_rate', 'close_rate'],
                fn ($key) => $t('metric.'.$key)
            ),
            default  => ['' => __('reports.all')] + array_combine($this->sources, $this->sources),
        };
    }

    protected function shareLabel(string $metric): string
    {
        return match ($metric) {
            'opportunities' => __('reports.lead_conversion.overview.step_opportunities'),
            'students'      => __('reports.lead_conversion.overview.step_students'),
            'won'           => __('reports.lead_conversion.metric.won'),
            default         => __('reports.lead_conversion.metric.leads'),
        };
    }

    public function panelValue(string $panel): string
    {
        $options = $this->panelOptions($panel);
        $value = (string) ($this->chart[$panel] ?? '');

        return array_key_exists($value, $options) ? $value : (string) array_key_first($options);
    }

    public function panelFilter(string $panel): array
    {
        return [
            'model'   => 'chart.'.$panel,
            'label'   => __('reports.lead_conversion.chart.'.self::PANELS[$panel]),
            'options' => $this->panelOptions($panel),
            'value'   => $this->panelValue($panel),
        ];
    }

    protected function panelFacts(string $panel): array
    {
        $kind = self::PANELS[$panel];
        $value = $this->panelValue($panel);
        if ($value === '' || ! in_array($kind, ['source', 'method'], true)) {
            return $this->facts;
        }

        return array_values(array_filter($this->facts, fn ($f) => $f[$kind] === $value));
    }

    protected function groupFacts(callable $key): array
    {
        $groups = [];
        foreach ($this->facts as $f) {
            $groups[(string) $key($f)][] = $f;
        }

        return $groups;
    }

    protected function series(array $rows, array $metrics): array
    {
        return array_map(fn ($m) => [
            'label' => __('reports.lead_conversion.metric.'.$m),
            'color' => self::COLORS[$m],
            'data'  => array_map(fn ($r) => $r[$m] ?? 0, $rows),
        ], $metrics);
    }

    protected function colorMap(array $catalog, array $values): array
    {
        $map = [];
        foreach ($values as $value) {
            $i = array_search($value, $catalog, true);
            $map[$value] = $i !== false && $i < count(self::PALETTE) ? self::PALETTE[$i] : LeadSources::OTHER_COLOR;
        }

        return $map;
    }

    protected function bucket(float $value, array $buckets): string
    {
        foreach ($buckets as $key => $limit) {
            if ($value <= $limit) {
                return $key;
            }
        }

        return array_key_last($buckets);
    }

    protected function monthIndex(int $time): int
    {
        return (int) date('Y', $time) * 12 + (int) date('n', $time) - 1;
    }

    protected function periodKey(int $time, ?string $period = null): string
    {
        return ($period ?? $this->period) === 'month' ? date('Y-m', $time) : date('o-W', $time);
    }

    protected function periodKeys(array $times, ?string $period = null): array
    {
        if (! $times) {
            return [];
        }

        $period ??= $this->period;
        $month = $period === 'month';
        $cursor = CarbonImmutable::createFromTimestamp(min($times), config('app.timezone'));
        $cursor = $month ? $cursor->startOfMonth() : $cursor->startOfWeek();
        $end = max($times);

        $keys = [];
        while ($cursor->getTimestamp() <= $end) {
            $keys[] = $this->periodKey($cursor->getTimestamp(), $period);
            $cursor = $month ? $cursor->addMonth() : $cursor->addWeek();
        }

        return $keys;
    }

    public function periodLabel(string $key, ?string $period = null): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $key, $m)) {
            return $key;
        }

        return ($period ?? $this->period) === 'month'
            ? "$m[2]/$m[1]"
            : __('reports.week_label', ['week' => (int) $m[2], 'year' => $m[1]]);
    }

    public function render()
    {
        return view('livewire.reports.lead-conversion')->title(__('reports.lead_conversion.title'));
    }
}
