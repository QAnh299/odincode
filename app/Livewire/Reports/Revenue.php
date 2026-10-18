<?php

namespace App\Livewire\Reports;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesTeam;
use App\Services\ReportExcelWriter;
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
 * 4 tab: Tổng quan, Thanh toán, Công nợ, Cơ cấu doanh thu. Mỗi tab chỉ tính số liệu khi được mở.
 * Doanh thu theo ngày tạo đơn, thực thu theo ngày thanh toán, công nợ tại thời điểm xem – xem App\Services\RevenueReport.
 * Cùng khung với báo cáo Chuyển đổi Lead (bộ lọc trên URL, kỳ so sánh, thẻ biểu đồ, xuất Excel).
 */
#[Layout('layouts.app')]
class Revenue extends Component
{
    const TABS = ['overview', 'payments', 'debt', 'structure'];

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

    const FILTERS = ['from', 'to', 'branch', 'team', 'employee'];

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
            'target' => $this->target(),
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
            'employees' => $this->canFilterEmployee ? $this->report()->byDimension($f, 'employee') : [],
            'chart'     => ['type' => 'bar_h', 'unit' => 'money', 'labels' => array_column($top, 'name'),
                'series' => [['label' => __('reports.revenue.metric.revenue'), 'color' => self::COLORS['revenue'], 'data' => array_column($top, 'revenue')]]],
        ];
    }

    /* ── Xuất Excel ───────────────────────────────────────────────────── */

    public function export(ReportExcelWriter $writer)
    {
        abort_unless($this->canExport, 403);

        $f = $this->filterValues();
        $c = fn ($key) => __('reports.revenue.export.'.$key);
        $method = fn ($m) => $m ? __('invoices.method.'.$m) : null;
        $type = fn ($t) => __('reports.revenue.payment_type.'.(in_array($t, SalesOrder::PAYMENT_TYPES, true) ? $t : 'none'));
        $time = fn ($v) => $v ? strtotime($v) : null;

        $debtOrders = $this->report()->debtOrders($this->filterValues(dates: false));
        $overdue = $this->report()->overdueInvoices($this->filterValues(dates: false));

        $path = $writer->write([
            ['name' => $c('sheet_orders'), 'columns' => [[$c('order'), 'text'], [$c('created'), 'datetime'], [$c('customer'), 'text'], [$c('employee'), 'text'],
                [$c('payment_type'), 'text'], [$c('status'), 'text'], [$c('total'), 'money'], [$c('paid'), 'money'], [$c('remaining'), 'money']],
                'rows' => array_map(fn ($r) => [$r['order_id'], $time($r['created_at']), $r['customer'], $r['employee'], $type($r['payment_type']),
                    __('orders.status.'.$r['status']), (float) $r['total_amount'], (float) $r['paid_amount'], (float) $r['remaining_amount']], $this->report()->orderList($f))],
            ['name' => $c('sheet_payments'), 'columns' => [[$c('invoice'), 'text'], [$c('order'), 'text'], [$c('installment'), 'int'], [$c('customer'), 'text'],
                [$c('employee'), 'text'], [$c('method'), 'text'], [$c('amount'), 'money'], [$c('due'), 'datetime'], [$c('paid_at'), 'datetime']],
                'rows' => array_map(fn ($r) => [$r['invoice_id'], $r['order_id'], (int) $r['payment_installment'], $r['customer'], $r['employee'],
                    $method($r['payment_method']), (float) $r['payment_amount'], $time($r['due_date']), $time($r['payment_date'])], $this->report()->paymentList($f))],
            ['name' => $c('sheet_debt'), 'columns' => [[$c('order'), 'text'], [$c('customer'), 'text'], [$c('phone'), 'text'], [$c('employee'), 'text'],
                [$c('total'), 'money'], [$c('paid'), 'money'], [$c('remaining'), 'money'], [$c('unpaid_invoices'), 'money'], [$c('uninvoiced'), 'money'], [$c('late_days'), 'int']],
                'rows' => array_map(fn ($r) => [$r['order_id'], $r['customer'], $r['phone'], $r['employee'], (float) $r['total_amount'], (float) $r['paid_amount'],
                    (float) $r['remaining_amount'], (float) $r['unpaid'], (float) $r['uninvoiced'], (int) $r['late']], $debtOrders)],
            ['name' => $c('sheet_overdue'), 'columns' => [[$c('invoice'), 'text'], [$c('order'), 'text'], [$c('customer'), 'text'], [$c('phone'), 'text'],
                [$c('employee'), 'text'], [$c('method'), 'text'], [$c('amount'), 'money'], [$c('due'), 'datetime'], [$c('late_days'), 'int']],
                'rows' => array_map(fn ($r) => [$r['invoice_id'], $r['order_id'], $r['customer'], $r['phone'], $r['employee'], $method($r['payment_method']),
                    (float) $r['payment_amount'], $time($r['due_date']), (int) $r['late']], $overdue)],
        ]);

        return response()->download($path, 'bao-cao-doanh-thu-thanh-toan-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend();
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
