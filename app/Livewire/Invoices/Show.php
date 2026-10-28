<?php

namespace App\Livewire\Invoices;

use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\TransferAlerts;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Chi tiết một hóa đơn (mọi vai trò, phạm vi theo Invoice::visibleTo(); ngoài phạm vi → 404).
 * Quy trình: docs/quy-trinh-bao-gia-thanh-toan.md, giai đoạn D – G.
 *
 *   - Chuyển khoản Chờ thanh toán: hiện QR (HĐ27); hệ thống tự xác nhận khi webhook nhận giao dịch khớp (HĐ29).
 *   - Tiền mặt Chờ thanh toán: "Đã gửi kế toán, chờ xác nhận" (HĐ30); kế toán đối chiếu và bấm Xác nhận (HĐ32).
 *   - Salesperson lập hóa đơn: sửa khi còn Chờ thanh toán (HĐ36); tạo hóa đơn kỳ tiếp khi đơn còn nợ (HĐ38).
 *   - Hóa đơn Quá hạn không nhận thanh toán (quyết định của nhóm); SP lập hóa đơn thay thế.
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public Invoice $invoice;

    // Thông báo sau thao tác: ['tone' => success|info|danger, 'text' => ...]
    public ?array $notice = null;

    public function mount(Invoice $invoice): void
    {
        abort_unless(
            Invoice::query()->visibleTo(Auth::user()->employee)->whereKey($invoice->getKey())->exists(),
            404
        );

        if ($text = session('invoice_notice')) {
            $this->notice = ['tone' => 'success', 'text' => $text];
        }

        $this->loadInvoice($invoice);
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * HĐ32: kế toán duyệt hóa đơn tiền mặt Chờ thanh toán.
     */
    #[Computed]
    public function canApproveCash(): bool
    {
        return app(InvoiceService::class)->canApproveCash($this->invoice, Auth::user()->employee);
    }

    /**
     * Kế toán xác nhận thủ công hóa đơn chuyển khoản bị lệch số tiền (sau khi SP sửa và kế toán đối chiếu sao kê).
     */
    #[Computed]
    public function canConfirmTransfer(): bool
    {
        return app(InvoiceService::class)->canConfirmTransfer($this->invoice, Auth::user()->employee);
    }

    /**
     * HĐ36: Salesperson lập hóa đơn sửa khi còn Chờ thanh toán.
     */
    #[Computed]
    public function canEdit(): bool
    {
        return app(InvoiceService::class)->canEdit($this->invoice, Auth::user()->employee);
    }

    /**
     * HĐ38: Salesperson lập báo giá của đơn tạo hóa đơn kỳ tiếp khi đơn còn phần nợ chưa lập hóa đơn.
     */
    #[Computed]
    public function canCreateNext(): bool
    {
        return $this->invoice->order !== null
            && app(InvoiceService::class)->blockerFor($this->invoice->order, Auth::user()->employee) === null;
    }

    /**
     * HĐ32 → HĐ33: kế toán xác nhận đã nhận đủ tiền mặt.
     */
    public function approveCash(InvoiceService $service): void
    {
        abort_unless($this->canApproveCash, 403);

        $service->approveCash($this->invoice, Auth::user()->employee);

        $this->reloadInvoice();
        $this->notice = ['tone' => 'success', 'text' => __('invoices.detail.cash_approved', ['code' => $this->invoice->invoice_id])];
    }

    /**
     * Lần chuyển khoản lệch số tiền / vào hóa đơn Quá hạn gần nhất (nếu có): hiện cảnh báo cho kế toán và SP.
     */
    #[Computed]
    public function transferAlert(): ?array
    {
        return in_array($this->invoice->status, [Invoice::STATUS_UNPAID, Invoice::STATUS_OVERDUE], true)
            ? TransferAlerts::find($this->invoice->invoice_id) : null;
    }

    public function confirmTransfer(InvoiceService $service): void
    {
        abort_unless($this->canConfirmTransfer, 403);

        $service->confirmTransfer($this->invoice, Auth::user()->employee);

        $this->reloadInvoice();
        $this->notice = ['tone' => 'success', 'text' => __('invoices.detail.transfer_confirmed', ['code' => $this->invoice->invoice_id])];
    }

    public function render()
    {
        return view('livewire.invoices.show')
            ->title(__('invoices.detail.title', ['code' => $this->invoice->invoice_id]));
    }

    private function reloadInvoice(): void
    {
        $this->loadInvoice($this->invoice->fresh());
        // Tính lại mọi nút / cảnh báo theo trạng thái mới (đã thanh toán thì ẩn nút xác nhận, cảnh báo giao dịch lệch)
        unset($this->canApproveCash, $this->canConfirmTransfer, $this->canEdit, $this->canCreateNext, $this->transferAlert);
    }

    private function loadInvoice(Invoice $invoice): void
    {
        $this->invoice = $invoice->load([
            'order.invoices' => fn ($q) => $q->orderBy('payment_installment'),
            // Khung phiếu (x-invoice-receipt): khóa học, voucher, khách + chi nhánh, nhân viên
            'order.quotation.details' => fn ($q) => $q->orderBy('course_id'),
            'order.quotation.details.course',
            'order.quotation.voucher',
            'order.quotation.opportunity.lead.branch',
            'order.quotation.employee',
            'createdBy',
            'accountant',
        ]);
    }
}
