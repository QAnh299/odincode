<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\SalesOrder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Số liệu cho báo cáo Phân tích doanh thu & thanh toán.
 *
 * Định nghĩa (thống nhất với báo cáo Hiệu quả Sales và Chất lượng nguồn Lead):
 *   - Doanh thu  = SalesOrders.total_amount, tính theo NGÀY TẠO ĐƠN (đơn tạo trong kỳ).
 *   - Thực thu   = Invoices.payment_amount của hóa đơn Đã thanh toán, tính theo NGÀY THANH TOÁN (tiền vào trong kỳ).
 *                  Không dùng SalesOrders.paid_amount để vẽ theo kỳ: đó là số cộng dồn của đơn, không cho biết tiền vào lúc nào.
 *   - Công nợ    = remaining_amount của đơn Đang xử lý TẠI THỜI ĐIỂM XEM (không theo bộ lọc ngày), chia thành:
 *                  quá hạn (hóa đơn chưa thanh toán đã qua hạn hoặc trạng thái Overdue), đã lập hóa đơn chờ thu (chưa tới hạn),
 *                  chưa lập hóa đơn (= còn lại − tổng hóa đơn chưa thanh toán, không âm).
 *   - Tuổi nợ tính theo due_date so với hôm nay (không dựa vào trạng thái Overdue – chưa có bước chuyển sang Overdue).
 *
 * Gán đơn cho người phụ trách Cơ hội (SalesOrders → Quotations → Opportunities.employee_id), đội = đội hiện tại của
 * người đó, chi nhánh = chi nhánh của khách (Leads.branch_id). Mọi truy vấn cộng tổng bằng SQL.
 *
 * Bộ lọc $f: from, to (Y-m-d | null), branch, team, employee (null = tất cả; '__none__' = không thấy gì).
 */
class RevenueReport
{
    // Nhóm tuổi nợ của hóa đơn chưa thanh toán: số ngày trễ hạn tối đa của nhóm
    const AGING = ['not_due' => 0, 'd7' => 7, 'd30' => 30, 'more' => PHP_INT_MAX];

    const NONE = '__none__';

    /* ── Truy vấn gốc ─────────────────────────────────────────────────── */

    /**
     * Đơn hàng kèm báo giá, cơ hội, khách, người phụ trách – áp phạm vi chi nhánh / đội / nhân viên (không lọc ngày).
     */
    public function orders(array $f): Builder
    {
        return $this->scope(
            DB::table('SalesOrders as so')
                ->join('Quotations as q', 'q.quotation_id', '=', 'so.quotation_id')
                ->join('Opportunities as op', 'op.opportunity_id', '=', 'q.opportunity_id')
                ->join('Leads as l', 'l.lead_id', '=', 'op.lead_id')
                ->join('Employees as e', 'e.employee_id', '=', 'op.employee_id'),
            $f
        );
    }

    /**
     * Đơn tạo trong kỳ (doanh thu).
     */
    public function ordersInPeriod(array $f): Builder
    {
        return $this->between($this->orders($f), 'so.created_at', $f);
    }

    /**
     * Hóa đơn đã thanh toán trong kỳ (thực thu).
     */
    public function paymentsInPeriod(array $f): Builder
    {
        return $this->between(
            $this->orders($f)->join('Invoices as i', 'i.order_id', '=', 'so.order_id')->where('i.status', Invoice::STATUS_PAID),
            'i.payment_date', $f
        );
    }

    /* ── Tổng quan ────────────────────────────────────────────────────── */

    /**
     * Doanh thu của kỳ: số đơn, doanh thu, giảm giá voucher (= tạm tính − tổng đơn), đơn đã thu đủ.
     *
     * @return array{orders: int, revenue: float, discount: float, completed: int}
     */
    public function revenueTotals(array $f): array
    {
        $row = $this->ordersInPeriod($f)
            ->selectRaw('COUNT(*) AS orders, COALESCE(SUM(so.total_amount), 0) AS revenue')
            ->selectRaw('COALESCE(SUM(GREATEST(0, (SELECT COALESCE(SUM(qd.line_total), 0) FROM QuotationDetails qd WHERE qd.quotation_id = so.quotation_id) - so.total_amount)), 0) AS discount')
            ->selectRaw('SUM(so.status = ?) AS completed', [SalesOrder::STATUS_COMPLETED])
            ->first();

        return ['orders' => (int) $row->orders, 'revenue' => (float) $row->revenue, 'discount' => (float) $row->discount, 'completed' => (int) $row->completed];
    }

    /**
     * Thực thu của kỳ: số hóa đơn, số tiền, theo phương thức, số hóa đơn thanh toán đúng hạn.
     *
     * @return array{invoices: int, collected: float, transfer: float, cash: float, transfer_count: int, cash_count: int, on_time: int}
     */
    public function collectionTotals(array $f): array
    {
        $row = $this->paymentsInPeriod($f)
            ->selectRaw('COUNT(*) AS invoices, COALESCE(SUM(i.payment_amount), 0) AS collected')
            ->selectRaw('COALESCE(SUM(CASE WHEN i.payment_method = ? THEN i.payment_amount END), 0) AS transfer', [Invoice::METHOD_BANK_TRANSFER])
            ->selectRaw('COALESCE(SUM(CASE WHEN i.payment_method = ? THEN i.payment_amount END), 0) AS cash', [Invoice::METHOD_CASH])
            ->selectRaw('SUM(i.payment_method = ?) AS transfer_count', [Invoice::METHOD_BANK_TRANSFER])
            ->selectRaw('SUM(i.payment_method = ?) AS cash_count', [Invoice::METHOD_CASH])
            ->selectRaw('SUM(DATE(i.payment_date) <= i.due_date) AS on_time')
            ->first();

        return [
            'invoices' => (int) $row->invoices, 'collected' => (float) $row->collected,
            'transfer' => (float) $row->transfer, 'cash' => (float) $row->cash,
            'transfer_count' => (int) $row->transfer_count, 'cash_count' => (int) $row->cash_count,
            'on_time' => (int) $row->on_time,
        ];
    }

    /**
     * Doanh thu theo kỳ: [khóa kỳ => doanh thu]. $period: month | week.
     */
    public function revenueByPeriod(array $f, string $period): array
    {
        $key = $this->periodSql('so.created_at', $period);

        return $this->ordersInPeriod($f)->selectRaw("$key AS k, SUM(so.total_amount) AS v")->groupByRaw($key)
            ->pluck('v', 'k')->map(fn ($v) => (float) $v)->all();
    }

    /**
     * Thực thu theo kỳ: [khóa kỳ => ['transfer' => ..., 'cash' => ...]].
     */
    public function collectionsByPeriod(array $f, string $period): array
    {
        $key = $this->periodSql('i.payment_date', $period);

        return $this->paymentsInPeriod($f)
            ->selectRaw("$key AS k")
            ->selectRaw('SUM(CASE WHEN i.payment_method = ? THEN i.payment_amount ELSE 0 END) AS transfer', [Invoice::METHOD_BANK_TRANSFER])
            ->selectRaw('SUM(CASE WHEN i.payment_method = ? THEN i.payment_amount ELSE 0 END) AS cash', [Invoice::METHOD_CASH])
            ->groupByRaw($key)->get()
            ->mapWithKeys(fn ($r) => [$r->k => ['transfer' => (float) $r->transfer, 'cash' => (float) $r->cash]])->all();
    }

    /**
     * Khoảng thời gian có dữ liệu trong phạm vi (để chọn nhóm theo tháng / tuần khi không lọc ngày): [min, max] timestamp.
     */
    public function dataSpan(array $f): ?array
    {
        $o = $this->ordersInPeriod($f)->selectRaw('MIN(so.created_at) AS a, MAX(so.created_at) AS b')->first();
        $p = $this->paymentsInPeriod($f)->selectRaw('MIN(i.payment_date) AS a, MAX(i.payment_date) AS b')->first();
        $times = array_filter([$o->a, $o->b, $p->a, $p->b]);

        return $times ? [min(array_map('strtotime', $times)), max(array_map('strtotime', $times))] : null;
    }

    /**
     * Chỉ tiêu doanh thu (TeamSalesTargets loại Revenue) của các tháng $months cho các đội $teams (null = mọi đội).
     */
    public function revenueTarget(array $months, ?array $teams): float
    {
        if (! $months || $teams === []) {
            return 0.0;
        }

        return (float) DB::table('TeamSalesTargets as t')
            ->join('SalesTargets as st', 'st.target_id', '=', 't.target_id')
            ->where('st.target_type', 'Revenue')
            ->whereIn(DB::raw("DATE_FORMAT(t.target_month, '%Y-%m')"), $months)
            ->when($teams !== null, fn ($q) => $q->whereIn('t.team_id', $teams))
            ->sum('t.target_value');
    }

    /**
     * Doanh thu của các tháng $months (theo ngày tạo đơn, bỏ qua bộ lọc ngày) – so với chỉ tiêu tháng.
     */
    public function revenueInMonths(array $f, array $months): float
    {
        if (! $months) {
            return 0.0;
        }

        return (float) $this->orders($f)->whereIn(DB::raw("DATE_FORMAT(so.created_at, '%Y-%m')"), $months)->sum('so.total_amount');
    }

    /* ── Thanh toán ───────────────────────────────────────────────────── */

    /**
     * Cơ cấu hình thức thanh toán của đơn tạo trong kỳ: [Full|Installment|none => ['orders', 'revenue']].
     */
    public function paymentTypeMix(array $f): array
    {
        return $this->ordersInPeriod($f)
            ->selectRaw("COALESCE(NULLIF(so.payment_type, ''), 'none') AS k, COUNT(*) AS orders, SUM(so.total_amount) AS revenue")
            ->groupBy('k')->get()
            ->mapWithKeys(fn ($r) => [$r->k => ['orders' => (int) $r->orders, 'revenue' => (float) $r->revenue]])->all();
    }

    /**
     * Số ngày từ tạo đơn tới khi thu đủ, cho các đơn Hoàn tất có lần thanh toán cuối trong kỳ: danh sách số ngày.
     */
    public function daysToCollect(array $f): array
    {
        $last = DB::table('Invoices')->selectRaw('order_id, MAX(payment_date) AS paid_at')
            ->where('status', Invoice::STATUS_PAID)->groupBy('order_id');

        return $this->between(
            $this->orders($f)->joinSub($last, 'lp', 'lp.order_id', '=', 'so.order_id')
                ->where('so.status', SalesOrder::STATUS_COMPLETED),
            'lp.paid_at', $f
        )->selectRaw('GREATEST(0, DATEDIFF(lp.paid_at, so.created_at)) AS d')->pluck('d')->map(fn ($d) => (int) $d)->all();
    }

    /**
     * Thanh toán đúng hạn / trễ hạn theo phương thức (hóa đơn thanh toán trong kỳ).
     */
    public function punctualityByMethod(array $f): array
    {
        return $this->paymentsInPeriod($f)
            ->selectRaw('i.payment_method AS k, COUNT(*) AS total, SUM(DATE(i.payment_date) <= i.due_date) AS on_time')
            ->selectRaw('AVG(CASE WHEN DATE(i.payment_date) > i.due_date THEN DATEDIFF(i.payment_date, i.due_date) END) AS late_days')
            ->groupBy('i.payment_method')->get()
            ->mapWithKeys(fn ($r) => [$r->k => ['total' => (int) $r->total, 'on_time' => (int) $r->on_time, 'late_days' => $r->late_days === null ? null : (float) $r->late_days]])
            ->all();
    }

    /**
     * Hóa đơn tiền mặt đang chờ kế toán duyệt (Chờ thanh toán + Tiền mặt): [count, amount].
     */
    public function cashQueue(array $f): array
    {
        $row = $this->orders($f)->join('Invoices as i', 'i.order_id', '=', 'so.order_id')
            ->where('i.status', Invoice::STATUS_UNPAID)->where('i.payment_method', Invoice::METHOD_CASH)
            ->selectRaw('COUNT(*) AS c, COALESCE(SUM(i.payment_amount), 0) AS a')->first();

        return ['count' => (int) $row->c, 'amount' => (float) $row->a];
    }

    /* ── Công nợ (tại thời điểm xem) ──────────────────────────────────── */

    /**
     * Hóa đơn chưa thanh toán của các đơn còn nợ, đã phân bổ vào phần còn lại của đơn ('counted').
     * Hóa đơn Overdue không nhận thanh toán nữa và SP lập hóa đơn thay thế cho cùng khoản nợ (InvoiceService),
     * nên mỗi đơn: hóa đơn Unpaid chiếm phần còn lại trước, rồi Overdue (mới nhất trước), không vượt remaining_amount.
     * Hóa đơn Overdue đã được thay thế hết có counted = 0, không tính vào công nợ.
     *
     * @return array<string, array{remaining: float, invoices: list<array>}> theo order_id
     */
    protected function openInvoices(array $f): array
    {
        $today = today()->toDateString();
        $rows = $this->orders($f)->join('Invoices as i', 'i.order_id', '=', 'so.order_id')
            ->where('so.status', SalesOrder::STATUS_IN_PROGRESS)->where('so.remaining_amount', '>', 0)
            ->whereIn('i.status', [Invoice::STATUS_UNPAID, Invoice::STATUS_OVERDUE])
            ->select('so.order_id', 'so.remaining_amount', 'i.invoice_id', 'i.status', 'i.payment_amount', 'i.due_date')
            ->selectRaw('DATEDIFF(?, i.due_date) AS diff', [$today])
            ->orderByRaw('i.status = ? DESC', [Invoice::STATUS_UNPAID])->orderByDesc('i.due_date')->orderByDesc('i.invoice_id')
            ->get();

        $orders = [];
        foreach ($rows as $r) {
            $o = &$orders[$r->order_id];
            $o ??= ['remaining' => (float) $r->remaining_amount, 'left' => (float) $r->remaining_amount, 'invoices' => []];
            $counted = min((float) $r->payment_amount, $o['left']);
            $o['left'] -= $counted;
            // Trễ: hạn trước hôm nay, hoặc trạng thái Overdue (tính ít nhất 1 ngày)
            $late = $r->status === Invoice::STATUS_OVERDUE ? max(1, (int) $r->diff) : max(0, (int) $r->diff);
            $o['invoices'][] = ['invoice_id' => $r->invoice_id, 'counted' => $counted, 'late' => $late];
            unset($o);
        }

        return $orders;
    }

    /**
     * Tổng hợp công nợ: còn lại của đơn Đang xử lý, chia quá hạn / chờ thu / chưa lập hóa đơn (tổng 3 phần = còn lại);
     * tuổi nợ theo hạn hóa đơn.
     *
     * @return array{orders: int, remaining: float, overdue: float, pending: float, uninvoiced: float, aging: array<string, array{count: int, amount: float}>}
     */
    public function debt(array $f): array
    {
        $orders = $this->orders($f)->where('so.status', SalesOrder::STATUS_IN_PROGRESS)->where('so.remaining_amount', '>', 0)
            ->selectRaw('COUNT(*) AS orders, COALESCE(SUM(so.remaining_amount), 0) AS remaining')
            ->first();

        $aging = array_fill_keys(array_keys(self::AGING), ['count' => 0, 'amount' => 0.0]);
        $invoiced = 0.0;
        foreach ($this->openInvoices($f) as $o) {
            foreach ($o['invoices'] as $inv) {
                if ($inv['counted'] <= 0) {
                    continue;
                }
                $bucket = $inv['late'] === 0 ? 'not_due' : $this->agingBucket($inv['late']);
                $aging[$bucket]['count']++;
                $aging[$bucket]['amount'] += $inv['counted'];
                $invoiced += $inv['counted'];
            }
        }

        $pending = $aging['not_due']['amount'];

        return [
            'orders' => (int) $orders->orders, 'remaining' => (float) $orders->remaining,
            'overdue' => $invoiced - $pending, 'pending' => $pending,
            'uninvoiced' => max(0, (float) $orders->remaining - $invoiced),
            'aging' => $aging,
        ];
    }

    /**
     * Đơn còn nợ: mã, khách, người phụ trách, tổng / đã thu / còn lại, hóa đơn chưa thanh toán, số ngày trễ lớn nhất.
     * Sắp: có quá hạn trước (trễ nhiều nhất), rồi còn lại cao nhất.
     */
    public function debtOrders(array $f, ?int $limit = null): array
    {
        $open = $this->openInvoices($f);

        $rows = $this->orders($f)->where('so.status', SalesOrder::STATUS_IN_PROGRESS)->where('so.remaining_amount', '>', 0)
            ->select('so.order_id', 'so.quotation_id', 'so.created_at', 'so.total_amount', 'so.paid_amount', 'so.remaining_amount', 'so.payment_type',
                'l.full_name as customer', 'l.phone', 'e.full_name as employee')
            ->get()->map(function ($r) use ($open) {
                $invoices = array_filter($open[$r->order_id]['invoices'] ?? [], fn ($i) => $i['counted'] > 0);
                $unpaid = array_sum(array_column($invoices, 'counted'));

                return (array) $r + [
                    'unpaid' => $unpaid, 'invoices' => count($invoices),
                    'late' => $invoices ? max(array_column($invoices, 'late')) : 0,
                    'uninvoiced' => max(0, (float) $r->remaining_amount - $unpaid),
                ];
            })
            ->sortBy([['late', 'desc'], ['remaining_amount', 'desc'], ['order_id', 'asc']])
            ->values();

        return ($limit ? $rows->take($limit) : $rows)->all();
    }

    /**
     * Hóa đơn chưa thanh toán đã trễ hạn (hạn trước hôm nay hoặc trạng thái Overdue), trễ nhiều nhất trước.
     * Bỏ hóa đơn Overdue đã có hóa đơn thay thế cho toàn bộ khoản nợ (xem openInvoices).
     */
    public function overdueInvoices(array $f, ?int $limit = null): array
    {
        $today = today()->toDateString();
        $superseded = collect($this->openInvoices($f))->flatMap(fn ($o) => $o['invoices'])
            ->filter(fn ($i) => $i['counted'] <= 0)->pluck('invoice_id')->all();

        return $this->orders($f)->join('Invoices as i', 'i.order_id', '=', 'so.order_id')
            ->where('so.status', SalesOrder::STATUS_IN_PROGRESS)
            ->whereIn('i.status', [Invoice::STATUS_UNPAID, Invoice::STATUS_OVERDUE])
            ->where(fn ($q) => $q->where('i.due_date', '<', $today)->orWhere('i.status', Invoice::STATUS_OVERDUE))
            ->when($superseded, fn ($q) => $q->whereNotIn('i.invoice_id', $superseded))
            ->select('i.invoice_id', 'i.order_id', 'i.status', 'i.payment_method', 'i.payment_amount', 'i.due_date', 'i.payment_installment',
                'l.full_name as customer', 'l.phone', 'e.full_name as employee')
            ->selectRaw('GREATEST(1, DATEDIFF(?, i.due_date)) AS late', [$today])
            ->orderByDesc('late')->orderByDesc('i.payment_amount')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get()->map(fn ($r) => (array) $r)->all();
    }

    /* ── Cơ cấu doanh thu ─────────────────────────────────────────────── */

    /**
     * Doanh thu theo khóa học: phần giảm voucher chia theo tỷ lệ thành tiền từng dòng
     * (dòng × tổng đơn / tạm tính) để cộng các khóa đúng bằng tổng doanh thu. [['name', 'quantity', 'revenue'], ...].
     */
    public function byCourse(array $f): array
    {
        $subtotal = '(SELECT SUM(x.line_total) FROM QuotationDetails x WHERE x.quotation_id = so.quotation_id)';

        return $this->ordersInPeriod($f)
            ->join('QuotationDetails as qd', 'qd.quotation_id', '=', 'so.quotation_id')
            ->join('Courses as c', 'c.course_id', '=', 'qd.course_id')
            ->selectRaw('c.course_id AS id, c.course_name AS name, SUM(qd.quantity) AS quantity, COUNT(DISTINCT so.order_id) AS orders')
            ->selectRaw("SUM(qd.line_total * so.total_amount / NULLIF($subtotal, 0)) AS revenue")
            ->groupBy('c.course_id', 'c.course_name')->orderByDesc('revenue')->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'quantity' => (int) $r->quantity, 'orders' => (int) $r->orders, 'revenue' => round((float) $r->revenue)])
            ->all();
    }

    /**
     * Doanh thu + thực thu theo một chiều: branch | team | employee. [['id', 'name', 'orders', 'revenue', 'collected'], ...].
     */
    public function byDimension(array $f, string $dimension): array
    {
        [$id, $name, $join] = match ($dimension) {
            'branch'   => ['l.branch_id', 'b.branch_name', fn ($q) => $q->leftJoin('Branches as b', 'b.branch_id', '=', 'l.branch_id')],
            'team'     => ['e.team_id', 't.team_name', fn ($q) => $q->leftJoin('SalesTeams as t', 't.team_id', '=', 'e.team_id')],
            default    => ['op.employee_id', 'e.full_name', fn ($q) => $q],
        };

        $revenue = $join($this->ordersInPeriod($f))
            ->selectRaw("$id AS id, MAX($name) AS name, COUNT(*) AS orders, SUM(so.total_amount) AS revenue")
            ->groupBy(DB::raw($id))->get()->keyBy('id');
        $collected = $join($this->paymentsInPeriod($f))
            ->selectRaw("$id AS id, MAX($name) AS name, SUM(i.payment_amount) AS collected")
            ->groupBy(DB::raw($id))->get()->keyBy('id');

        $rows = [];
        foreach ($revenue->keys()->merge($collected->keys())->unique() as $key) {
            $rows[] = [
                'id'        => $key,
                'name'      => $revenue[$key]->name ?? $collected[$key]->name ?? null,
                'orders'    => (int) ($revenue[$key]->orders ?? 0),
                'revenue'   => (float) ($revenue[$key]->revenue ?? 0),
                'collected' => (float) ($collected[$key]->collected ?? 0),
            ];
        }
        usort($rows, fn ($a, $b) => $b['revenue'] <=> $a['revenue'] ?: $b['collected'] <=> $a['collected']);

        return $rows;
    }

    /* ── Danh sách cho xuất Excel ─────────────────────────────────────── */

    public function orderList(array $f): array
    {
        return $this->ordersInPeriod($f)
            ->select('so.order_id', 'so.quotation_id', 'so.created_at', 'so.status', 'so.payment_type', 'so.total_amount', 'so.paid_amount', 'so.remaining_amount',
                'l.full_name as customer', 'e.full_name as employee')
            ->orderBy('so.created_at')->get()->map(fn ($r) => (array) $r)->all();
    }

    public function paymentList(array $f): array
    {
        return $this->paymentsInPeriod($f)
            ->select('i.invoice_id', 'i.order_id', 'i.payment_installment', 'i.payment_method', 'i.payment_amount', 'i.issued_date', 'i.due_date', 'i.payment_date',
                'l.full_name as customer', 'e.full_name as employee')
            ->orderBy('i.payment_date')->get()->map(fn ($r) => (array) $r)->all();
    }

    /* ── Tiện ích ─────────────────────────────────────────────────────── */

    public static function pct(float $part, float $total): ?float
    {
        return $total > 0 ? round($part * 100 / $total, 1) : null;
    }

    public static function median(array $values): ?float
    {
        if (! $values) {
            return null;
        }
        sort($values);
        $n = count($values);

        return $n % 2 ? (float) $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
    }

    /**
     * Khóa kỳ trong SQL, cùng định dạng date('Y-m') / date('o-W') của PHP.
     */
    protected function periodSql(string $column, string $period): string
    {
        return $period === 'week' ? "DATE_FORMAT($column, '%x-%v')" : "DATE_FORMAT($column, '%Y-%m')";
    }

    protected function agingBucket(int $lateDays): string
    {
        foreach (self::AGING as $key => $limit) {
            if ($key !== 'not_due' && $lateDays <= $limit) {
                return $key;
            }
        }

        return 'more';
    }

    protected function between(Builder $q, string $column, array $f): Builder
    {
        return $q->when($f['from'] ?? null, fn ($q, $d) => $q->where($column, '>=', $d.' 00:00:00'))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->where($column, '<=', $d.' 23:59:59'));
    }

    protected function scope(Builder $q, array $f): Builder
    {
        foreach (['team' => 'e.team_id', 'employee' => 'op.employee_id'] as $key => $column) {
            if (($f[$key] ?? null) === self::NONE) {
                return $q->whereRaw('1 = 0');
            }
        }

        return $q->when($f['branch'] ?? null, fn ($q, $v) => $q->where('l.branch_id', $v))
            ->when($f['team'] ?? null, fn ($q, $v) => $q->where('e.team_id', $v))
            ->when($f['employee'] ?? null, fn ($q, $v) => $q->where('op.employee_id', $v));
    }
}
