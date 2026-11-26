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

#[Layout('layouts.app')]
class Show extends Component
{
    use StudentTrail;

    public Invoice $invoice;

    public ?array $notice = null;

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

    #[Computed]
    public function canApproveCash(): bool
    {
        return app(InvoiceService::class)->canApproveCash($this->invoice, Auth::user()->employee);
    }

    #[Computed]
    public function canConfirmTransfer(): bool
    {
        return app(InvoiceService::class)->canConfirmTransfer($this->invoice, Auth::user()->employee);
    }

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

    #[Computed]
    public function canCreateNext(): bool
    {
        return $this->invoice->order !== null
            && app(InvoiceService::class)->blockerFor($this->invoice->order, Auth::user()->employee) === null;
    }

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

    public function checkPayment(): void
    {
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

        unset($this->canApproveCash, $this->canConfirmTransfer, $this->canEdit, $this->canDownloadQr, $this->canCreateNext, $this->transferAlert);
    }

    private function loadInvoice(Invoice $invoice): void
    {
        $this->shownStatus = $invoice->status;
        $this->invoice = $invoice->load([
            'order.invoices' => fn ($q) => $q->orderBy('payment_installment'),

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
