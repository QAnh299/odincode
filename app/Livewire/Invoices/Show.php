<?php

namespace App\Livewire\Invoices;

use App\Livewire\Concerns\StudentTrail;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\TransferAlerts;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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
    // Breadcrumb nối tiếp khi đi từ trang Chi tiết học viên (?student=)
    use StudentTrail;

    public Invoice $invoice;

    // Thông báo sau thao tác: ['tone' => success|info|danger, 'text' => ...]
    public ?array $notice = null;

    // Trạng thái hóa đơn lúc trang hiển thị lần gần nhất (checkPayment so sánh để biết vừa được thanh toán)
    public ?string $shownStatus = null;

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
        $this->initStudentTrail($this->invoice->order?->quotation?->opportunity_id);
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
    public function canDownloadQr(): bool
    {
        return app(InvoiceService::class)->canDownloadQr($this->invoice, Auth::user()->employee);
    }

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
        if ($this->handledElsewhere()) {
            return;
        }
        abort_unless($this->canApproveCash, 403);

        if (! $this->attempt(fn () => $service->approveCash($this->invoice, Auth::user()->employee))) {
            return;
        }

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
        if ($this->handledElsewhere()) {
            return;
        }
        abort_unless($this->canConfirmTransfer, 403);

        if (! $this->attempt(fn () => $service->confirmTransfer($this->invoice, Auth::user()->employee))) {
            return;
        }

        $this->reloadInvoice();
        $this->notice = ['tone' => 'success', 'text' => __('invoices.detail.transfer_confirmed', ['code' => $this->invoice->invoice_id])];
    }

    /**
     * HĐ29: trang hóa đơn Chờ thanh toán tự kiểm tra lại (wire:poll) để thấy ngay khi webhook ghi nhận chuyển khoản
     * hoặc kế toán khác cùng cơ sở xác nhận tiền mặt,
     * không cần tải lại trang.
     */
    public function checkPayment(): void
    {
        // $invoice được Livewire nạp lại từ CSDL mỗi request, nên so với trạng thái trang đang hiển thị ($shownStatus)
        $status = $this->invoice->status;

        if ($status === $this->shownStatus) {
            $this->skipRender();

            return;
        }

        $this->reloadInvoice();

        if ($status === Invoice::STATUS_PAID) {
            $this->notice = $this->invoice->payment_method === Invoice::METHOD_CASH
                ? ['tone' => 'info', 'text' => $this->paidElsewhereText()]
                : ['tone' => 'success', 'text' => __('invoices.detail.transfer_received', ['code' => $this->invoice->invoice_id])];
        }
    }

    public function render()
    {
        return view('livewire.invoices.show')
            ->title(__('invoices.detail.title', ['code' => $this->invoice->invoice_id]));
    }

    /**
     * Nhiều kế toán cùng cơ sở dùng chung hàng đợi: hóa đơn đã được người khác xác nhận (hoặc hệ thống ghi nhận chuyển khoản)
     * trong lúc trang đang mở → tải lại và báo ai đã xác nhận, thay vì lỗi 403.
     */
    private function handledElsewhere(): bool
    {
        $fresh = $this->invoice->fresh();
        if (! $fresh || $fresh->status === Invoice::STATUS_UNPAID) {
            return false;
        }

        $this->reloadInvoice();
        $this->notice = ['tone' => 'info', 'text' => $fresh->status === Invoice::STATUS_PAID
            ? $this->paidElsewhereText()
            : __('invoices.detail.status_changed', ['code' => $fresh->invoice_id])];

        return true;
    }

    /**
     * Chạy thao tác xác nhận; hai kế toán bấm cùng lúc thì InvoiceService chặn người sau (409) → báo như handledElsewhere.
     */
    private function attempt(callable $action): bool
    {
        try {
            $action();

            return true;
        } catch (HttpExceptionInterface $e) {
            if ($e->getStatusCode() === 409 && $this->handledElsewhere()) {
                return false;
            }
            throw $e;
        }
    }

    private function paidElsewhereText(): string
    {
        $invoice = $this->invoice;
        $time = $invoice->payment_date?->format('H:i d/m/Y') ?? '-';

        return $invoice->accountant
            ? __('invoices.detail.already_confirmed', ['code' => $invoice->invoice_id, 'name' => $invoice->accountant->full_name, 'time' => $time])
            : __('invoices.detail.already_paid', ['code' => $invoice->invoice_id, 'time' => $time]);
    }

    private function reloadInvoice(): void
    {
        $this->loadInvoice($this->invoice->fresh());
        // Tính lại mọi nút / cảnh báo theo trạng thái mới (đã thanh toán thì ẩn nút xác nhận, cảnh báo giao dịch lệch)
        unset($this->canApproveCash, $this->canConfirmTransfer, $this->canEdit, $this->canDownloadQr, $this->canCreateNext, $this->transferAlert);
    }

    private function loadInvoice(Invoice $invoice): void
    {
        $this->shownStatus = $invoice->status;
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
