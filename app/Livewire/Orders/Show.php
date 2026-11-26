<?php

namespace App\Livewire\Orders;

use App\Livewire\Concerns\StudentTrail;
use App\Models\SalesOrder;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
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

        if ($text = session('order_notice')) {
            $this->notice = ['tone' => 'success', 'text' => $text];
        }
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

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
