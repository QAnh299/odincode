<?php

namespace App\Livewire\Orders;

use App\Livewire\Concerns\StudentTrail;
use App\Models\SalesOrder;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Chi tiết một đơn hàng (chỉ xem; mọi vai trò, phạm vi theo SalesOrder::visibleTo(); ngoài phạm vi → 404).
 * Bố cục thẻ như chi tiết báo giá: tổng quan thanh toán (tổng tiền / đã thanh toán / còn lại – giá trị ĐÃ LƯU trong SalesOrders),
 * khóa học của báo giá đã xác nhận, lịch sử các kỳ hóa đơn, khách hàng. Salesperson lập báo giá được tạo hóa đơn kỳ tiếp (HĐ18).
 */
#[Layout('layouts.app')]
class Show extends Component
{
    // Breadcrumb nối tiếp khi đi từ trang Chi tiết học viên (?student=)
    use StudentTrail;

    public SalesOrder $order;

    public ?array $notice = null;

    public function mount(SalesOrder $order): void
    {
        abort_unless(
            SalesOrder::query()->visibleTo(Auth::user()->employee)->whereKey($order->getKey())->exists(),
            404
        );

        $this->order = $order->load([
            'invoices' => fn ($q) => $q->orderBy('payment_installment'),
            'invoices.accountant',
            'quotation.details' => fn ($q) => $q->orderBy('course_id'),
            'quotation.details.course',
            'quotation.voucher',
            'quotation.employee',
            'quotation.opportunity.lead',
        ]);

        $this->initStudentTrail($this->order->quotation?->opportunity_id);

        // Vừa xác nhận báo giá (HĐ17) → báo đơn hàng đã được tạo
        if ($text = session('order_notice')) {
            $this->notice = ['tone' => 'success', 'text' => $text];
        }
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
