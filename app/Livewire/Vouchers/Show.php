<?php

namespace App\Livewire\Vouchers;

use App\Models\Role;
use App\Models\Voucher;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Chi tiết một voucher + các báo giá đã áp dụng voucher (trong phạm vi được xem của vai trò).
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public Voucher $voucher;

    public function mount(Voucher $voucher): void
    {
        $this->voucher = $voucher;
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * Sale Leader / Salesperson chỉ thấy báo giá của nhóm / của mình.
     */
    #[Computed]
    public function limitedScope(): bool
    {
        return in_array(Auth::user()->role_name, [Role::SALE_LEADER, Role::SALESPERSON], true);
    }

    /**
     * Báo giá đã áp dụng voucher, kèm tổng tiền trước giảm và tiền được giảm.
     */
    #[Computed]
    public function quotations()
    {
        return $this->voucher->quotations()
            ->visibleTo(Auth::user()->employee)
            ->with(['employee', 'opportunity.lead'])
            ->withSum('details as subtotal', 'line_total')
            ->latest('created_at')
            ->get()
            ->each(function ($quotation) {
                $quotation->subtotal = (float) $quotation->subtotal;
                $quotation->discount = $this->voucher->discountFor($quotation->subtotal);
            });
    }

    public function render()
    {
        return view('livewire.vouchers.show')
            ->title(__('vouchers.detail_title', ['code' => $this->voucher->voucher_id]));
    }
}
