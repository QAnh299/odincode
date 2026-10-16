<?php

namespace App\Livewire\Reports;

use App\Models\Branch;
use App\Models\CareResult;
use App\Models\Employee;
use App\Models\Role;
use App\Models\SalesTeam;
use App\Services\LeadConversionReport;
use App\Services\ReportExcelWriter;
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

/**
 * Dashboard & Báo cáo → Phân tích hiệu quả chuyển đổi Lead.
 *
 * Phạm vi theo vai trò: Giám đốc, Sale Admin xem toàn bộ Lead (lọc được theo đội / salesperson);
 * Sale Leader xem Lead của đội mình; Salesperson xem Lead mình phụ trách.
 *
 * 5 tab: Tổng quan phễu, Hiệu quả theo nguồn, Tốc độ chuyển đổi,
 * Chất lượng chăm sóc, Cohort theo tháng. Mỗi tab chỉ tính số liệu khi được mở.
 * Tỷ lệ tính theo ngày tạo Lead, doanh thu theo ngày thanh toán - xem App\Services\LeadConversionReport.
 */
#[Layout('layouts.app')]
class LeadConversion extends Component
{
    const TABS = ['overview', 'sources', 'velocity', 'care', 'cohort'];

    // Bảng màu phân loại dùng chung các trang báo cáo; màu đi theo thực thể / chỉ số
    const PALETTE = LeadSources::PALETTE;

    const COLORS = [
        'leads'            => '#2a78d6',
        'opportunities'    => '#6250d6',
        'won'              => '#1baf7a',
        'open'             => '#eda100',
        'lost'             => '#e34948',
        'archived'         => '#9ca3af',
        'new'              => '#c3cad5',
        'revenue'          => '#2a78d6',
        'conversion_rate'  => '#6250d6',
        'win_rate'         => '#1baf7a',
        'close_rate'       => '#eda100',
        'Success'          => '#1baf7a',
        'Failed'           => '#e34948',
        'Unreachable'      => '#eda100',
    ];

    // Nhóm thời gian phản hồi đầu tiên (giờ, cận trên)
    const RESPONSE_BUCKETS = ['h1' => 1, 'h4' => 4, 'h24' => 24, 'd3' => 72, 'more' => INF];

    // Nhóm thời gian chốt (ngày, cận trên)
    const CLOSE_BUCKETS = ['d7' => 7, 'd14' => 14, 'd30' => 30, 'd60' => 60, 'more' => INF];

    // Số lần liên hệ: 0, 1, …, 5+ (số cuối gộp từ đó trở lên)
    const CONTACT_BUCKETS = [0, 1, 2, 3, 4, 5];

    const COHORT_ROWS = 12;

    const COHORT_LINES = 6;

    const STUCK_LIMIT = 50;

    const COHORT_METRICS = ['won', 'converted', 'student'];

    /* ── Bộ lọc ───────────────────────────────────────────────────────── */

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $branch = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: '')]
    public string $team = '';

    #[Url(except: '')]
    public string $employee = '';

    #[Url(except: 'won')]
    public string $cohortMetric = 'won';

    const FILTERS = ['from', 'to', 'branch', 'source', 'team', 'employee'];

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

    /**
     * Xuất Excel: chỉ Giám đốc, Sale Admin.
     */
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
        return SalesTeam::query()->orderBy('team_name')->get(['team_id', 'team_name']);
    }

    /**
     * Đội đang xem: Giám đốc / Sale Admin = đội đã chọn ('' = tất cả); Sale Leader = đội của mình
     * (không thuộc đội nào → null, không thấy dữ liệu). Salesperson không lọc theo đội ('').
     */
    #[Computed]
    public function teamScope(): ?string
    {
        if ($this->canFilterTeam) {
            return $this->teams->contains('team_id', $this->team) ? $this->team : '';
        }

        return $this->canFilterEmployee ? $this->me?->team_id : '';
    }

    /**
     * Salesperson trong phạm vi đang xem (cho bộ lọc và để hiện cả người chưa có số liệu).
     */
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
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'team_id', 'status']);
    }

    /**
     * Salesperson đang xem: Salesperson = chính mình; vai trò khác = người đã chọn (null = tất cả).
     */
    #[Computed]
    public function employeeScope(): ?string
    {
        if (! $this->canFilterEmployee) {
            return $this->me?->employee_id ?? '__none__';
        }

        return $this->employeeOptions->contains('employee_id', $this->employee) ? $this->employee : null;
    }

    /**
     * Đang xem toàn bộ Lead (gồm cả Lead chưa có Cơ hội): Giám đốc / Sale Admin không lọc đội, nhân viên.
     * Phạm vi khác chỉ gồm Lead đã giao nên không có bước Lead → Cơ hội.
     */
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

    /**
     * Toàn bộ nguồn Lead (theo tên) - cho bộ lọc và làm khoá màu cố định của từng nguồn.
     */
    #[Computed]
    public function sources(): array
    {
        return DB::table('Leads')->distinct()->orderBy('source_name')->pluck('source_name')->all();
    }

    /* ── Số liệu chung ────────────────────────────────────────────────── */

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
    public function payments(): array
    {
        return $this->report()->payments($this->filterValues());
    }

    #[Computed]
    public function revenue(): float
    {
        return array_sum(array_column($this->payments, 'amount'));
    }

    /**
     * Kỳ liền trước cùng độ dài (chỉ khi chọn đủ Từ ngày và Đến ngày).
     */
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
            'revenue' => array_sum(array_column($this->report()->payments($filters), 'amount')),
        ];
    }

    /**
     * Nhóm thời gian của biểu đồ xu hướng: theo tháng nếu dữ liệu trải từ 4 tháng trở lên, không thì theo tuần.
     */
    #[Computed]
    public function period(): string
    {
        $times = array_merge(array_column($this->facts, 'created'), array_column($this->payments, 'paid_at'));
        if (! $times) {
            return 'month';
        }

        return $this->monthIndex(max($times)) - $this->monthIndex(min($times)) >= 3 ? 'month' : 'week';
    }

    /* ── Tab 1: Tổng quan phễu ────────────────────────────────────────── */

    #[Computed]
    public function overview(): array
    {
        $s = $this->summary;
        $label = fn ($key) => __('reports.lead_conversion.overview.'.$key);

        // Phễu: Lead → (Cơ hội) → các stage sau stage đầu → Học viên
        $steps = [[$label($this->seesAll ? 'step_leads' : 'step_assigned'), $s['leads']]];
        if ($this->seesAll) {
            $steps[] = [$label('step_opportunities'), $s['opportunities']];
        }
        foreach (array_slice($this->report()->funnelStages(), 1) as $stage) {
            $steps[] = [$stage['name'], $s['reached'][$stage['order']]];
        }
        $steps[] = [$label('step_students'), $s['students']];

        $funnel = [];
        foreach ($steps as $i => [$name, $value]) {
            $funnel[] = [
                'name'    => $name,
                'value'   => $value,
                'rate'    => $i ? LeadConversionReport::pct($value, $steps[$i - 1][1]) : null,
                'overall' => LeadConversionReport::pct($value, $s['leads']),
                'prev'    => $i ? $steps[$i - 1][0] : null,
            ];
        }

        $outcomes = array_values(array_filter(LeadConversionReport::OUTCOMES, fn ($o) => $s[$o] > 0));

        // Xu hướng theo kỳ tạo Lead
        $groups = $this->groupFacts(fn ($f) => $this->periodKey($f['created']));
        $keys = $this->periodKeys(array_column($this->facts, 'created'));
        $rows = array_map(fn ($k) => $this->report()->summarize($groups[$k] ?? []), $keys);
        $labels = array_map(fn ($k) => $this->periodLabel($k), $keys);

        $rateMetrics = $this->seesAll ? ['conversion_rate', 'win_rate'] : ['win_rate', 'close_rate'];

        // Doanh thu theo kỳ thanh toán
        $paid = [];
        foreach ($this->payments as $p) {
            $key = $this->periodKey($p['paid_at']);
            $paid[$key] = ($paid[$key] ?? 0) + $p['amount'];
        }
        $paidKeys = $this->periodKeys(array_column($this->payments, 'paid_at'));

        return [
            'funnel' => $funnel,
            'charts' => [
                'funnel'   => ['type' => 'bar_h', 'unit' => 'count', 'labels' => array_column($funnel, 'name'),
                    'series' => [['label' => $label('reached'), 'color' => self::COLORS['leads'], 'data' => array_column($funnel, 'value')]]],
                'outcomes' => ['type' => 'doughnut', 'unit' => 'count',
                    'labels' => array_map(fn ($o) => __('reports.lead_conversion.outcome.'.$o), $outcomes),
                    'series' => [['label' => $label('leads'), 'data' => array_map(fn ($o) => $s[$o], $outcomes),
                        'colors' => array_map(fn ($o) => self::COLORS[$o], $outcomes)]]],
                'trend_count' => ['type' => 'bar', 'unit' => 'count', 'labels' => $labels,
                    'series' => $this->series($rows, ['leads', 'won'])],
                'trend_rate' => ['type' => 'line', 'unit' => 'percent', 'labels' => $labels,
                    'series' => $this->series($rows, $rateMetrics)],
                'revenue' => ['type' => 'bar', 'unit' => 'money', 'labels' => array_map(fn ($k) => $this->periodLabel($k), $paidKeys),
                    'series' => [['label' => __('reports.lead_conversion.metric.revenue'), 'color' => self::COLORS['revenue'],
                        'data' => array_map(fn ($k) => $paid[$k] ?? 0, $paidKeys)]]],
            ],
        ];
    }

    /* ── Tab 2: Hiệu quả theo nguồn ───────────────────────────────────── */

    #[Computed]
    public function bySource(): array
    {
        $revenue = $this->sumPayments('source');
        $groups = $this->groupFacts(fn ($f) => $f['source']);

        // Nguồn chỉ có doanh thu (Lead tạo ngoài kỳ) vẫn hiện
        foreach (array_keys($revenue) as $source) {
            $groups[$source] ??= [];
        }

        $rows = [];
        foreach ($groups as $source => $facts) {
            $rows[] = $this->withRevenue($this->report()->summarize($facts), $revenue[$source] ?? 0) + ['name' => (string) $source];
        }
        usort($rows, fn ($a, $b) => [$b['leads'], $b['revenue']] <=> [$a['leads'], $a['revenue']]);

        $colors = $this->colorMap($this->sources, array_column($rows, 'name'));
        $withLeads = array_values(array_filter($rows, fn ($r) => $r['leads'] > 0));
        $withRevenue = array_values(array_filter($rows, fn ($r) => $r['revenue'] > 0));
        usort($withRevenue, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        // Theo kênh liên hệ
        $methodRevenue = $this->sumPayments('method');
        $methods = [];
        foreach ($this->groupFacts(fn ($f) => $f['method']) as $method => $facts) {
            $methods[] = $this->withRevenue($this->report()->summarize($facts), $methodRevenue[$method] ?? 0)
                + ['name' => __('leads.method.'.$method)];
        }
        usort($methods, fn ($a, $b) => $b['leads'] <=> $a['leads']);

        $rateMetrics = $this->seesAll ? ['conversion_rate', 'win_rate', 'close_rate'] : ['win_rate', 'close_rate'];

        return [
            'rows'    => $rows,
            'methods' => $methods,
            'total'   => $this->withRevenue($this->summary, $this->revenue),
            'charts'  => [
                // Số Lead (trục ngang) × tỷ lệ chốt (trục dọc): nguồn nhiều nhưng kém chất lượng nằm góc phải dưới
                'scatter' => ['type' => 'scatter', 'unit' => 'percent', 'xUnit' => 'count',
                    'xLabel' => __('reports.lead_conversion.metric.leads'),
                    'yLabel' => __('reports.lead_conversion.metric.win_rate'),
                    'labels' => [],
                    'series' => array_map(fn ($r) => ['label' => $r['name'], 'color' => $colors[$r['name']],
                        'data' => [['x' => $r['leads'], 'y' => $r['win_rate'] ?? 0]]], $withLeads)],
                'rates'   => ['type' => 'bar', 'unit' => 'percent', 'labels' => array_column($withLeads, 'name'),
                    'series' => $this->series($withLeads, $rateMetrics)],
                'revenue' => ['type' => 'bar_h', 'unit' => 'money', 'labels' => array_column($withRevenue, 'name'),
                    'series' => [['label' => __('reports.lead_conversion.metric.revenue'), 'color' => self::COLORS['revenue'],
                        'data' => array_column($withRevenue, 'revenue')]]],
                'methods' => ['type' => 'bar', 'unit' => 'percent', 'labels' => array_column($methods, 'name'),
                    'series' => $this->series($methods, $rateMetrics)],
            ],
        ];
    }

    /* ── Tab 3: Tốc độ chuyển đổi ─────────────────────────────────────── */

    #[Computed]
    public function velocity(): array
    {
        $facts = $this->facts;
        $stages = $this->report()->funnelStages();
        $label = fn ($key, $params = []) => __('reports.lead_conversion.velocity.'.$key, $params);
        $hours = fn ($a, $b) => $a !== null && $b !== null ? max(0, $b - $a) / 3600 : null;

        // Thời gian từng bước (giờ)
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

        // Thời gian phản hồi đầu tiên: phân bố và tỷ lệ chốt theo từng nhóm (chỉ Lead đã giao)
        $assigned = array_values(array_filter($facts, fn ($f) => $f['outcome'] !== LeadConversionReport::OUTCOME_NEW));
        $responseGroups = array_fill_keys(array_merge(array_keys(self::RESPONSE_BUCKETS), ['none']), []);
        foreach ($assigned as $f) {
            $h = $hours($f['created'], $f['first_contact']);
            $responseGroups[$h === null ? 'none' : $this->bucket($h, self::RESPONSE_BUCKETS)][] = $f;
        }
        $responseRows = [];
        foreach ($responseGroups as $key => $group) {
            $responseRows[] = $this->report()->summarize($group) + ['name' => $label('response.'.$key)];
        }

        // Thời gian chốt (ngày, Lead đã chốt)
        $closeCounts = array_fill_keys(array_keys(self::CLOSE_BUCKETS), 0);
        foreach ($facts as $f) {
            if ($f['won_at']) {
                $closeCounts[$this->bucket(max(0, $f['won_at'] - $f['created']) / 86400, self::CLOSE_BUCKETS)]++;
            }
        }

        $within24 = count(array_filter($assigned, fn ($f) => ($h = $hours($f['created'], $f['first_contact'])) !== null && $h <= 24));
        $stuck = $this->report()->stuck($facts);
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
                'response_count' => ['type' => 'bar', 'unit' => 'count', 'labels' => array_column($responseRows, 'name'),
                    'series' => [['label' => __('reports.lead_conversion.metric.leads'), 'color' => self::COLORS['leads'],
                        'data' => array_column($responseRows, 'leads')]]],
                'response_rate'  => ['type' => 'bar', 'unit' => 'percent', 'labels' => array_column($responseRows, 'name'),
                    'series' => $this->series($responseRows, ['win_rate'])],
                'close'          => ['type' => 'bar', 'unit' => 'count',
                    'labels' => array_map(fn ($k) => $label('close.'.$k), array_keys(self::CLOSE_BUCKETS)),
                    'series' => [['label' => __('reports.lead_conversion.metric.won'), 'color' => self::COLORS['won'],
                        'data' => array_values($closeCounts)]]],
            ],
        ];
    }

    /* ── Tab 4: Chất lượng chăm sóc ───────────────────────────────────── */

    #[Computed]
    public function care(): array
    {
        $facts = $this->facts;
        $ids = array_merge(...array_column($facts, 'opportunities') ?: [[]]);
        $contacts = $this->report()->contacts($ids);
        $appointments = $this->report()->appointments($ids);
        $results = CareResult::RESULTS;

        // Kết quả theo hoạt động chăm sóc
        $activityNames = DB::table('CareActivities')->orderBy('activity_id')->pluck('activity_name', 'activity_id');
        $activities = [];
        foreach ($activityNames as $id => $name) {
            $activities[$id] = ['name' => $name, 'total' => 0] + array_fill_keys($results, 0);
        }
        foreach ($contacts as $c) {
            $activities[$c['activity']] ??= ['name' => $c['activity'], 'total' => 0] + array_fill_keys($results, 0);
            $activities[$c['activity']]['total']++;
            $activities[$c['activity']][$c['result']] = ($activities[$c['activity']][$c['result']] ?? 0) + 1;
        }
        foreach ($activities as &$a) {
            foreach ($results as $r) {
                $a['rate'][$r] = LeadConversionReport::pct($a[$r], $a['total']);
            }
        }
        unset($a);
        $activities = array_values(array_filter($activities, fn ($a) => $a['total'] > 0));

        // Tỷ lệ thắng theo số lần liên hệ (Lead đã đóng)
        $closedFacts = array_filter($facts, fn ($f) => in_array($f['outcome'], [
            LeadConversionReport::OUTCOME_WON, LeadConversionReport::OUTCOME_LOST, LeadConversionReport::OUTCOME_ARCHIVED,
        ], true));
        $last = self::CONTACT_BUCKETS[array_key_last(self::CONTACT_BUCKETS)];
        $touchGroups = array_fill_keys(self::CONTACT_BUCKETS, []);
        foreach ($closedFacts as $f) {
            $touchGroups[min($f['contacts'], $last)][] = $f;
        }
        $touches = [];
        foreach (array_filter($touchGroups) as $n => $group) {
            $touches[] =$this->report()->summarize($group) + ['name' => __('reports.lead_conversion.care.touches_'.($n === $last ? 'more' : 'n'), ['n' => $n])];
        }

        // Lịch hẹn theo loại
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

        // Bản đồ nhiệt: thứ trong tuần × giờ liên hệ
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
            array_filter($facts, fn ($f) => in_array($f['outcome'], $outcomes, true)), 'contacts'));
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
                'activities' => ['type' => 'stacked_h', 'unit' => 'percent', 'labels' => array_column($activities, 'name'),
                    'series' => array_map(fn ($r) => ['label' => __('reports.lead_conversion.care.result.'.$r), 'color' => self::COLORS[$r],
                        'data' => array_map(fn ($a) => $a['rate'][$r] ?? 0, $activities)], $results)],
                'touches'    => ['type' => 'bar', 'unit' => 'percent',
                    'labels' => array_map(fn ($t) => $t['name'].' (n='.$t['leads'].')', $touches),
                    'series' => $this->series($touches, ['close_rate'])],
            ],
        ];
    }

    /* ── Tab 5: Cohort theo tháng tạo Lead ────────────────────────────── */

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
                    'data' => array_values($r['cells'])], $lines, array_keys($lines))],
        ];
    }

    /* ── Xuất Excel ───────────────────────────────────────────────────── */

    /**
     * Xuất theo bộ lọc và phạm vi đang xem, dạng danh sách (mỗi sheet 1 bảng):
     * Tổng hợp theo nguồn, Danh sách Lead (không kèm SĐT / email), Lịch sử chăm sóc, Lịch hẹn.
     */
    public function export(ReportExcelWriter $writer)
    {
        abort_unless($this->canExport, 403);

        $ids = array_merge(...array_column($this->facts, 'opportunities') ?: [[]]);
        $contacts = $this->report()->contacts($ids);
        $appointments = $this->report()->appointments($ids);

        $path = $writer->write([
            $this->summarySheet(),
            $this->leadListSheet(),
            $this->careListSheet($contacts),
            $this->appointmentListSheet($appointments),
        ]);

        return response()
            ->download($path, 'bao-cao-chuyen-doi-lead-'.now()->format('Ymd-His').'.xlsx')
            ->deleteFileAfterSend();
    }

    /**
     * Mô tả phạm vi đang xem (tiêu đề trang).
     */
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

    /**
     * Tổng hợp: mỗi nguồn Lead 1 dòng + dòng tổng cộng.
     */
    protected function summarySheet(): array
    {
        $d = $this->bySource;
        $col = fn ($key) => $this->exportLabel('summary.'.$key);

        // [khoá, kiểu]
        $fields = [
            'leads' => 'int', 'opportunities' => 'int', 'conversion_rate' => 'percent', 'contact_rate' => 'percent',
            'appointment_rate' => 'percent', 'open' => 'int', 'won' => 'int', 'lost' => 'int', 'archived' => 'int',
            'win_rate' => 'percent', 'close_rate' => 'percent', 'students' => 'int', 'response_median' => 'decimal',
            'close_median' => 'decimal', 'revenue' => 'money', 'revenue_per_lead' => 'money',
        ];
        $values = fn (array $row) => array_map(fn ($key) => $row[$key] ?? null, array_keys($fields));

        return [
            'name'    => $this->exportLabel('sheet.summary'),
            'columns' => array_merge([[$col('source'), 'text']],
                array_map(fn ($key, $type) => [$col($key), $type], array_keys($fields), $fields)),
            'rows'    => array_map(fn ($r) => array_merge([$r['name']], $values($r)), $d['rows']),
            'total'   => array_merge([__('reports.lead_conversion.total')], $values($d['total'])),
        ];
    }

    /**
     * Mỗi Lead 1 dòng - không kèm số điện thoại / email.
     */
    protected function leadListSheet(): array
    {
        $branches = $this->branches->pluck('branch_name', 'branch_id');
        $teams = $this->teams->pluck('team_name', 'team_id');
        $stuck = array_column($this->report()->stuck($this->facts), 'days', 'id');
        $now = time();
        $hours = fn ($from, $to) => $from && $to ? round(max(0, $to - $from) / 3600, 1) : null;
        $yes = $this->exportLabel('yes');
        $open = fn ($f) => $f['outcome'] === LeadConversionReport::OUTCOME_OPEN;

        $columns = [
            'id' => 'text', 'name' => 'text', 'created' => 'datetime', 'source' => 'text', 'method' => 'text',
            'branch' => 'text', 'owner' => 'text', 'team' => 'text', 'opportunity' => 'text', 'outcome' => 'text',
            'stage' => 'text', 'stage_since' => 'datetime', 'days_in_stage' => 'decimal', 'stuck' => 'text',
            'assigned' => 'datetime', 'first_contact' => 'datetime', 'response_hours' => 'decimal', 'contacts' => 'int',
            'contacts_ok' => 'int', 'appointments_ok' => 'int', 'appointments_failed' => 'int', 'won_at' => 'datetime',
            'close_days' => 'decimal', 'student' => 'text', 'order_total' => 'money',
        ];

        return [
            'name'    => $this->exportLabel('sheet.leads'),
            'columns' => array_map(fn ($key, $type) => [$this->exportLabel('leads.'.$key), $type], array_keys($columns), $columns),
            'rows'    => array_map(fn ($f) => [
                $f['id'], $f['name'], $f['created'], $f['source'], __('leads.method.'.$f['method']),
                $branches[$f['branch']] ?? $f['branch'], $f['employee_name'], $teams[$f['team']] ?? $f['team'],
                $f['opportunity'], __('reports.lead_conversion.outcome.'.$f['outcome']),
                $f['stage_name'], $open($f) ? $f['stage_since'] : null,
                $open($f) && $f['stage_since'] ? round(($now - $f['stage_since']) / 86400, 1) : null,
                isset($stuck[$f['id']]) ? $yes : null,
                $f['converted_at'], $f['first_contact'], $hours($f['created'], $f['first_contact']), $f['contacts'],
                $f['contacts_ok'], $f['appointments_ok'], $f['appointments_failed'], $f['won_at'],
                $f['won_at'] ? round(max(0, $f['won_at'] - $f['created']) / 86400, 1) : null,
                $f['student'] ? $yes : null, $f['order_total'] ?: null,
            ], $this->facts),
        ];
    }

    /**
     * Mỗi lần chăm sóc 1 dòng.
     */
    protected function careListSheet(array $contacts): array
    {
        $leads = $this->leadsByOpportunity();
        $people = $this->employeeNames(array_column($contacts, 'employee'));
        $activities = DB::table('CareActivities')->pluck('activity_name', 'activity_id');
        $columns = ['at' => 'datetime', 'lead_id' => 'text', 'lead_name' => 'text', 'source' => 'text', 'opportunity' => 'text',
            'employee' => 'text', 'activity' => 'text', 'result' => 'text', 'notes' => 'text'];

        return [
            'name'    => $this->exportLabel('sheet.care'),
            'columns' => array_map(fn ($key, $type) => [$this->exportLabel('care.'.$key), $type], array_keys($columns), $columns),
            'rows'    => array_map(fn ($c) => [
                $c['at'], $leads[$c['opportunity']]['id'] ?? null, $leads[$c['opportunity']]['name'] ?? null,
                $leads[$c['opportunity']]['source'] ?? null, $c['opportunity'], $people[$c['employee']] ?? $c['employee'],
                $activities[$c['activity']] ?? $c['activity'], __('reports.lead_conversion.care.result.'.$c['result']), $c['notes'],
            ], $contacts),
        ];
    }

    /**
     * Mỗi lịch hẹn 1 dòng.
     */
    protected function appointmentListSheet(array $appointments): array
    {
        $leads = $this->leadsByOpportunity();
        $people = $this->employeeNames(array_column($appointments, 'employee'));
        $columns = ['at' => 'datetime', 'id' => 'text', 'lead_id' => 'text', 'lead_name' => 'text', 'source' => 'text',
            'opportunity' => 'text', 'employee' => 'text', 'type' => 'text', 'status' => 'text', 'notes' => 'text'];

        return [
            'name'    => $this->exportLabel('sheet.appointments'),
            'columns' => array_map(fn ($key, $type) => [$this->exportLabel('appointments.'.$key), $type], array_keys($columns), $columns),
            'rows'    => array_map(fn ($a) => [
                $a['at'], $a['id'], $leads[$a['opportunity']]['id'] ?? null, $leads[$a['opportunity']]['name'] ?? null,
                $leads[$a['opportunity']]['source'] ?? null, $a['opportunity'], $people[$a['employee']] ?? $a['employee'],
                __('opportunities.appointment_type_name.'.$a['type']), __('appointments.status.'.$a['status']), $a['notes'],
            ], $appointments),
        ];
    }

    /**
     * Mã Cơ hội → Lead của nó (trong phạm vi đang xem).
     */
    protected function leadsByOpportunity(): array
    {
        $map = [];
        foreach ($this->facts as $f) {
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

    protected function exportLabel(string $key): string
    {
        return __('reports.lead_conversion.export.'.$key);
    }

    /* ── Hỗ trợ ───────────────────────────────────────────────────────── */

    /**
     * Link tới chi tiết Cơ hội (nếu vai trò có trang này).
     */
    public function opportunityUrl(?string $id): ?string
    {
        $name = Str::before((string) Auth::user()->homeRoute(), '.').'.opportunities.show';

        return $id && Route::has($name) ? route($name, $id) : null;
    }

    protected function report(): LeadConversionReport
    {
        return app(LeadConversionReport::class);
    }

    /**
     * Bộ lọc đã kiểm tra (giá trị không hợp lệ bị bỏ qua) + phạm vi theo vai trò.
     */
    protected function filterValues(): array
    {
        return [
            'from'     => $this->validDate($this->from) ? $this->from : null,
            'to'       => $this->validDate($this->to) ? $this->to : null,
            // Salesperson chỉ thuộc 1 chi nhánh, nhận Lead của chi nhánh đó → không lọc chi nhánh
            'branch'   => $this->canFilterEmployee && $this->branches->contains('branch_id', $this->branch) ? $this->branch : null,
            'source'   => in_array($this->source, $this->sources, true) ? $this->source : null,
            'team'     => $this->teamScope === null ? '__none__' : ($this->teamScope ?: null),
            'employee' => $this->employeeScope,
        ];
    }

    protected function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }

    /**
     * Nhóm các Lead theo 1 khoá.
     */
    protected function groupFacts(callable $key): array
    {
        $groups = [];
        foreach ($this->facts as $f) {
            $groups[(string) $key($f)][] = $f;
        }

        return $groups;
    }

    /**
     * Cộng doanh thu (theo ngày thanh toán) theo 1 cột của payments().
     */
    protected function sumPayments(string $key): array
    {
        $sums = [];
        foreach ($this->payments as $p) {
            $sums[(string) $p[$key]] = ($sums[(string) $p[$key]] ?? 0) + $p['amount'];
        }

        return $sums;
    }

    protected function withRevenue(array $row, float $revenue): array
    {
        return $row + [
            'revenue'          => $revenue,
            'revenue_per_lead' => $row['leads'] ? round($revenue / $row['leads']) : null,
        ];
    }

    /**
     * Mỗi chỉ số 1 dãy biểu đồ, màu cố định theo chỉ số.
     */
    protected function series(array $rows, array $metrics): array
    {
        return array_map(fn ($m) => [
            'label' => __('reports.lead_conversion.metric.'.$m),
            'color' => self::COLORS[$m],
            'data'  => array_map(fn ($r) => $r[$m] ?? 0, $rows),
        ], $metrics);
    }

    /**
     * Màu cố định cho từng giá trị theo vị trí trong danh mục; ngoài bảng màu thì xám.
     */
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

    protected function periodKey(int $time): string
    {
        return $this->period === 'month' ? date('Y-m', $time) : date('o-W', $time);
    }

    /**
     * Các kỳ liên tiếp từ thời điểm nhỏ nhất đến lớn nhất (không bỏ trống kỳ không có số liệu).
     */
    protected function periodKeys(array $times): array
    {
        if (! $times) {
            return [];
        }

        $month = $this->period === 'month';
        $cursor = CarbonImmutable::createFromTimestamp(min($times), config('app.timezone'));
        $cursor = $month ? $cursor->startOfMonth() : $cursor->startOfWeek();
        $end = max($times);

        $keys = [];
        while ($cursor->getTimestamp() <= $end) {
            $keys[] = $this->periodKey($cursor->getTimestamp());
            $cursor = $month ? $cursor->addMonth() : $cursor->addWeek();
        }

        return $keys;
    }

    public function periodLabel(string $key): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $key, $m)) {
            return $key;
        }

        return $this->period === 'month'
            ? "$m[2]/$m[1]"
            : __('reports.week_label', ['week' => (int) $m[2], 'year' => $m[1]]);
    }

    public function render()
    {
        return view('livewire.reports.lead-conversion')->title(__('reports.lead_conversion.title'));
    }
}
