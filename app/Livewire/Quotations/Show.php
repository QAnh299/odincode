<?php

namespace App\Livewire\Quotations;

use App\Models\Quotation;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Chi tiết một báo giá (chỉ xem): thông tin chung, khách hàng, cơ hội, khóa học, tổng tiền,
 * voucher, đơn hàng và tiến trình Báo giá → Đơn hàng → Hóa đơn → Thanh toán.
 * Quy trình: docs/quy-trinh-bao-gia-thanh-toan.md.
 * Dùng chung cho Giám đốc, Sale Admin, Sale Leader, Salesperson, Kế toán (Kế toán chỉ xem).
 *
 * Phạm vi xem theo Quotation::visibleTo(): ngoài phạm vi hoặc không tồn tại → 404 (giống Opportunities\Show).
 * Thanh thao tác (Chỉnh sửa, Xuất PDF, Xác nhận, Từ chối – HĐ7, HĐ10, HĐ12, HĐ16) chỉ hiện với
 * Salesperson lập báo giá và báo giá còn Nháp; các nút CHƯA có chức năng (làm ở bước sau).
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public Quotation $quotation;

    public function mount(Quotation $quotation): void
    {
        abort_unless(
            Quotation::query()->visibleTo(Auth::user()->employee)->whereKey($quotation->getKey())->exists(),
            404
        );

        // Nạp sẵn mọi quan hệ cần hiển thị (tránh N+1); tạm tính nạp bằng loadSum để subtotal() không truy vấn lại
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
        return $this->routePrefix === Role::SALESPERSON
            && $this->quotation->employee_id === Auth::user()->employee?->employee_id
            && $this->quotation->status === Quotation::STATUS_DRAFT;
    }

    /**
     * Nút trên thanh thao tác theo quy trình. Draft còn hạn: đủ 4 nút; Draft quá hạn: chỉ Từ chối
     * (HĐ8 và HĐ17 yêu cầu chưa hết hạn).
     * 'action' là tên hàm Livewire sẽ gắn wire:click ở bước sau (hiện các nút đều disabled).
     *
     * @return array<int, array{key: string, icon: string, action: string, tone: string}>
     */
    #[Computed]
    public function actions(): array
    {
        if (! $this->canAct) {
            return [];
        }

        $all = [
            ['key' => 'edit', 'icon' => 'edit', 'action' => 'edit', 'tone' => 'ghost'],              // HĐ10
            ['key' => 'export_pdf', 'icon' => 'file', 'action' => 'exportPdf', 'tone' => 'ghost'],  // HĐ7 – HĐ8
            ['key' => 'confirm', 'icon' => 'check-circle', 'action' => 'confirm', 'tone' => 'primary'], // HĐ16 – HĐ17
            ['key' => 'reject', 'icon' => 'x', 'action' => 'reject', 'tone' => 'danger'],           // HĐ12 – HĐ13
        ];

        return $this->quotation->isExpired()
            ? array_values(array_filter($all, fn ($button) => $button['key'] === 'reject'))
            : $all;
    }

    public function render()
    {
        return view('livewire.quotations.show')
            ->title(__('quotations.detail_title', ['code' => $this->quotation->quotation_id]));
    }
}
