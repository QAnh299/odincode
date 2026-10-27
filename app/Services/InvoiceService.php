<?php

namespace App\Services;

use App\Mail\InvoicePaidMail;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\SalesOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Nghiệp vụ hóa đơn và thanh toán, giai đoạn D – G của quy trình (docs/quy-trinh-bao-gia-thanh-toan.md):
 *   - Tạo hóa đơn từ đơn hàng (HĐ18 – HĐ26), sửa hóa đơn Chờ thanh toán (HĐ36): chỉ Salesperson lập báo giá của đơn.
 *   - Chuyển khoản: hệ thống so khớp giao dịch ngân hàng / cổng QR với hóa đơn (HĐ29), KHÔNG có kế toán.
 *   - Tiền mặt: kế toán đối chiếu và duyệt (HĐ32), ghi accountant_id.
 *   - Ghi nhận thanh toán (HĐ33 – HĐ35): hóa đơn Đã thanh toán, cập nhật đơn, cơ hội Won, email hóa đơn cho khách.
 *
 * Quyết định của nhóm: chỉ hóa đơn Unpaid (Chờ thanh toán) nhận thanh toán; hóa đơn Overdue không nhận và không tính
 * vào "đang chờ" khi tính số tiền tối đa của hóa đơn mới (để SP lập hóa đơn thay thế).
 */
class InvoiceService
{
    // Hạn thanh toán mặc định của hóa đơn mới (ngày, tính từ ngày lập)
    const DEFAULT_DUE_DAYS = 7;

    /**
     * HĐ18 – HĐ19: lý do không tạo được hóa đơn (khóa lang invoices.form.blocked.*), null = được tạo.
     * Chỉ Salesperson lập báo giá của đơn; đơn còn Đang xử lý; còn phần nợ chưa lập hóa đơn; khách có SĐT và email.
     */
    public function blockerFor(SalesOrder $order, ?Employee $employee): ?string
    {
        $order->loadMissing('quotation.opportunity.lead');

        return match (true) {
            $employee?->role_name !== Role::SALESPERSON,
            $order->quotation?->employee_id !== $employee->employee_id => 'not_owner',
            $order->status !== SalesOrder::STATUS_IN_PROGRESS => 'completed',
            $this->maxAmount($order) <= 0 => 'fully_invoiced',
            blank($order->quotation?->opportunity?->lead?->phone),
            blank($order->quotation?->opportunity?->lead?->email) => 'missing_profile',
            default => null,
        };
    }

    /**
     * Tổng tiền các hóa đơn đang chờ thanh toán (Unpaid) của đơn, trừ hóa đơn $except (khi sửa).
     */
    public function pendingTotal(SalesOrder $order, ?string $except = null): float
    {
        return (float) $order->invoices()
            ->where('status', Invoice::STATUS_UNPAID)
            ->when($except, fn ($q) => $q->where('invoice_id', '<>', $except))
            ->sum('payment_amount');
    }

    /**
     * HĐ25: số tiền tối đa của một hóa đơn = còn lại − tổng các hóa đơn đang chờ khác.
     */
    public function maxAmount(SalesOrder $order, ?string $except = null): float
    {
        return round(max(0, (float) $order->remaining_amount - $this->pendingTotal($order, $except)), 2);
    }

    /**
     * Hóa đơn đầu tiên của đơn (chưa có hóa đơn nào): SP chọn hình thức Toàn bộ / Trả góp (HĐ21 – HĐ22).
     */
    public function isFirstInvoice(SalesOrder $order): bool
    {
        return ! $order->invoices()->exists();
    }

    /**
     * HĐ21: cần SP chọn hình thức khi đơn chưa xác định hình thức (NULL / rỗng) – thực tế là hóa đơn đầu tiên.
     * Đã có Full / Installment thì không hỏi lại (HĐ38).
     */
    public function needsPaymentType(SalesOrder $order): bool
    {
        return ! in_array($order->payment_type, SalesOrder::PAYMENT_TYPES, true);
    }

    /**
     * Sửa hóa đơn (HĐ36): Salesperson lập hóa đơn, hóa đơn còn Chờ thanh toán.
     */
    public function canEdit(Invoice $invoice, ?Employee $employee): bool
    {
        return $employee?->role_name === Role::SALESPERSON
            && $invoice->created_by_employee_id === $employee->employee_id
            && $invoice->status === Invoice::STATUS_UNPAID;
    }

    /**
     * Kế toán duyệt tiền mặt (HĐ32): hóa đơn Tiền mặt còn Chờ thanh toán.
     */
    public function canApproveCash(Invoice $invoice, ?Employee $employee): bool
    {
        return $employee?->role_name === Role::ACCOUNTANT
            && $invoice->status === Invoice::STATUS_UNPAID
            && $invoice->payment_method === Invoice::METHOD_CASH;
    }

    /**
     * Ngoại lệ của chuyển khoản (chốt 18/10/2026): khách chuyển lệch số tiền nên hệ thống không tự khớp và báo lỗi
     * (TransferAlerts); thiếu thì SP nhắc khách chuyển đủ, kế toán đối chiếu sao kê rồi xác nhận thủ công.
     * Số tiền hóa đơn giữ nguyên; chuyển thừa / hoàn tiền nằm ngoài phạm vi.
     */
    public function canConfirmTransfer(Invoice $invoice, ?Employee $employee): bool
    {
        return $employee?->role_name === Role::ACCOUNTANT
            && $invoice->status === Invoice::STATUS_UNPAID
            && $invoice->payment_method === Invoice::METHOD_BANK_TRANSFER;
    }

    /**
     * HĐ26: một giao dịch tạo hóa đơn Chờ thanh toán (kỳ = kỳ lớn nhất + 1) và ghi hình thức cho đơn nếu là
     * hóa đơn đầu tiên. Toàn bộ: số tiền = còn lại của đơn (khóa). Trả góp: 0 < số tiền ≤ còn lại − đang chờ.
     * Khóa dòng đơn hàng để hai lần bấm cùng lúc không vượt số tiền tối đa.
     *
     * @param  array{payment_type: ?string, amount: float, due_date: string, method: string}  $data
     */
    public function create(SalesOrder $order, Employee $employee, array $data): Invoice
    {
        return DB::transaction(function () use ($order, $employee, $data) {
            $order = SalesOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            abort_if($this->blockerFor($order, $employee) !== null, 409);

            $needsType = $this->needsPaymentType($order);
            $type = $needsType ? $data['payment_type'] : $order->payment_type;
            abort_unless(in_array($type, SalesOrder::PAYMENT_TYPES, true), 409);

            $amount = $this->resolveAmount($order, $type, $data['amount']);

            // HĐ26: ghi hình thức cho đơn nếu chưa có (chọn một lần ở hóa đơn đầu tiên)
            if ($needsType) {
                $order->update(['payment_type' => $type]);
            }

            return Invoice::create([
                'issued_date'            => today()->toDateString(),
                'due_date'               => $data['due_date'],
                'payment_amount'         => $amount,
                'status'                 => Invoice::STATUS_UNPAID,
                'payment_method'         => $data['method'],
                'order_id'               => $order->order_id,
                'payment_installment'    => (int) $order->invoices()->max('payment_installment') + 1,
                'created_by_employee_id' => $employee->employee_id,
            ])->fresh();
        });
    }

    /**
     * HĐ36: SP sửa hóa đơn Chờ thanh toán (số tiền, hạn, phương thức) rồi kiểm tra lại số tiền như HĐ25.
     *
     * @param  array{amount: float, due_date: string, method: string}  $data
     */
    public function update(Invoice $invoice, Employee $employee, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $employee, $data) {
            $order = SalesOrder::query()->whereKey($invoice->order_id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($this->canEdit($invoice, $employee), 409);

            $invoice->update([
                'payment_amount' => $this->resolveAmount($order, $order->payment_type, $data['amount'], $invoice->invoice_id),
                'due_date'       => $data['due_date'],
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * HĐ32: kế toán đối chiếu khớp và duyệt hóa đơn tiền mặt → ghi nhận thanh toán (HĐ33), ghi mã kế toán.
     */
    public function approveCash(Invoice $invoice, Employee $accountant): Invoice
    {
        abort_unless($this->canApproveCash($invoice, $accountant), 409);

        return $this->recordPayment($invoice, $accountant->employee_id, now());
    }

    /**
     * Kế toán xác nhận thủ công hóa đơn chuyển khoản sau khi đối chiếu sao kê → ghi nhận thanh toán.
     * accountant_id vẫn để trống vì CHECK của Invoices (chuyển khoản không có kế toán); người xác nhận ghi vào log 'payments'.
     */
    public function confirmTransfer(Invoice $invoice, Employee $accountant): Invoice
    {
        abort_unless($this->canConfirmTransfer($invoice, $accountant), 409);

        Log::channel('payments')->info('Kế toán xác nhận chuyển khoản thủ công', [
            'invoice' => $invoice->invoice_id, 'amount' => $invoice->payment_amount, 'accountant' => $accountant->employee_id,
        ]);

        return $this->recordPayment($invoice, null, now());
    }

    /**
     * HĐ29: nhận thông báo giao dịch từ ngân hàng / cổng QR, so khớp nội dung (mã hóa đơn) và số tiền với hóa đơn
     * Chuyển khoản còn Chờ thanh toán. Khớp → ghi nhận thanh toán (không có kế toán). Không khớp → không ghi nhận,
     * lưu giao dịch vào log 'payments' (CSDL chưa có bảng giao dịch) và trả về null.
     */
    public function handleBankTransfer(float $amount, string $content, ?string $reference = null, ?Carbon $paidAt = null): ?Invoice
    {
        $code = preg_match('/INV\d+/i', $content, $m) ? strtoupper($m[0]) : null;
        $invoice = $code ? Invoice::query()->find($code) : null;

        $matched = $invoice
            && $invoice->status === Invoice::STATUS_UNPAID
            && $invoice->payment_method === Invoice::METHOD_BANK_TRANSFER
            && abs((float) $invoice->payment_amount - $amount) < 0.01;

        if (! $matched) {
            Log::channel('payments')->warning('Giao dịch chuyển khoản không khớp hóa đơn', [
                'reference' => $reference, 'amount' => $amount, 'content' => $content,
                'invoice'   => $code, 'invoice_status' => $invoice?->status, 'invoice_amount' => $invoice?->payment_amount,
            ]);

            // Đúng hóa đơn chuyển khoản đang chờ nhưng lệch số tiền → báo lỗi cho kế toán và SP (không ghi nhận tiền)
            if ($invoice && $invoice->status === Invoice::STATUS_UNPAID && $invoice->payment_method === Invoice::METHOD_BANK_TRANSFER) {
                TransferAlerts::add($invoice->invoice_id, $amount, $paidAt);
            }

            return null;
        }

        Log::channel('payments')->info('Giao dịch chuyển khoản khớp hóa đơn', ['reference' => $reference, 'invoice' => $code, 'amount' => $amount]);

        return $this->recordPayment($invoice, null, $paidAt ?? now());
    }

    /**
     * HĐ33 – HĐ35 trong một giao dịch: hóa đơn → Đã thanh toán (ngày thanh toán; mã kế toán nếu tiền mặt);
     * đơn tăng đã thanh toán, tính lại còn lại, Completed khi hết nợ (SalesOrder tự tạo học viên); cơ hội chưa Won → Won.
     * Sau khi giao dịch xong: gửi email hóa đơn cho khách (HĐ34) – lỗi gửi chỉ ghi log, không hoàn tác thanh toán.
     */
    public function recordPayment(Invoice $invoice, ?string $accountantId, Carbon $paidAt): Invoice
    {
        $paid = DB::transaction(function () use ($invoice, $accountantId, $paidAt) {
            $order = SalesOrder::query()->whereKey($invoice->order_id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($invoice->status === Invoice::STATUS_UNPAID, 409);

            $isCash = $invoice->payment_method === Invoice::METHOD_CASH;
            abort_if($isCash && ! $accountantId, 409);

            $invoice->update([
                'status'        => Invoice::STATUS_PAID,
                'payment_date'  => $paidAt,
                'accountant_id' => $isCash ? $accountantId : null,
            ]);

            $paidAmount = min((float) $order->total_amount, (float) $order->paid_amount + (float) $invoice->payment_amount);
            $remaining = round((float) $order->total_amount - $paidAmount, 2);

            $order->update([
                'paid_amount'      => $paidAmount,
                'remaining_amount' => $remaining,
                'status'           => $remaining <= 0 ? SalesOrder::STATUS_COMPLETED : SalesOrder::STATUS_IN_PROGRESS,
            ]);

            $opportunity = $order->quotation?->opportunity;
            if ($opportunity && $opportunity->status !== Opportunity::STATUS_WON) {
                $opportunity->update(['status' => Opportunity::STATUS_WON]);
            }

            return $invoice->fresh(['order.quotation.opportunity.lead', 'createdBy', 'accountant']);
        });

        TransferAlerts::forget($paid->invoice_id);
        $this->sendInvoiceEmail($paid);

        return $paid;
    }

    /**
     * HĐ34: gửi thông tin hóa đơn đến email khách hàng của cơ hội. Lỗi gửi: ghi log, không hoàn tác thanh toán.
     */
    public function sendInvoiceEmail(Invoice $invoice): bool
    {
        $email = $invoice->order?->quotation?->opportunity?->lead?->email;

        if (blank($email)) {
            Log::channel('payments')->warning('Không gửi được email hóa đơn: khách chưa có email', ['invoice' => $invoice->invoice_id]);

            return false;
        }

        try {
            Mail::to($email)->send(new InvoicePaidMail($invoice));

            return true;
        } catch (\Throwable $e) {
            Log::channel('payments')->error('Gửi email hóa đơn thất bại', ['invoice' => $invoice->invoice_id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Số tiền hợp lệ của hóa đơn (HĐ25). Toàn bộ: bằng phần còn lại chưa lập hóa đơn (khóa, bỏ qua số SP nhập).
     * Trả góp: 0 < số tiền ≤ tối đa; sai thì 422 (biểu mẫu đã kiểm tra trước, đây là lớp chặn cuối khi có tranh chấp).
     */
    private function resolveAmount(SalesOrder $order, ?string $type, float $amount, ?string $except = null): float
    {
        $max = $this->maxAmount($order, $except);

        if ($type === SalesOrder::PAYMENT_FULL) {
            abort_if($max <= 0, 409);

            return $max;
        }

        abort_unless($amount > 0 && $amount <= $max + 0.001, 422);

        return round($amount, 2);
    }
}
