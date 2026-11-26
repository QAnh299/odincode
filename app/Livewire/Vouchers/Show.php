<?php

namespace App\Livewire\Vouchers;

use App\Models\Role;
use App\Models\Voucher;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Show extends Component
{
    use WithPagination;

    public const PER_PAGE = 10;

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

    #[Computed]
    public function limitedScope(): bool
    {
        return in_array(Auth::user()->role_name, [Role::SALE_LEADER, Role::SALESPERSON], true);
    }

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

    #[Computed]
    public function pagedQuotations(): LengthAwarePaginator
    {
        $all = $this->quotations;
        $page = max(1, min($this->getPage(), (int) ceil(max(1, $all->count()) / self::PER_PAGE)));

        return new LengthAwarePaginator(
            $all->forPage($page, self::PER_PAGE)->values(),
            $all->count(),
            self::PER_PAGE,
            $page,
        );
    }

    public function render()
    {
        return view('livewire.vouchers.show')
            ->title(__('vouchers.detail_title', ['code' => $this->voucher->voucher_id]));
    }
}
