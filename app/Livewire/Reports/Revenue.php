<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\ReportExport;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesTeam;
use App\Services\RevenueReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard & Báo cáo → Phân tích doanh thu & thanh toán (mọi vai trò).
 *
 * Phạm vi theo vai trò: Giám đốc, Sale Admin, Kế toán xem toàn bộ (lọc được theo chi nhánh / đội / nhân viên);
 * Sale Leader xem đơn của đội mình; Salesperson xem đơn mình phụ trách.
 *
 * Tối đa 6 tab: Tổng quan (việc cần xử lý, nhận định), Thanh toán, Công nợ, Tỷ lệ thu theo tháng, Theo nhân viên, Cơ cấu doanh thu;
 * tab, nhóm việc, nhận định, chỉ tiêu và bảng chi tiết hiện theo vai trò (ROLE_VIEWS).
 * Mỗi tab chỉ tính số liệu khi được mở; thẻ chỉ số bấm được để mở danh sách hóa đơn / đơn hàng tương ứng.
 * Doanh thu theo ngày tạo đơn, thực thu theo ngày thanh toán, công nợ tại thời điểm xem – xem App\Services\RevenueReport.
 * Cùng khung với các báo cáo khác: bộ lọc trên URL, kỳ so sánh, thẻ biểu đồ, popup xuất Excel (trait ReportExport).
 */
#[Layout('layouts.app')]
class Revenue extends Component
{
    use ReportExport;

    const TABS = ['overview', 'payments', 'debt', 'cohort', 'employees', 'structure'];

    // Việc cần xử lý: tiền mặt chờ duyệt quá STALE_CASH_DAYS ngày; đơn chưa lập hóa đơn kỳ tiếp quá STALE_ORDER_DAYS ngày
    const STALE_CASH_DAYS = 1;

    const STALE_ORDER_DAYS = 7;

    // Số dòng hiện trong mỗi nhóm việc cần xử lý
    const TODO_ROWS = 5;

    // Mốc ngày của bảng tỷ lệ thu theo tháng tạo đơn
    const COHORT_HORIZONS = [7, 30, 60];

    // Nhận định "đúng hạn tốt nhất" chỉ xét nhân viên có từ chừng này hóa đơn đã thu trong kỳ
    const MIN_PAID_INVOICES = 3;

    // Cột sắp xếp của bảng theo nhân viên
    const EMPLOYEE_SORTS = ['collected', 'revenue', 'debt', 'on_time', 'overdue_amount'];

    /*
     * Nội dung báo cáo theo vai trò:
     *   - Giám đốc, Sale Admin: chỉ số tổng quan toàn trung tâm (không có danh sách việc, không có bảng chi tiết từng đơn / hóa đơn).
     *   - Kế toán: thu tiền và công nợ – việc cần làm là đối chiếu chuyển khoản, duyệt tiền mặt.
     *   - Sale Leader: đội mình – chỉ tiêu đội, nhân viên trong đội, việc của đội (gia hạn, lập hóa đơn kỳ tiếp).
     *   - Salesperson: đơn mình phụ trách – việc của mình, công nợ của khách mình.
     * tabs: tab hiện; todo: nhóm việc cần xử lý; insights: nhận định; target: chỉ tiêu doanh thu; debt_lists: bảng đơn còn nợ / hóa đơn quá hạn.
     */
    const ROLE_VIEWS = [
        Role::DIRECTOR => [
            'tabs' => ['overview', 'payments', 'debt', 'cohort', 'employees', 'structure'], 'todo' => [],
            'insights' => ['top_course', 'best_on_time', 'top_team', 'top_branch'], 'target' => true, 'debt_lists' => false,
        ],
        Role::SALE_ADMIN => [
            'tabs' => ['overview', 'payments', 'debt', 'cohort', 'employees', 'structure'], 'todo' => [],
            'insights' => ['top_course', 'best_on_time', 'top_team', 'top_branch'], 'target' => true, 'debt_lists' => false,
        ],
        Role::ACCOUNTANT => [
            'tabs' => ['overview', 'payments', 'debt', 'cohort'], 'todo' => ['transfer', 'cash'],
            'insights' => [], 'target' => false, 'debt_lists' => true,
        ],
        Role::SALE_LEADER => [
            'tabs' => ['overview', 'payments', 'debt', 'employees', 'structure'], 'todo' => ['overdue', 'uninvoiced', 'transfer'],
            'insights' => ['top_course', 'best_on_time'], 'target' => true, 'debt_lists' => true,
        ],
        Role::SALESPERSON => [
            'tabs' => ['overview', 'payments', 'debt', 'structure'], 'todo' => ['overdue', 'uninvoiced', 'transfer'],
            'insights' => ['top_course'], 'target' => false, 'debt_lists' => true,
        ],
    ];

    // Màu cố định theo chỉ số (cùng bảng màu các báo cáo khác – LeadSources::PALETTE)
    const COLORS = [
        'revenue'     => '#2a78d6',
        'collected'   => '#1baf7a',
        'target'      => '#c3cad5',
        'transfer'    => '#6250d6',
        'cash'        => '#eda100',
        'Full'        => '#2a78d6',
        'Installment' => '#eb6834',
        'none'        => '#9ca3af',
        'not_due'     => '#1baf7a',
        'd7'          => '#eda100',
        'd30'         => '#eb6834',
        'more'        => '#e34948',
    ];

    // Số dòng hiện trên bảng công nợ (xuất Excel thì đủ)
    const DEBT_ROWS = 15;

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

    // Bảng theo nhân viên: cột đang sắp (giảm dần; riêng % đúng hạn tăng dần để thấy người cần nhắc trước)
    #[Url(as: 'sort', except: 'collected')]
    public string $employeeSort = 'collected';

    const FILTERS = ['from', 'to', 'branch', 'team', 'employee'];

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, $this->tabs(), true) ? $tab : 'overview';
    }

    /**
     * Cấu hình nội dung của vai trò đang xem (ROLE_VIEWS); vai trò lạ dùng cấu hình Salesperson (ít quyền nhất).
     */
    public function roleView(): array
    {
        return self::ROLE_VIEWS[$this->routePrefix] ?? self::ROLE_VIEWS[Role::SALESPERSON];
    }

    /**
     * Tab hiện được theo vai trò, giữ thứ tự TABS.
     */
    public function tabs(): array
    {
        return array_values(array_intersect(self::TABS, $this->roleView()['tabs']));
    }

    public function sortEmployees(string $column): void
    {
        $this->employeeSort = in_array($column, self::EMPLOYEE_SORTS, true) ? $column : 'collected';
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
        return in_array($this->tab, $this->tabs(), true) ? $this->tab : 'overview';
    }

    /* ── Phạm vi theo vai trò (giống báo cáo Chuyển đổi Lead; thêm Kế toán xem toàn bộ) ── */

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    #[Computed]
    public function me(): ?Employee
    {
        return Auth::user()->employee;
    }

    /**
     * Xem toàn bộ, lọc được theo đội: Giám đốc, Sale Admin, Kế toán.
     */
    #[Computed]
    public function canFilterTeam(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN, Role::ACCOUNTANT], true);
    }

    #[Computed]
    public function canFilterEmployee(): bool
    {
        return $this->routePrefix !== Role::SALESPERSON;
    }

    /**
     * Xuất Excel: Giám đốc, Sale Admin, Kế toán.
     */
    #[Computed]
    public function canExport(): bool
    {
        return $this->canFilterTeam;
    }

    #[Computed]
    public function branches(): Collection
    {
        return Branch::query()->orderBy('branch_id')->get(['branch_id', 'branch_name']);
    }

    #[Computed]
    public function teams(): Collection
    {
        return SalesTeam::query()->with('leader:employee_id,branch_id')->orderBy('team_name')->get(['team_id', 'team_name', 'team_leader_id']);
    }

    #[Computed]
    public function branchScope(): ?string
    {
        return $this->canFilterEmployee && $this->branches->contains('branch_id', $this->branch) ? $this->branch : null;
    }

    /**
     * Đội chọn được: đang lọc chi nhánh thì chỉ đội của chi nhánh đó (SalesTeam::branchId).
     */
    #[Computed]
    public function teamOptions(): Collection
    {
        return $this->branchScope === null
            ? $this->teams
            : $this->teams->filter(fn ($team) => $team->branchId() === $this->branchScope)->values();
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

    /**
     * Đội đang xem: Giám đốc / Sale Admin / Kế toán = đội đã chọn ('' = tất cả); Sale Leader = đội của mình
     * (chưa thuộc đội → null, không thấy dữ liệu); Salesperson không lọc theo đội ('').
     */
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

    /**
     * Nhân viên đang xem: Salesperson = chính mình; vai trò khác = người đã chọn (null = tất cả).
     */
    #[Computed]
    public function employeeScope(): ?string
    {
        if (! $this->canFilterEmployee) {
            return $this->me?->employee_id ?? RevenueReport::NONE;
        }

        return $this->employeeOptions->contains('employee_id', $this->employee) ? $this->employee : null;
    }

    public function scopeLabel(): string
    {
        $label = fn ($key, $params = []) => __('reports.revenue.scope.'.$key, $params);

        return match (true) {
            ! $this->canFilterEmployee    => $label('mine'),
            $this->employeeScope !== null => $label('employee', ['name' => $this->employeeOptions->firstWhere('employee_id', $this->employeeScope)?->full_name ?? $this->employeeScope]),
            (bool) $this->teamScope       => $label('team', ['team' => $this->teams->firstWhere('team_id', $this->teamScope)?->team_name ?? $this->teamScope]),
            $this->teamScope === null     => $label('none'),
            default                       => $label('all'),
        };
    }

    /* ── Số liệu chung ────────────────────────────────────────────────── */

    #[Computed]
    public function revenueTotals(): array
    {
        return $this->report()->revenueTotals($this->filterValues());
    }

    #[Computed]
    public function collectionTotals(): array
    {
        return $this->report()->collectionTotals($this->filterValues());
    }

    public function hasData(): bool
    {
        return $this->revenueTotals['orders'] > 0 || $this->collectionTotals['invoices'] > 0 || $this->debt['orders'] > 0;
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
        $days = $from->diffInDays(CarbonImmutable::parse($filters['to'])) + 1;
        $filters['to'] = $from->subDay()->toDateString();
        $filters['from'] = $from->subDays($days)->toDateString();

        return [
            'label'      => CarbonImmutable::parse($filters['from'])->format('d/m').'-'.CarbonImmutable::parse($filters['to'])->format('d/m/Y'),
            'revenue'    => $this->report()->revenueTotals($filters),
            'collection' => $this->report()->collectionTotals($filters),
        ];
    }

    /**
     * Nhóm thời gian của biểu đồ xu hướng: theo tháng nếu kỳ trải từ 4 tháng trở lên, không thì theo tuần.
     */
    #[Computed]
    public function period(): string
    {
        [$start, $end] = $this->range ?? [time(), time()];

        return $this->monthIndex($end) - $this->monthIndex($start) >= 3 ? 'month' : 'week';
    }

    /**
     * Khoảng thời gian của kỳ: theo bộ lọc, thiếu đầu nào thì lấy theo dữ liệu.
     */
    #[Computed]
    public function range(): ?array
    {
        $f = $this->filterValues();
        $span = $this->report()->dataSpan($f);
        $start = $f['from'] ? strtotime($f['from']) : $span[0] ?? null;
        $end = $f['to'] ? strtotime($f['to']) : $span[1] ?? null;

        return $start && $end ? [$start, max($start, $end)] : null;
    }

    /* ── Tab 1: Tổng quan ─────────────────────────────────────────────── */

    #[Computed]
    public function overview(): array
    {
        $f = $this->filterValues();
        $keys = $this->range ? $this->periodKeys(...$this->range) : [];
        $revenue = $this->report()->revenueByPeriod($f, $this->period);
        $collected = $this->report()->collectionsByPeriod($f, $this->period);
        $labels = array_map(fn ($k) => $this->periodLabel($k), $keys);
        $metric = fn ($key) => __('reports.revenue.metric.'.$key);

        return [
            'target' => $this->roleView()['target'] ? $this->target() : null,
            'charts' => [
                'trend' => ['type' => 'bar', 'unit' => 'money', 'labels' => $labels, 'series' => [
                    ['label' => $metric('revenue'), 'color' => self::COLORS['revenue'], 'data' => array_map(fn ($k) => $revenue[$k] ?? 0, $keys)],
                    ['label' => $metric('collected'), 'color' => self::COLORS['collected'], 'data' => array_map(fn ($k) => ($collected[$k]['transfer'] ?? 0) + ($collected[$k]['cash'] ?? 0), $keys)],
                ]],
                'methods' => ['type' => 'stacked', 'unit' => 'money', 'labels' => $labels, 'series' => [
                    ['label' => __('invoices.method.BankTransfer'), 'color' => self::COLORS['transfer'], 'data' => array_map(fn ($k) => $collected[$k]['transfer'] ?? 0, $keys)],
                    ['label' => __('invoices.method.Cash'), 'color' => self::COLORS['cash'], 'data' => array_map(fn ($k) => $collected[$k]['cash'] ?? 0, $keys)],
                ]],
            ],
        ];
    }

    /**
     * Chỉ tiêu doanh thu của các tháng trong kỳ (TeamSalesTargets loại Revenue) so với doanh thu cùng các tháng.
     * Đang lọc theo nhân viên thì không có chỉ tiêu (chỉ tiêu đặt cho đội).
     */
    protected function target(): ?array
    {
        if (! $this->range || $this->employeeScope !== null || $this->teamScope === null) {
            return null;
        }

        $months = [];
        $cursor = CarbonImmutable::createFromTimestamp($this->range[0], config('app.timezone'))->startOfMonth();
        while ($cursor->getTimestamp() <= $this->range[1]) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonth();
        }

        $teams = match (true) {
            (bool) $this->teamScope    => [$this->teamScope],
            $this->branchScope !== null => $this->teamOptions->pluck('team_id')->all(),
            default                    => null,
        };

        $target = $this->report()->revenueTarget($months, $teams);
        if ($target <= 0) {
            return null;
        }

        $actual = $this->report()->revenueInMonths($this->filterValues(), $months);

        return [
            'months' => count($months) === 1 ? $this->monthLabel($months[0]) : $this->monthLabel($months[0]).' – '.$this->monthLabel(end($months)),
            'target' => $target,
            'actual' => $actual,
            'rate'   => RevenueReport::pct($actual, $target),
        ];
    }

    /* ── Tab 2: Thanh toán ────────────────────────────────────────────── */

    #[Computed]
    public function payments(): array
    {
        $f = $this->filterValues();
        $c = $this->collectionTotals;
        $types = $this->report()->paymentTypeMix($f);
        $typeKeys = array_values(array_filter([SalesOrder::PAYMENT_FULL, SalesOrder::PAYMENT_INSTALLMENT, 'none'], fn ($k) => isset($types[$k])));
        $days = $this->report()->daysToCollect($f);
        $punctuality = $this->report()->punctualityByMethod($f);

        return [
            'on_time_rate' => RevenueReport::pct($c['on_time'], $c['invoices']),
            'days'         => ['median' => RevenueReport::median($days), 'avg' => $days ? round(array_sum($days) / count($days), 1) : null, 'count' => count($days)],
            'cash_queue'   => $this->report()->cashQueue($f),
            'transfers'    => $this->transferQueue,
            'punctuality'  => $punctuality,
            'types'        => $types,
            'charts'       => [
                'methods' => ['type' => 'doughnut', 'unit' => 'money',
                    'labels' => [__('invoices.method.BankTransfer'), __('invoices.method.Cash')],
                    'series' => [['label' => __('reports.revenue.metric.collected'), 'data' => [$c['transfer'], $c['cash']],
                        'colors' => [self::COLORS['transfer'], self::COLORS['cash']]]]],
                'types' => ['type' => 'doughnut', 'unit' => 'money',
                    'labels' => array_map(fn ($k) => __('reports.revenue.payment_type.'.$k), $typeKeys),
                    'series' => [['label' => __('reports.revenue.metric.revenue'), 'data' => array_map(fn ($k) => $types[$k]['revenue'], $typeKeys),
                        'colors' => array_map(fn ($k) => self::COLORS[$k], $typeKeys)]]],
            ],
        ];
    }

    /* ── Tab 3: Công nợ (tại thời điểm xem, không theo bộ lọc ngày) ───── */

    #[Computed]
    public function debt(): array
    {
        return $this->report()->debt($this->filterValues(dates: false));
    }

    #[Computed]
    public function debtDetail(): array
    {
        $f = $this->filterValues(dates: false);
        $aging = $this->debt['aging'];
        $keys = array_keys(RevenueReport::AGING);

        return [
            'orders'   => $this->report()->debtOrders($f, self::DEBT_ROWS),
            'invoices' => $this->report()->overdueInvoices($f, self::DEBT_ROWS),
            'chart'    => ['type' => 'bar', 'unit' => 'money', 'labels' => array_map(fn ($k) => __('reports.revenue.aging.'.$k), $keys),
                // Mỗi cột một màu theo mức trễ (Chart.js nhận mảng màu cho backgroundColor)
                'series' => [['label' => __('reports.revenue.debt.amount'), 'data' => array_map(fn ($k) => $aging[$k]['amount'], $keys),
                    'color' => array_map(fn ($k) => self::COLORS[$k], $keys)]]],
        ];
    }

    /* ── Tab 4: Cơ cấu doanh thu ──────────────────────────────────────── */

    #[Computed]
    public function structure(): array
    {
        $f = $this->filterValues();
        $courses = $this->report()->byCourse($f);
        $top = array_slice($courses, 0, 8);

        return [
            'courses'   => $courses,
            'branches'  => $this->canFilterEmployee ? $this->report()->byDimension($f, 'branch') : [],
            'teams'     => $this->canFilterTeam && ! $this->teamScope ? $this->report()->byDimension($f, 'team') : [],
            // Theo nhân viên: xem tab "Theo nhân viên" (thêm công nợ, quá hạn, % đúng hạn)
            'employees' => [],
            'chart'     => ['type' => 'bar_h', 'unit' => 'money', 'labels' => array_column($top, 'name'),
                'series' => [['label' => __('reports.revenue.metric.revenue'), 'color' => self::COLORS['revenue'], 'data' => array_column($top, 'revenue')]]],
        ];
    }

    /* ── Việc cần xử lý + nhận định (tab Tổng quan) ──────────────────── */

    #[Computed]
    public function transferQueue(): array
    {
        return $this->report()->transferQueue($this->filterValues(dates: false));
    }

    /**
     * Các nhóm việc cần xử lý tại thời điểm xem (không theo bộ lọc ngày): [key => ['count', 'amount', 'rows' (tối đa TODO_ROWS), 'url']].
     * Nhóm không có việc thì bỏ.
     */
    #[Computed]
    public function todo(): array
    {
        $f = $this->filterValues(dates: false);
        // Chỉ tính các nhóm việc của vai trò đang xem
        $groups = array_intersect_key([
            'overdue'    => fn () => [array_values(array_filter($this->report()->overdueInvoices($f), fn ($r) => $r['status'] === Invoice::STATUS_OVERDUE)),
                'payment_amount', $this->listUrl('invoices', ['status' => [Invoice::STATUS_OVERDUE]])],
            'uninvoiced' => fn () => [$this->report()->staleUninvoiced($f, self::STALE_ORDER_DAYS), 'uninvoiced', $this->listUrl('orders', ['invoice' => 'needs_invoice'])],
            'transfer'   => fn () => [$this->transferQueue, 'payment_amount', null],
            'cash'       => fn () => [$this->report()->staleCash($f, self::STALE_CASH_DAYS), 'payment_amount', $this->listUrl('invoices', ['cash_pending' => 1])],
        ], array_flip($this->roleView()['todo']));

        $out = [];
        foreach (array_map(fn ($load) => $load(), $groups) as $key => [$rows, $amount, $url]) {
            if ($rows) {
                $out[$key] = ['count' => count($rows), 'amount' => array_sum(array_map(fn ($r) => (float) $r[$amount], $rows)),
                    'rows' => array_slice($rows, 0, self::TODO_ROWS), 'url' => $url];
            }
        }

        return $out;
    }

    /**
     * Nhận định tự động trong kỳ: khóa học doanh thu cao nhất, nhân viên thu đúng hạn tốt nhất,
     * chi nhánh / đội thu tiền nhiều nhất. Không đủ số liệu thì bỏ.
     *
     * Mỗi nhận định hiện như một thẻ chỉ số: nhãn + biểu tượng, giá trị chính (tên), dòng phụ (số liệu).
     *
     * @return list<array{key: string, icon: string, value: string, detail: string}>
     */
    #[Computed]
    public function highlights(): array
    {
        $f = $this->filterValues();
        $money = fn ($v) => number_format((float) $v, 0, ',', '.').' ₫';
        $pct = fn ($v) => number_format((float) $v, 1, ',', '.').'%';
        $detail = fn (string $key, array $params) => __('reports.revenue.insight.'.$key.'_detail', $params);
        $r = $this->revenueTotals;
        $out = [];

        $course = $this->report()->byCourse($f)[0] ?? null;
        if ($course && $course['revenue'] > 0) {
            $out[] = ['key' => 'top_course', 'icon' => 'book', 'value' => $course['name'], 'detail' => $detail('top_course', [
                'amount' => $money($course['revenue']), 'share' => $pct(RevenueReport::pct($course['revenue'], $r['revenue']) ?? 0)])];
        }

        if ($this->canFilterEmployee) {
            $staff = array_filter($this->employeeRows, fn ($e) => $e['paid_invoices'] >= self::MIN_PAID_INVOICES);
            usort($staff, fn ($a, $b) => $b['on_time'] <=> $a['on_time'] ?: $b['paid_invoices'] <=> $a['paid_invoices']);
            if ($best = $staff[0] ?? null) {
                $out[] = ['key' => 'best_on_time', 'icon' => 'check-circle', 'value' => $best['name'],
                    'detail' => $detail('best_on_time', ['rate' => $pct($best['on_time']), 'count' => $best['paid_invoices']])];
            }

            $dimension = $this->canFilterTeam && ! $this->teamScope ? 'team' : 'branch';
            $top = collect($this->report()->byDimension($f, $dimension))->sortByDesc('collected')->first();
            if ($top && $top['collected'] > 0) {
                $out[] = ['key' => 'top_'.$dimension, 'icon' => 'wallet', 'value' => $top['name'] ?? __('reports.revenue.structure.no_team'),
                    'detail' => $detail('top_'.$dimension, ['amount' => $money($top['collected'])])];
            }
        }

        // Chỉ giữ nhận định của vai trò đang xem
        return array_values(array_filter($out, fn ($item) => in_array($item['key'], $this->roleView()['insights'], true)));
    }

    /* ── Tab: Tỷ lệ thu theo tháng tạo đơn ────────────────────────────── */

    #[Computed]
    public function cohort(): array
    {
        return $this->report()->collectionCohort($this->filterValues(), self::COHORT_HORIZONS);
    }

    /* ── Tab: Theo nhân viên ──────────────────────────────────────────── */

    #[Computed]
    public function employeeRows(): array
    {
        return $this->report()->collectionByEmployee($this->filterValues(), $this->filterValues(dates: false));
    }

    /**
     * Bảng theo nhân viên đã sắp theo cột đang chọn, kèm hạng.
     */
    public function sortedEmployees(): array
    {
        $sort = in_array($this->employeeSort, self::EMPLOYEE_SORTS, true) ? $this->employeeSort : 'collected';
        $rows = $this->employeeRows;
        // % đúng hạn: thấp trước (người cần nhắc); người chưa có hóa đơn đã thu xếp cuối
        usort($rows, $sort === 'on_time'
            ? fn ($a, $b) => ($a['on_time'] === null) <=> ($b['on_time'] === null) ?: $a['on_time'] <=> $b['on_time']
            : fn ($a, $b) => $b[$sort] <=> $a[$sort] ?: strcmp($a['name'], $b['name']));

        return $rows;
    }

    /* ── Liên kết ─────────────────────────────────────────────────────── */

    public function invoiceUrl(string $id): string
    {
        return route($this->routePrefix.'.invoices.show', $id);
    }

    public function orderInvoicesUrl(string $orderId): string
    {
        return route($this->routePrefix.'.invoices', ['q' => $orderId]);
    }

    /**
     * Danh sách hóa đơn / đơn hàng đã lọc sẵn, kèm đội và nhân viên đang xem (nếu có).
     */
    public function listUrl(string $page, array $params = []): string
    {
        $scope = array_filter(['team' => $this->canFilterTeam ? $this->teamScope : null,
            'employee' => $this->canFilterEmployee ? $this->employeeScope : null]);

        return route($this->routePrefix.'.'.$page, $params + $scope);
    }

    /**
     * Liên kết của thẻ chỉ số (null = thẻ không bấm được).
     */
    public function kpiUrl(string $key): ?string
    {
        $f = $this->filterValues();
        $range = fn (string $from, string $to) => array_filter([$from => $f['from'], $to => $f['to']]);

        return match ($key) {
            'revenue', 'orders' => $this->listUrl('orders', $range('created_from', 'created_to')),
            'collected'         => $this->listUrl('invoices', ['status' => [Invoice::STATUS_PAID]] + $range('paid_from', 'paid_to')),
            'debt'              => $this->listUrl('orders', ['status' => [SalesOrder::STATUS_IN_PROGRESS]]),
            'overdue'           => $this->listUrl('invoices', ['deadline' => 'overdue']),
            'pending'           => $this->listUrl('invoices', ['status' => [Invoice::STATUS_UNPAID], 'deadline' => 'valid']),
            'uninvoiced'        => $this->listUrl('orders', ['invoice' => 'needs_invoice']),
            'cash_queue'        => $this->listUrl('invoices', ['cash_pending' => 1]),
            'transfer_queue'    => ! in_array('transfer', $this->roleView()['todo'], true) ? null : route($this->routePrefix.'.reports.revenue', array_filter(['branch' => $this->branch, 'team' => $this->team, 'employee' => $this->employee])).'#rv-todo',
            default             => null,
        };
    }

    /* ── Xuất Excel (popup chọn kỳ / sheet / cột / xem trước - trait ReportExport) ── */

    // Sheet: tổng hợp theo tháng · đơn trong kỳ · thực thu · công nợ · hóa đơn quá hạn · theo nhân viên
    const EXPORT_SHEETS = ['summary', 'orders', 'payments', 'debt', 'overdue', 'employees'];

    const EXPORT_LOCKED = ['summary' => 'month', 'orders' => 'order', 'payments' => 'invoice', 'debt' => 'order', 'overdue' => 'invoice', 'employees' => 'name'];

    const EXPORT_DEFAULT_SHEETS = ['summary', 'debt', 'overdue', 'employees'];

    const EXPORT_DEFAULT_COLUMNS = [
        'summary'   => ['month', 'orders', 'revenue', 'collected', 'transfer', 'cash', 'collect_rate'],
        'orders'    => ['order', 'created', 'customer', 'employee', 'payment_type', 'status', 'total', 'paid', 'remaining'],
        'payments'  => ['invoice', 'order', 'installment', 'customer', 'employee', 'method', 'amount', 'due', 'paid_at'],
        'debt'      => ['order', 'customer', 'phone', 'employee', 'total', 'paid', 'remaining', 'unpaid', 'uninvoiced', 'late'],
        'overdue'   => ['invoice', 'order', 'customer', 'phone', 'employee', 'status', 'amount', 'due', 'late'],
        'employees' => ['name', 'orders', 'revenue', 'collected', 'on_time', 'debt', 'overdue_count', 'overdue_amount'],
    ];

    // Tiêu chí riêng của popup ('' = tất cả)
    public string $exportBranch = '';

    public string $exportTeam = '';

    public function exportColumnCatalog(): array
    {
        return [
            'summary'   => ['month' => 'label', 'orders' => 'int', 'revenue' => 'money', 'collected' => 'money', 'transfer' => 'money', 'cash' => 'money', 'collect_rate' => 'percent'],
            'orders'    => ['order' => 'label', 'created' => 'text', 'customer' => 'text', 'employee' => 'text', 'payment_type' => 'text', 'status' => 'text',
                'total' => 'money', 'paid' => 'money', 'remaining' => 'money'],
            'payments'  => ['invoice' => 'label', 'order' => 'text', 'installment' => 'int', 'customer' => 'text', 'employee' => 'text', 'method' => 'text',
                'amount' => 'money', 'due' => 'text', 'paid_at' => 'text', 'on_time' => 'text'],
            'debt'      => ['order' => 'label', 'customer' => 'text', 'phone' => 'text', 'employee' => 'text', 'total' => 'money', 'paid' => 'money',
                'remaining' => 'money', 'unpaid' => 'money', 'uninvoiced' => 'money', 'late' => 'int'],
            'overdue'   => ['invoice' => 'label', 'order' => 'text', 'customer' => 'text', 'phone' => 'text', 'employee' => 'text', 'status' => 'text',
                'method' => 'text', 'amount' => 'money', 'due' => 'text', 'late' => 'int'],
            'employees' => ['name' => 'label', 'orders' => 'int', 'revenue' => 'money', 'collected' => 'money', 'paid_invoices' => 'int', 'on_time' => 'percent',
                'debt' => 'money', 'overdue_count' => 'int', 'overdue_amount' => 'money'],
        ];
    }

    public function exportLabel(string $key, array $replace = []): string
    {
        return __('reports.revenue.export.'.$key, $replace);
    }

    public function exportFilterFields(): array
    {
        $teams = $this->exportBranch === '' ? $this->teams
            : $this->teams->filter(fn ($team) => $team->branchId() === $this->exportBranch);

        return [
            ['model' => 'exportBranch', 'label' => __('reports.branch'), 'all' => __('reports.all_branches'),
                'options' => $this->branches->pluck('branch_name', 'branch_id')->all()],
            ['model' => 'exportTeam', 'label' => __('reports.sales_performance.team'), 'all' => __('reports.sales_performance.all_teams'),
                'options' => $teams->pluck('team_name', 'team_id')->all()],
        ];
    }

    public function updatedExportBranch(): void
    {
        $this->exportTeam = '';
    }

    protected function resetExportFilters(): void
    {
        $this->reset('exportBranch', 'exportTeam');
    }

    protected function exportCriteria(): array
    {
        $branch = $this->branches->firstWhere('branch_id', $this->exportBranch);
        $team = $this->teams->firstWhere('team_id', $this->exportTeam);
        $parts = array_filter([
            $branch ? __('reports.branch').': '.$branch->branch_name : null,
            $team ? __('reports.sales_performance.team').': '.$team->team_name : null,
        ]);

        return [
            ['branch' => $branch?->branch_id, 'team' => $team?->team_id, 'employee' => null],
            $parts ? implode(' · ', $parts) : $this->exportLabel('scope_all'),
        ];
    }

    /**
     * Số liệu cho các sheet cần xuất: phần theo kỳ (đơn, thực thu, theo tháng) dùng kỳ của popup;
     * công nợ, hóa đơn quá hạn tính tại thời điểm xuất.
     */
    protected function exportData(array $period, array $filters, array $sheets): array
    {
        $f = ['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()] + $filters;
        $now = ['from' => null, 'to' => null] + $filters;
        $need = fn (string ...$names) => (bool) array_intersect($names, $sheets);

        return [
            'revenue'   => $this->report()->revenueTotals($f),
            'collected' => $this->report()->collectionTotals($f),
            'months'    => $need('summary') ? [
                'keys'      => $period['months'],
                'revenue'   => $this->report()->revenueByPeriod($f, 'month'),
                'orders'    => $this->report()->ordersInPeriod($f)->selectRaw("DATE_FORMAT(so.created_at, '%Y-%m') AS k, COUNT(*) AS v")
                    ->groupBy('k')->pluck('v', 'k')->all(),
                'collected' => $this->report()->collectionsByPeriod($f, 'month'),
            ] : null,
            'orders'    => $need('orders') ? $this->report()->orderList($f) : [],
            'payments'  => $need('payments') ? $this->report()->paymentList($f) : [],
            'debt'      => $need('debt') ? $this->report()->debtOrders($now) : [],
            'overdue'   => $need('overdue') ? $this->report()->overdueInvoices($now) : [],
            'employees' => $need('employees') ? $this->report()->collectionByEmployee($f, $now) : [],
        ];
    }

    protected function exportRows(string $sheet, array $data): array
    {
        $date = fn ($v) => $v ? CarbonImmutable::parse($v)->format('d/m/Y') : null;
        $method = fn ($m) => $m ? __('invoices.method.'.$m) : null;
        $type = fn ($t) => __('reports.revenue.payment_type.'.(in_array($t, SalesOrder::PAYMENT_TYPES, true) ? $t : 'none'));
        $total = fn (array $values) => ['total', ['_stt' => '', array_key_first($values) => $this->exportLabel('total')] + $values];
        $sum = fn (array $rows, string $key) => array_sum(array_map(fn ($r) => (float) $r[$key], $rows));

        switch ($sheet) {
            case 'summary':
                $m = $data['months'];
                $rev = $data['revenue'];
                $col = $data['collected'];
                $rows = [$total(['month' => null, 'orders' => $rev['orders'], 'revenue' => $rev['revenue'], 'collected' => $col['collected'],
                    'transfer' => $col['transfer'], 'cash' => $col['cash'], 'collect_rate' => RevenueReport::pct($col['collected'], $rev['revenue'])])];
                foreach ($m['keys'] as $k) {
                    $got = ($m['collected'][$k]['transfer'] ?? 0) + ($m['collected'][$k]['cash'] ?? 0);
                    $rows[] = ['item', ['month' => $this->exportLabel('month', ['month' => $this->monthLabel($k)]), 'orders' => (int) ($m['orders'][$k] ?? 0),
                        'revenue' => $m['revenue'][$k] ?? 0, 'collected' => $got, 'transfer' => $m['collected'][$k]['transfer'] ?? 0,
                        'cash' => $m['collected'][$k]['cash'] ?? 0, 'collect_rate' => RevenueReport::pct($got, $m['revenue'][$k] ?? 0)]];
                }

                return $rows;

            case 'orders':
                $rows = $data['orders'];

                return array_merge([$total(['order' => null, 'total' => $sum($rows, 'total_amount'), 'paid' => $sum($rows, 'paid_amount'), 'remaining' => $sum($rows, 'remaining_amount')])],
                    array_map(fn ($r) => ['item', ['order' => $r['order_id'], 'created' => $date($r['created_at']), 'customer' => $r['customer'], 'employee' => $r['employee'],
                        'payment_type' => $type($r['payment_type']), 'status' => __('orders.status.'.$r['status']),
                        'total' => (float) $r['total_amount'], 'paid' => (float) $r['paid_amount'], 'remaining' => (float) $r['remaining_amount']]], $rows));

            case 'payments':
                $rows = $data['payments'];

                return array_merge([$total(['invoice' => null, 'amount' => $sum($rows, 'payment_amount')])],
                    array_map(fn ($r) => ['item', ['invoice' => $r['invoice_id'], 'order' => $r['order_id'], 'installment' => (int) $r['payment_installment'],
                        'customer' => $r['customer'], 'employee' => $r['employee'], 'method' => $method($r['payment_method']), 'amount' => (float) $r['payment_amount'],
                        'due' => $date($r['due_date']), 'paid_at' => $date($r['payment_date']),
                        'on_time' => $this->exportLabel(substr((string) $r['payment_date'], 0, 10) <= (string) $r['due_date'] ? 'on_time_yes' : 'on_time_no')]], $rows));

            case 'debt':
                $rows = $data['debt'];

                return array_merge([$total(['order' => null, 'total' => $sum($rows, 'total_amount'), 'paid' => $sum($rows, 'paid_amount'), 'remaining' => $sum($rows, 'remaining_amount'),
                    'unpaid' => $sum($rows, 'unpaid'), 'uninvoiced' => $sum($rows, 'uninvoiced')])],
                    array_map(fn ($r) => ['item', ['order' => $r['order_id'], 'customer' => $r['customer'], 'phone' => $r['phone'], 'employee' => $r['employee'],
                        'total' => (float) $r['total_amount'], 'paid' => (float) $r['paid_amount'], 'remaining' => (float) $r['remaining_amount'],
                        'unpaid' => (float) $r['unpaid'], 'uninvoiced' => (float) $r['uninvoiced'], 'late' => (int) $r['late']]], $rows));

            case 'overdue':
                $rows = $data['overdue'];

                return array_merge([$total(['invoice' => null, 'amount' => $sum($rows, 'payment_amount')])],
                    array_map(fn ($r) => ['item', ['invoice' => $r['invoice_id'], 'order' => $r['order_id'], 'customer' => $r['customer'], 'phone' => $r['phone'],
                        'employee' => $r['employee'], 'status' => __('invoices.status.'.$r['status']), 'method' => $method($r['payment_method']),
                        'amount' => (float) $r['payment_amount'], 'due' => $date($r['due_date']), 'late' => (int) $r['late']]], $rows));

            default:
                $rows = $data['employees'];
                usort($rows, fn ($a, $b) => $b['collected'] <=> $a['collected'] ?: strcmp($a['name'], $b['name']));
                $paid = array_sum(array_column($rows, 'paid_invoices'));

                return array_merge([$total(['name' => null, 'orders' => array_sum(array_column($rows, 'orders')), 'revenue' => $sum($rows, 'revenue'),
                    'collected' => $sum($rows, 'collected'), 'paid_invoices' => $paid,
                    'on_time' => RevenueReport::pct(array_sum(array_column($rows, 'on_time_count')), $paid),
                    'debt' => $sum($rows, 'debt'), 'overdue_count' => array_sum(array_column($rows, 'overdue_count')), 'overdue_amount' => $sum($rows, 'overdue_amount')])],
                    array_map(fn ($r) => ['item', array_intersect_key($r, $this->exportColumnCatalog()['employees'])], $rows));
        }
    }

    protected function exportSheetMeta(string $sheet, array $data, array $period): array
    {
        $meta = ['unit' => $this->exportLabel('unit')];
        if (in_array($sheet, ['debt', 'overdue'], true)) {
            $meta['notes'] = [$this->exportLabel('note_now', ['date' => now()->format('d/m/Y')])];
        }

        return $meta;
    }

    protected function exportFileName(): string
    {
        return 'bao-cao-doanh-thu-thanh-toan';
    }

    /**
     * Năm chọn được trong popup xuất: từ năm có đơn hàng sớm nhất đến năm hiện tại.
     */
    public function exportYears(): array
    {
        $first = SalesOrder::query()->min('created_at');
        $current = (int) now()->year;

        return range($current, $first ? min($current, (int) CarbonImmutable::parse($first)->year) : $current);
    }

    /* ── Tiện ích ─────────────────────────────────────────────────────── */

    protected function report(): RevenueReport
    {
        return app(RevenueReport::class);
    }

    /**
     * Bộ lọc đã kiểm tra + phạm vi theo vai trò. $dates = false: bỏ lọc ngày (công nợ tính tại thời điểm xem).
     */
    protected function filterValues(bool $dates = true): array
    {
        return [
            'from'     => $dates && $this->validDate($this->from) ? $this->from : null,
            'to'       => $dates && $this->validDate($this->to) ? $this->to : null,
            'branch'   => $this->branchScope,
            'team'     => $this->teamScope === null ? RevenueReport::NONE : ($this->teamScope ?: null),
            'employee' => $this->employeeScope,
        ];
    }

    protected function validDate(string $value): bool
    {
        return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    protected function monthIndex(int $time): int
    {
        return (int) date('Y', $time) * 12 + (int) date('n', $time) - 1;
    }

    /**
     * Các kỳ liên tiếp từ đầu đến cuối khoảng (không bỏ trống kỳ không có số liệu); khóa giống RevenueReport::periodSql.
     */
    protected function periodKeys(int $start, int $end): array
    {
        $month = $this->period === 'month';
        $cursor = CarbonImmutable::createFromTimestamp($start, config('app.timezone'));
        $cursor = $month ? $cursor->startOfMonth() : $cursor->startOfWeek();

        $keys = [];
        while ($cursor->getTimestamp() <= $end) {
            $keys[] = $month ? $cursor->format('Y-m') : $cursor->format('o-W');
            $cursor = $month ? $cursor->addMonth() : $cursor->addWeek();
        }

        return $keys;
    }

    public function periodLabel(string $key): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $key, $m)) {
            return $key;
        }

        return $this->period === 'month' ? "$m[2]/$m[1]" : __('reports.week_label', ['week' => (int) $m[2], 'year' => $m[1]]);
    }

    protected function monthLabel(string $key): string
    {
        return substr($key, 5, 2).'/'.substr($key, 0, 4);
    }

    public function render()
    {
        return view('livewire.reports.revenue')->title(__('reports.revenue.title'));
    }
}
