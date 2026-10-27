<?php

namespace App\Livewire\Quotations;

use App\Models\Opportunity;
use App\Models\Quotation;
use App\Models\Role;
use App\Services\InvoiceService;
use App\Services\QuotationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Chi tiết một báo giá: thông tin chung, khách hàng, cơ hội, khóa học, tổng tiền,
 * voucher, đơn hàng và tiến trình Báo giá → Đơn hàng → Hóa đơn → Thanh toán.
 * Quy trình: docs/quy-trinh-bao-gia-thanh-toan.md.
 * Dùng chung cho Giám đốc, Sale Admin, Sale Leader, Salesperson, Kế toán (Kế toán chỉ xem).
 *
 * Phạm vi xem theo Quotation::visibleTo(): ngoài phạm vi hoặc không tồn tại → 404 (giống Opportunities\Show).
 * Thanh thao tác chỉ hiện với Salesperson lập báo giá và báo giá còn Nháp:
 *   - Chỉnh sửa (HĐ10) → trang sửa; Xuất PDF (HĐ7 – HĐ8) → tải file; Từ chối (HĐ12 – HĐ13).
 *   - Xác nhận (HĐ16 – HĐ17): một giao dịch gồm báo giá → Đã xác nhận + tạo đơn hàng (hình thức chưa xác định).
 * Từ chối = khách không đồng ý: kết thúc cơ hội luôn (Lost / giữ Won); đổi khóa học thì SP sửa báo giá Nháp.
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public Quotation $quotation;

    // Thông báo sau thao tác: ['tone' => success|info, 'text' => ...]
    public ?array $notice = null;

    // Mở từ trang chi tiết cơ hội (?from=opportunity): nút "Quay lại" trở về cơ hội thay vì danh sách báo giá
    #[Locked]
    public bool $fromOpportunity = false;

    // HĐ15: lý do từ chối đang chọn trong khung Từ chối báo giá (null = khung đang đóng)
    public ?string $rejectReason = null;

    public function mount(Quotation $quotation): void
    {
        abort_unless(
            Quotation::query()->visibleTo(Auth::user()->employee)->whereKey($quotation->getKey())->exists(),
            404
        );

        $this->fromOpportunity = request()->query('from') === 'opportunity';

        if ($text = session('quotation_notice')) {
            $this->notice = ['tone' => 'success', 'text' => $text];
        }

        $this->loadQuotation($quotation);
    }

    /**
     * Prefix route theo vai trò, VD: 'director' → route 'director.quotations'.
     */
    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * Có thanh thao tác: Salesperson là chính người lập báo giá và báo giá còn Nháp.
     * Giám đốc, Sale Admin, Sale Leader, Kế toán chỉ xem; báo giá đã Xác nhận / Từ chối thì đã chốt.
     */
    #[Computed]
    public function canAct(): bool
    {
        return $this->isAuthor() && $this->quotation->status === Quotation::STATUS_DRAFT;
    }

    /**
     * Nút trên thanh thao tác (chỉ Salesperson lập báo giá). Nháp – kể cả đã quá hạn: Chỉnh sửa, Xuất PDF, Xác nhận, Từ chối;
     * Đã xác nhận: chỉ Xuất PDF; Từ chối: không có nút. 'url' = link, 'action' = hàm Livewire.
     *
     * @return array<int, array{key: string, icon: string, tone: string, url?: string, action?: string, confirm?: string, disabled?: string}>
     */
    #[Computed]
    public function actions(): array
    {
        if (! $this->isAuthor() || $this->quotation->status === Quotation::STATUS_REJECTED) {
            return [];
        }

        $code = $this->quotation->quotation_id;

        if (! $this->canAct) {
            return [['key' => 'export_pdf', 'icon' => 'file', 'tone' => 'ghost', 'url' => route('salesperson.quotations.pdf', $code)]];
        }

        $all = [
            ['key' => 'edit', 'icon' => 'edit', 'tone' => 'ghost', 'url' => route('salesperson.quotations.edit', $code)],       // HĐ10
            ['key' => 'export_pdf', 'icon' => 'file', 'tone' => 'ghost', 'url' => route('salesperson.quotations.pdf', $code)],  // HĐ7 - HĐ8
            ['key' => 'confirm', 'icon' => 'check-circle', 'tone' => 'primary', 'action' => 'confirm', 'confirm' => __('quotations.detail.confirm_confirm', ['code' => $code])], // HĐ16 - HĐ17
            ['key' => 'reject', 'icon' => 'x', 'tone' => 'danger', 'action' => 'startReject'], // HĐ15: mở khung chọn lý do
        ];

        // Báo giá quá hạn vẫn đủ nút: Chỉnh sửa để gia hạn; Xác nhận bị chặn và nhắc gia hạn (confirmBlocker 'expired')
        return $all;
    }



    /**
     * HĐ18: nút "Tạo hóa đơn" ở khối Đơn hàng – Salesperson lập báo giá, đơn còn phần nợ chưa lập hóa đơn
     * (gồm cả trường hợp khách thiếu email: biểu mẫu hóa đơn cho bổ sung ngay – HĐ20).
     */
    #[Computed]
    public function canCreateInvoice(): bool
    {
        $order = $this->quotation->order;

        return $order !== null
            && in_array(app(InvoiceService::class)->blockerFor($order, Auth::user()->employee), [null, 'missing_profile'], true);
    }

    /**
     * HĐ16 → HĐ17: Salesperson xác nhận báo giá khi khách đồng ý. Không đạt điều kiện thì báo lý do, không đổi gì;
     * đạt thì một giao dịch: báo giá → Đã xác nhận + tạo đơn hàng, rồi gợi ý tạo hóa đơn (HĐ18).
     */
    public function confirm(QuotationService $service): void
    {
        abort_unless($this->canAct, 403);

        if ($reason = $service->confirmBlocker($this->quotation, Auth::user()->employee)) {
            $this->notice = ['tone' => 'danger', 'text' => __('quotations.detail.confirm_blocked.'.$reason)];

            return;
        }

        $order = $service->confirm($this->quotation, Auth::user()->employee);

        $this->reloadQuotation();
        unset($this->canCreateInvoice);
        $this->notice = ['tone' => 'success', 'text' => __('quotations.detail.confirmed_done', ['code' => $this->quotation->quotation_id, 'order' => $order->order_id])];
    }

    /**
     * HĐ15: mở khung Từ chối báo giá để Salesperson chọn lý do (mặc định: khách không đồng ý).
     */
    public function startReject(): void
    {
        abort_unless($this->canAct, 403);

        $this->rejectReason = QuotationService::REJECT_DECLINED;
    }

    public function cancelReject(): void
    {
        $this->rejectReason = null;
    }

    /**
     * HĐ15 – HĐ16: Salesperson từ chối báo giá Nháp: báo giá → Từ chối; cơ hội đang xử lý → Lost (khách không đồng ý)
     * hoặc Archived (khách không phản hồi đến khi quá hạn); đã Won giữ nguyên.
     */
    public function reject(QuotationService $service): void
    {
        abort_unless($this->canAct, 403);
        $this->validate(['rejectReason' => ['required', 'in:'.implode(',', QuotationService::REJECT_REASONS)]]);

        $closed = $service->reject($this->quotation, Auth::user()->employee, $this->rejectReason);
        $status = $this->rejectReason === QuotationService::REJECT_NO_RESPONSE ? 'opportunity_archived' : 'opportunity_lost';

        $this->rejectReason = null;
        $this->reloadQuotation();
        $this->notice = ['tone' => 'success', 'text' => __('quotations.detail.rejected_done', ['code' => $this->quotation->quotation_id])
            .' '.__($closed ? 'quotations.detail.'.$status : 'quotations.detail.opportunity_kept')];
    }

    public function render()
    {
        return view('livewire.quotations.show')
            ->title(__('quotations.detail_title', ['code' => $this->quotation->quotation_id]));
    }

    private function isAuthor(): bool
    {
        return $this->routePrefix === Role::SALESPERSON
            && $this->quotation->employee_id === Auth::user()->employee?->employee_id;
    }

    private function reloadQuotation(): void
    {
        $this->loadQuotation($this->quotation->fresh());
        unset($this->canAct, $this->actions);
    }

    /**
     * Nạp sẵn mọi quan hệ cần hiển thị (tránh N+1); tạm tính nạp bằng loadSum để subtotal() không truy vấn lại.
     */
    private function loadQuotation(Quotation $quotation): void
    {
        $this->quotation = $quotation
            ->load([
                'details' => fn ($q) => $q->orderBy('course_id'),
                'details.course',
                'voucher',
                'order.invoices',
                'employee.team',
                'opportunity.lead',
                'opportunity.stage',
            ])
            ->loadSum('details as details_subtotal', 'line_total');
    }
}
