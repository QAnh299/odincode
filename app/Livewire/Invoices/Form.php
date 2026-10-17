<?php

namespace App\Livewire\Invoices;

use App\Models\Employee;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Tạo hóa đơn từ đơn hàng (HĐ18 – HĐ26) và sửa hóa đơn Chờ thanh toán (HĐ36). Chỉ Salesperson.
 * Quy trình: docs/quy-trinh-bao-gia-thanh-toan.md.
 *
 *   - HĐ19 – HĐ20: khách phải có SĐT và email (để gửi email hóa đơn ở HĐ34); thiếu email thì SP bổ sung ngay tại đây.
 *     CSDL không có cột giới tính / ngày sinh của khách nên chưa kiểm tra được hai thông tin này.
 *   - HĐ21 – HĐ22: đơn chưa xác định hình thức → SP chọn Toàn bộ / Trả góp (một lần); đã có thì không hỏi lại.
 *   - HĐ23 – HĐ25: Toàn bộ → số tiền = phần còn lại (khóa); Trả góp → 0 < số tiền ≤ còn lại − đang chờ.
 *   - HĐ26: lưu Chờ thanh toán; chuyển khoản → trang chi tiết hiện QR; tiền mặt → vào hàng đợi của kế toán.
 *
 * Nhận MÃ từ URL rồi tự tìm bản ghi (hai route dùng chung component, xem Quotations\Form).
 */
#[Layout('layouts.app')]
class Form extends Component
{
    #[Locked]
    public string $orderId = '';

    #[Locked]
    public ?string $invoiceId = null;

    public string $paymentType = '';

    public string $amount = '';

    public string $dueDate = '';

    public string $method = '';

    // HĐ20: bổ sung email khách khi thiếu
    public string $customerEmail = '';

    public function mount(?string $order = null, ?string $invoice = null): void
    {
        $service = app(InvoiceService::class);

        if ($invoice !== null) {
            $model = Invoice::query()->findOrFail($invoice);
            abort_unless($service->canEdit($model, $this->viewer()), 403);

            $this->invoiceId = $model->invoice_id;
            $this->orderId = $model->order_id;
            $this->amount = (string) (float) $model->payment_amount;
            $this->dueDate = $model->due_date->toDateString();
            $this->method = (string) $model->payment_method;

            return;
        }

        $this->orderId = SalesOrder::query()->findOrFail((string) $order)->order_id;
        $this->dueDate = today()->addDays(InvoiceService::DEFAULT_DUE_DAYS)->toDateString();
        $this->fillFullAmount();
    }

    /**
     * Chọn hình thức (HĐ22): Toàn bộ → tự điền số tiền = phần còn lại (khóa); Trả góp → để trống cho SP nhập.
     */
    public function updatedPaymentType(): void
    {
        unset($this->type, $this->limits);
        $this->amount = '';
        $this->resetErrorBag('amount');
        $this->fillFullAmount();
    }

    /**
     * Đơn / lựa chọn là Toàn bộ thì số tiền luôn bằng số tối đa (còn lại − đang chờ).
     */
    private function fillFullAmount(): void
    {
        if ($this->type === SalesOrder::PAYMENT_FULL) {
            $this->amount = (string) (int) round($this->limits['max']);
        }
    }

    // ── Dữ liệu hiển thị ───────────────────────────────────────────────

    #[Computed]
    public function order(): SalesOrder
    {
        // Nạp sẵn mọi thứ khung phiếu cần (khóa học, voucher, khách + chi nhánh, nhân viên) – tránh N+1
        return SalesOrder::query()->with([
            'quotation.details' => fn ($q) => $q->orderBy('course_id'),
            'quotation.details.course',
            'quotation.voucher',
            'quotation.employee',
            'quotation.opportunity.lead.branch',
            'invoices' => fn ($q) => $q->orderBy('payment_installment'),
        ])->findOrFail($this->orderId);
    }

    /**
     * Lý do không tạo được (HĐ18 – HĐ19); trang sửa không xét (đã có hóa đơn).
     */
    #[Computed]
    public function blocked(): ?string
    {
        return $this->invoiceId ? null : app(InvoiceService::class)->blockerFor($this->order, $this->viewer());
    }

    /**
     * HĐ21: có hiện hộp chọn hình thức không (chỉ khi tạo mới và đơn chưa có hình thức).
     */
    #[Computed]
    public function needsType(): bool
    {
        return ! $this->invoiceId && app(InvoiceService::class)->needsPaymentType($this->order);
    }

    /**
     * Hình thức đang áp dụng: chọn trên form (hóa đơn đầu) hoặc của đơn.
     */
    #[Computed]
    public function type(): ?string
    {
        $type = $this->needsType ? $this->paymentType : $this->order->payment_type;

        return in_array($type, SalesOrder::PAYMENT_TYPES, true) ? $type : null;
    }

    /**
     * HĐ23: số còn lại, đang chờ (trừ chính hóa đơn khi sửa) và số tiền tối đa.
     *
     * @return array{remaining: float, pending: float, max: float}
     */
    #[Computed]
    public function limits(): array
    {
        $service = app(InvoiceService::class);

        return [
            'remaining' => (float) $this->order->remaining_amount,
            'pending'   => $service->pendingTotal($this->order, $this->invoiceId),
            'max'       => $service->maxAmount($this->order, $this->invoiceId),
        ];
    }

    #[Computed]
    public function nextInstallment(): int
    {
        return $this->invoiceId
            ? (int) Invoice::query()->whereKey($this->invoiceId)->value('payment_installment')
            : (int) $this->order->invoices->max('payment_installment') + 1;
    }

    // ── Thao tác ───────────────────────────────────────────────────────

    /**
     * HĐ20: SP bổ sung email khách (ghi vào hồ sơ Lead) rồi kiểm tra lại HĐ19.
     */
    public function saveCustomerEmail(): void
    {
        $lead = $this->order->quotation?->opportunity?->lead;
        abort_unless($lead && $this->blocked === 'missing_profile'
            && $this->order->quotation->employee_id === $this->viewer()?->employee_id, 403);

        $this->validate(['customerEmail' => ['required', 'email:rfc', 'max:254']], [], ['customerEmail' => __('invoices.form.customer_email')]);

        $lead->update(['email' => trim($this->customerEmail)]);
        unset($this->order, $this->blocked);
    }

    /**
     * HĐ24 → HĐ25 → HĐ26: kiểm tra rồi lưu hóa đơn Chờ thanh toán, mở trang chi tiết (QR hoặc chờ kế toán).
     */
    public function save(InvoiceService $service)
    {
        abort_if($this->blocked !== null, 403);

        $limits = $this->limits;
        $isFull = $this->type === SalesOrder::PAYMENT_FULL;

        $this->validate([
            'paymentType' => $this->needsType ? ['required', Rule::in(SalesOrder::PAYMENT_TYPES)] : ['nullable'],
            'amount'      => $isFull ? ['nullable'] : ['required', 'numeric', 'gt:0', 'lte:'.$limits['max']],
            'dueDate'     => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->issuedDate()],
            'method'      => ['required', Rule::in(Invoice::METHODS)],
        ], [
            'amount.lte' => __('invoices.form.amount_max', ['max' => number_format($limits['max'], 0, ',', '.').' ₫']),
        ], [
            'paymentType' => __('invoices.payment_type_label'),
            'amount'      => __('invoices.amount'),
            'dueDate'     => __('invoices.due_date'),
            'method'      => __('invoices.method_label'),
        ]);

        $data = [
            'payment_type' => $this->paymentType ?: null,
            'amount'       => $isFull ? $limits['max'] : (float) $this->amount,
            'due_date'     => $this->dueDate,
            'method'       => $this->method,
        ];

        $invoice = $this->invoiceId
            ? $service->update(Invoice::query()->findOrFail($this->invoiceId), $this->viewer(), $data)
            : $service->create($this->order, $this->viewer(), $data);

        $key = match (true) {
            $this->invoiceId !== null                        => 'invoices.form.updated',
            $invoice->payment_method === Invoice::METHOD_CASH => 'invoices.form.created_cash',
            default                                          => 'invoices.form.created_transfer',
        };
        session()->flash('invoice_notice', __($key, ['code' => $invoice->invoice_id]));

        return $this->redirectRoute('salesperson.invoices.show', $invoice, navigate: false);
    }

    public function render()
    {
        $title = $this->invoiceId
            ? __('invoices.form.edit_title', ['code' => $this->invoiceId])
            : __('invoices.form.create_title', ['code' => $this->orderId]);

        return view('livewire.invoices.form')->title($title);
    }

    /**
     * Ngày lập: tạo mới = hôm nay; sửa = ngày lập của hóa đơn (hạn không được trước ngày này – CHECK trong odin.sql).
     */
    public function issuedDate(): string
    {
        return $this->invoiceId
            ? (string) Invoice::query()->whereKey($this->invoiceId)->value('issued_date')
            : today()->toDateString();
    }

    private function viewer(): ?Employee
    {
        return Auth::user()->employee;
    }
}
