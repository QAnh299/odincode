<?php

namespace App\Livewire\Orders;

use App\Models\SalesOrder;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Chi tiết một đơn hàng (chỉ xem; mọi vai trò, phạm vi theo SalesOrder::visibleTo(); ngoài phạm vi → 404).
 * Trình bày cùng khung phiếu với chi tiết hóa đơn (x-invoice-receipt, type = order): khách, khóa học của báo giá
 * đã xác nhận, tổng tiền / đã thanh toán / còn lại (giá trị ĐÃ LƯU trong SalesOrders) và lịch sử các kỳ hóa đơn. Salesperson lập báo giá được tạo hóa đơn kỳ tiếp (HĐ18).
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public SalesOrder $order;

    public function mount(SalesOrder $order): void
    {
        abort_unless(
            SalesOrder::query()->visibleTo(Auth::user()->employee)->whereKey($order->getKey())->exists(),
            404
        );

        $this->order = $order->load([
            'invoices' => fn ($q) => $q->orderBy('payment_installment'),
            'invoices.createdBy',
            'invoices.accountant',
            'quotation.details' => fn ($q) => $q->orderBy('course_id'),
            'quotation.details.course',
            'quotation.voucher',
            'quotation.employee',
            // Đầu phiếu (x-invoice-receipt): chi nhánh của khách
            'quotation.opportunity.lead.branch',
        ]);
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * HĐ18: giống danh sách đơn hàng và chi tiết báo giá (thiếu email/SĐT thì trang tạo hóa đơn yêu cầu bổ sung).
     */
    #[Computed]
    public function canCreateInvoice(): bool
    {
        return in_array(app(InvoiceService::class)->blockerFor($this->order, Auth::user()->employee), [null, 'missing_profile'], true);
    }

    public function render()
    {
        return view('livewire.orders.show')
            ->title(__('orders.detail.title', ['code' => $this->order->order_id]));
    }
}
