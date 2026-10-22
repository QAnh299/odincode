<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\ReportExport;
use App\Models\Employee;
use App\Models\Role;
use App\Models\SalesTeam;
use App\Services\SalesPerformanceReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard & Báo cáo → Hiệu quả Sales Team / Salesperson theo tháng
 * (Giám đốc, Sale Admin: tất cả đội; Sale Leader: đội của mình).
 *
 * Báo cáo xoay quanh CON NGƯỜI (mọi chỉ số đều nhóm theo đội / salesperson), trả lời 3 câu hỏi:
 *   1. Đội nào, ai đạt / trượt chỉ tiêu?          → thẻ KPI, xếp hạng đội, xếp hạng salesperson
 *   2. Tháng sau có ổn không?                      → pipeline đang mở, xu hướng 6 tháng
 *   3. Ai cần hỗ trợ / nhắc nhở?                   → việc cần xử lý (ảnh chụp hiện tại)
 * Công nợ / tiền đã thu (báo cáo Doanh thu & thanh toán), Lead theo nguồn (Chất lượng nguồn Lead) và
 * tỷ lệ Lead → Cơ hội (Hiệu quả chuyển đổi Lead) không nằm ở đây.
 *
 * Ghi công: kết quả (Won, doanh số, học viên) cho người giữ Cơ hội lúc chốt; hoạt động chăm sóc cho người thực hiện.
 * Định nghĩa và thời điểm tính từng chỉ số: xem App\Services\SalesPerformanceReport.
 */
#[Layout('layouts.app')]
class SalesPerformance extends Component
{
    use ReportExport;

    // Màu cố định theo chỉ số / nhóm
    const COLORS = [
        'revenue' => '#2a78d6',
        'target'  => '#c3cad5',
    ];

    // Màu đường của từng đội ở biểu đồ xu hướng tỷ lệ thắng (lặp lại khi nhiều đội hơn)
    const TEAM_COLORS = ['#2a78d6', '#16a34a', '#d97706', '#9333ea', '#0891b2', '#db2777'];

    // Số dòng hiện trong bảng team / salesperson ('all' = tất cả, bảng có thanh cuộn)
    const TOPS = ['5', '10', 'all'];

    // Chỉ số dùng để xếp hạng salesperson → chiều sắp (desc: cao là tốt, asc: thấp là tốt)
    const SORTS = [
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

    /**
     * Cột bảng salesperson theo nhóm → [chiều so sánh với trung bình đội, loại].
     * Chiều: 1 = cao là tốt, -1 = thấp là tốt, 0 = không tô màu.
     * Loại: count = so với trung bình mỗi người trong đội, rate = so với tỷ lệ chung của đội.
     */
    const COLUMN_GROUPS = [
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

    const TREND_MONTHS = 6;

    // Biểu đồ tỷ lệ thắng tách theo đội khi phạm vi có không quá số đội này
    const MAX_TREND_TEAMS = 5;

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

    public string $employeeTop = '10';

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
        $filters = $this->filterValues($this->activeMonth);

        return $this->report()->totals($filters) + ($this->report()->pipeline($filters, 'all')['all'] ?? ['open' => 0, 'open_value' => 0.0]);
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
        return $this->targetsFor($this->activeMonth, $this->filterValues($this->activeMonth));
    }

    /**
     * Tổng chỉ tiêu của phạm vi đang xem: ['revenue' => ?float, 'students' => ?float].
     */
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

    /**
     * Việc cần xử lý hiện tại theo salesperson (ảnh chụp, không theo tháng).
     */
    #[Computed]
    public function alerts(): array
    {
        return $this->report()->alerts($this->filterValues($this->activeMonth));
    }

    /**
     * Chỉ số theo salesperson, kèm pipeline, việc cần xử lý, % đóng góp vào doanh số đội,
     * hạng theo chỉ số đang chọn và so sánh với trung bình đội.
     * Người chưa có số liệu trong tháng vẫn hiện (đang làm việc), xếp cuối và không có hạng.
     */
    #[Computed]
    public function byEmployee(): array
    {
        return $this->employeeRows($this->filterValues($this->activeMonth), true);
    }

    /**
     * $current = gắn pipeline + việc cần xử lý (ảnh chụp hiện tại - chỉ dùng trên trang, không dùng khi xuất kỳ cũ).
     */
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

        // % đóng góp vào doanh số của đội
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
        $rows = $this->employeeView;

        return $this->report()->combine($rows) + [
            'open'       => array_sum(array_column($rows, 'open')),
            'open_value' => array_sum(array_column($rows, 'open_value')),
        ];
    }

    /**
     * Chỉ số theo đội trong phạm vi + chỉ tiêu + pipeline + việc cần xử lý, xếp hạng theo % đạt chỉ tiêu doanh số.
     */
    #[Computed]
    public function byTeam(): array
    {
        $rows = $this->teamRows($this->filterValues($this->activeMonth), $this->byEmployee, $this->targets);

        foreach ($rows as &$row) {
            $members = array_filter($this->byEmployee, fn ($r) => $r['team_id'] === $row['id']);
            $row['open'] = array_sum(array_column($members, 'open'));
            $row['open_value'] = array_sum(array_column($members, 'open_value'));
            $row['flagged'] = array_sum(array_map(fn ($r) => $r['alerts']['flagged'] ?? 0, $members));
        }
        unset($row);

        return $rows;
    }

    /**
     * $people = dòng salesperson cùng kỳ (đếm số thành viên), $targets = chỉ tiêu cùng kỳ theo đội.
     */
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

        // Có chỉ tiêu xếp trước, % đạt giảm dần; rồi doanh số
        usort($rows, fn ($a, $b) => [$b['attain']['revenue'] !== null, $b['attain']['revenue'] ?? 0, $b['revenue']]
            <=> [$a['attain']['revenue'] !== null, $a['attain']['revenue'] ?? 0, $a['revenue']]);
        foreach ($rows as $i => &$row) {
            $row['rank'] = $i + 1;
        }
        unset($row);

        return $rows;
    }

    /**
     * Việc cần xử lý: salesperson có ít nhất 1 việc, nhiều việc nhất trước.
     */
    #[Computed]
    public function alertRows(): array
    {
        $rows = array_values(array_filter($this->byEmployee, fn ($r) => ($r['alerts']['flagged'] ?? 0) > 0));
        usort($rows, fn ($a, $b) => $b['alerts']['flagged'] <=> $a['alerts']['flagged'] ?: strcmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * Link Kanban lọc theo salesperson (chỉ vai trò có trang Kanban: Giám đốc, Sale Leader).
     */
    public function kanbanUrl(array $row): ?string
    {
        $route = Auth::user()->role_name.'.opportunities';
        if (! Route::has($route)) {
            return null;
        }

        return route($route, array_filter([
            'team'     => Auth::user()->role_name === Role::DIRECTOR ? $row['team_id'] : null,
            'employee' => $row['id'],
        ]));
    }

    /**
     * Xu hướng 6 tháng tính đến tháng đang xem.
     */
    #[Computed]
    public function trend(): array
    {
        return $this->trendRows($this->trailingMonths($this->activeMonth), $this->filterValues($this->activeMonth));
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
     * Chỉ số từng tháng (Y-m liên tiếp) kèm tổng chỉ tiêu doanh số của các đội trong phạm vi $filters (đội / salesperson).
     */
    protected function trendRows(array $months, array $filters): array
    {
        $data = $this->report()->aggregate($this->monthsFilter($months, $filters), 'month');

        return array_map(fn ($m) => ($data[$m] ?? $this->report()->derive([])) + [
            'month'  => $m,
            'target' => array_sum(array_column($this->targetsFor($m, $filters), 'revenue')),
        ], $months);
    }

    /**
     * $filters với khoảng ngày phủ các tháng (Y-m liên tiếp).
     */
    protected function monthsFilter(array $months, array $filters): array
    {
        $end = CarbonImmutable::parse(end($months).'-01');

        return ['from' => $months[0].'-01', 'to' => $end->endOfMonth()->toDateString()] + $filters;
    }

    /**
     * Dòng hiện trong bảng: $top dòng đầu (đã xếp hạng) hoặc tất cả.
     */
    public function topRows(array $rows, string $top): array
    {
        return in_array($top, self::TOPS, true) && $top !== 'all' ? array_slice($rows, 0, (int) $top) : $rows;
    }

    /**
     * Biểu đồ: doanh số 6 tháng so với chỉ tiêu, tỷ lệ thắng 6 tháng theo đội.
     */
    #[Computed]
    public function charts(): array
    {
        $label = fn ($m) => __('reports.sales_performance.metric.'.$m);
        $charts = [];

        // Doanh số 6 tháng so với chỉ tiêu
        $trend = $this->trend;
        $months = array_map(fn ($r) => $this->monthLabel($r['month']), $trend);
        $revenueSeries = [['label' => $label('revenue'), 'color' => self::COLORS['revenue'], 'data' => array_column($trend, 'revenue')]];
        if ($this->employee === '' && array_sum(array_column($trend, 'target')) > 0) {
            $revenueSeries[] = ['label' => $label('target'), 'color' => self::COLORS['target'], 'data' => array_column($trend, 'target')];
        }
        $charts['trend_revenue'] = ['type' => 'bar', 'unit' => 'money', 'labels' => $months, 'series' => $revenueSeries];

        // Tỷ lệ thắng 6 tháng: mỗi đội 1 đường khi có 2..MAX_TREND_TEAMS đội (nhiều hơn thì rối) - còn lại 1 đường chung
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
        $charts['trend_close'] = ['type' => 'line', 'unit' => 'percent', 'labels' => $months, 'series' => $series];

        return $charts;
    }

    /* ── Xuất Excel (popup chọn sheet / cột / xem trước - trait ReportExport) ── */

    // Sheet: Sales Team · Salesperson; cột luôn có của từng sheet
    const EXPORT_SHEETS = ['teams', 'employees'];

    const EXPORT_LOCKED = ['teams' => 'team', 'employees' => 'employee'];

    const EXPORT_DEFAULT_SHEETS = ['teams', 'employees'];

    const EXPORT_DEFAULT_COLUMNS = [
        'teams'     => ['team', 'members', 'revenue', 'target_revenue', 'attain_revenue', 'students', 'attain_students', 'won', 'close_rate'],
        'employees' => ['employee', 'revenue', 'share', 'won', 'close_rate', 'avg_order', 'students', 'activity'],
    ];

    // Tiêu chí riêng của popup ('' = tất cả)
    public string $exportTeam = '';

    public string $exportEmployee = '';

    /**
     * Xuất Excel: Giám đốc, Sale Admin.
     */
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

    /**
     * Salesperson chọn được trong popup: theo đội đã chọn.
     */
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

    /**
     * Số liệu của kỳ: salesperson và đội (so với chỉ tiêu cộng dồn các tháng của kỳ).
     */
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

    /**
     * Năm chọn được trong popup: các năm có trong danh sách tháng đánh giá.
     */
    public function exportYears(): array
    {
        return array_values(array_unique(array_map(fn ($m) => (int) substr($m, 0, 4), $this->months)));
    }

    /**
     * Sales Team: dòng tổng cộng (chỉ tiêu cộng các đội có chỉ tiêu) rồi từng đội theo hạng.
     */
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

    /**
     * Salesperson nhóm theo đội (thứ tự đội theo hạng ở sheet Sales Team): dòng tổng cộng, dòng đội (I, II...) = cộng các thành viên.
     */
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
        $order = array_merge(array_values(array_intersect($order, $groups->keys()->all())),
            array_values(array_diff($groups->keys()->all(), $order)));

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

    /* ── Hỗ trợ ───────────────────────────────────────────────────────── */

    public function activeSort(): string
    {
        return isset(self::SORTS[$this->sort]) ? $this->sort : 'revenue';
    }

    /**
     * Cột bảng salesperson (phẳng): [khoá => [chiều, loại]].
     */
    public static function columns(): array
    {
        return array_merge(...array_values(self::COLUMN_GROUPS));
    }

    /**
     * Sắp và gán hạng: người có số liệu xếp trước theo chỉ số chọn (không có giá trị thì xuống cuối),
     * hoà thì xét doanh số, rồi số Lead chốt. Người chưa có số liệu không có hạng.
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

    /**
     * Các đội thuộc phạm vi của bộ lọc: lọc 1 salesperson hoặc không có đội ('__none__') → không có;
     * lọc 1 đội → đội đó; không lọc → tất cả đội.
     */
    protected function filterTeams(array $filters): Collection
    {
        return match (true) {
            (bool) ($filters['employee'] ?? null) => collect(),
            ($filters['team'] ?? null) !== null    => $this->teams->where('team_id', $filters['team'])->values(),
            default                                => $this->teams,
        };
    }

    /**
     * Salesperson của 1 đội (null = tất cả) - để hiện cả người chưa có số liệu.
     */
    protected function salespeople(?string $team): Collection
    {
        return Employee::query()
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALESPERSON))
            ->when($team !== null, fn ($q) => $q->where('team_id', $team))
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'team_id', 'status']);
    }

    /**
     * Chỉ tiêu 1 tháng của các đội thuộc phạm vi bộ lọc (không áp dụng khi lọc 1 salesperson).
     */
    protected function targetsFor(string $month, array $filters): array
    {
        $teams = $this->filterTeams($filters);

        return $teams->isEmpty() ? [] : $this->report()->targets($month, $teams->pluck('team_id')->all());
    }

    /**
     * Chỉ tiêu cộng dồn các tháng của 1 kỳ: [team_id => [chỉ số => tổng]].
     */
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
