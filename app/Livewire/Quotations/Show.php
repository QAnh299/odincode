<?php

namespace App\Livewire\Quotations;

use App\Livewire\Concerns\StudentTrail;
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

#[Layout('layouts.app')]
class Show extends Component
{
    use StudentTrail;

    public Quotation $quotation;

    public ?array $notice = null;

    #[Locked]
    public bool $fromOpportunity = false;

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
        $this->initStudentTrail($this->quotation->opportunity_id);
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    #[Computed]
    public function canAct(): bool
    {
        return $this->isAuthor() && $this->quotation->status === Quotation::STATUS_DRAFT;
    }

    #[Computed]
    public function actions(): array
    {
        if (! $this->isAuthor() || $this->quotation->status === Quotation::STATUS_REJECTED) {
            return [];
        }

        $code = $this->quotation->quotation_id;

        if (! $this->canAct) {
            $order = $this->quotation->order;
            $create = $this->canCreateInvoice
                ? [['key' => $order->invoices()->exists() ? 'create_next_invoice' : 'create_invoice', 'icon' => 'plus', 'tone' => 'primary', 'url' => route('salesperson.invoices.create', $order)]]
                : [];

            return [...$create, ['key' => 'export_pdf', 'icon' => 'file', 'tone' => 'ghost', 'url' => route('salesperson.quotations.pdf', $code)]];
        }

        $all = [
            ['key' => 'edit', 'icon' => 'edit', 'tone' => 'ghost', 'url' => route('salesperson.quotations.edit', $code)],
            ['key' => 'export_pdf', 'icon' => 'file', 'tone' => 'ghost', 'url' => route('salesperson.quotations.pdf', $code)],
            ['key' => 'confirm', 'icon' => 'check-circle', 'tone' => 'primary', 'action' => 'confirm', 'confirm' => __('quotations.detail.confirm_confirm', ['code' => $code])],
            ['key' => 'reject', 'icon' => 'x', 'tone' => 'danger', 'action' => 'startReject'],
        ];

        return $all;
    }

    #[Computed]
    public function canCreateInvoice(): bool
    {
        $order = $this->quotation->order;

        return $order !== null
            && in_array(app(InvoiceService::class)->blockerFor($order, Auth::user()->employee), [null, 'missing_profile'], true);
    }

    public function confirm(QuotationService $service)
    {
        abort_unless($this->canAct, 403);

        if ($reason = $service->confirmBlocker($this->quotation, Auth::user()->employee)) {
            $this->notice = ['tone' => 'danger', 'text' => __('quotations.detail.confirm_blocked.'.$reason)];

            return;
        }

        $order = $service->confirm($this->quotation, Auth::user()->employee);

        session()->flash('order_notice', __('quotations.detail.confirmed_done', ['code' => $this->quotation->quotation_id, 'order' => $order->order_id]));

        return $this->redirectRoute('salesperson.orders.show', $order, navigate: false);
    }

    public function startReject(): void
    {
        abort_unless($this->canAct, 403);

        $this->rejectReason = QuotationService::REJECT_DECLINED;
    }

    public function cancelReject(): void
    {
        $this->rejectReason = null;
    }

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
