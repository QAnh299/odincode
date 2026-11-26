<?php

namespace App\Services;

use App\Mail\InvoicePaidMail;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\SalesOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InvoiceService
{
    public const DEFAULT_DUE_DAYS = 7;

    public function blockerFor(SalesOrder $order, ?Employee $employee): ?string
    {
        $order->loadMissing('quotation.opportunity.lead');

        return match (true) {
            $employee?->role_name !== Role::SALESPERSON,
            $order->quotation?->employee_id !== $employee->employee_id => 'not_owner',
            $order->status !== SalesOrder::STATUS_IN_PROGRESS => 'completed',
            $this->maxAmount($order) <= 0 => 'fully_invoiced',
            blank($order->quotation?->opportunity?->lead?->phone),
            blank($order->quotation?->opportunity?->lead?->email) => 'missing_profile',
            default => null,
        };
    }

    public function pendingTotal(SalesOrder $order, ?string $except = null): float
    {
        return (float) $order->invoices()
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_OVERDUE])
            ->when($except, fn ($q) => $q->where('invoice_id', '<>', $except))
            ->sum('payment_amount');
    }

    public function maxAmount(SalesOrder $order, ?string $except = null): float
    {
        return round(max(0, (float) $order->remaining_amount - $this->pendingTotal($order, $except)), 2);
    }

    public function isFirstInvoice(SalesOrder $order): bool
    {
        return ! $order->invoices()->exists();
    }

    public function needsPaymentType(SalesOrder $order): bool
    {
        return ! in_array($order->payment_type, SalesOrder::PAYMENT_TYPES, true);
    }

    public function canEdit(Invoice $invoice, ?Employee $employee): bool
    {
        return $employee?->role_name === Role::SALESPERSON
            && $invoice->created_by_employee_id === $employee->employee_id
            && in_array($invoice->status, [Invoice::STATUS_UNPAID, Invoice::STATUS_OVERDUE], true);
    }

    public function canDownloadQr(Invoice $invoice, ?Employee $employee): bool
    {
        return $employee?->role_name === Role::SALESPERSON
            && $invoice->created_by_employee_id === $employee->employee_id
            && $invoice->status === Invoice::STATUS_UNPAID
            && $invoice->payment_method === Invoice::METHOD_BANK_TRANSFER;
    }

    public function canApproveCash(Invoice $invoice, ?Employee $employee): bool
    {
        return $employee?->role_name === Role::ACCOUNTANT
            && $invoice->status === Invoice::STATUS_UNPAID
            && $invoice->payment_method === Invoice::METHOD_CASH
            && $this->inAccountantScope($invoice, $employee);
    }

    public function canConfirmTransfer(Invoice $invoice, ?Employee $employee): bool
    {
        return $employee?->role_name === Role::ACCOUNTANT
            && $invoice->status === Invoice::STATUS_UNPAID
            && $invoice->payment_method === Invoice::METHOD_BANK_TRANSFER
            && $this->inAccountantScope($invoice, $employee);
    }

    private function inAccountantScope(Invoice $invoice, Employee $accountant): bool
    {
        return Invoice::query()->visibleTo($accountant)->whereKey($invoice->getKey())->exists();
    }

    public function create(SalesOrder $order, Employee $employee, array $data): Invoice
    {
        return DB::transaction(function () use ($order, $employee, $data) {
            $order = SalesOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            abort_if($this->blockerFor($order, $employee) !== null, 409);

            $needsType = $this->needsPaymentType($order);
            $type = $needsType ? $data['payment_type'] : $order->payment_type;
            abort_unless(in_array($type, SalesOrder::PAYMENT_TYPES, true), 409);

            $amount = $this->resolveAmount($order, $type, $data['amount']);

            if ($needsType) {
                $order->update(['payment_type' => $type]);
            }

            return Invoice::create([
                'issued_date'            => today()->toDateString(),
                'due_date'               => $data['due_date'],
                'payment_amount'         => $amount,
                'status'                 => Invoice::STATUS_UNPAID,
                'payment_method'         => $data['method'],
                'order_id'               => $order->order_id,
                'payment_installment'    => (int) $order->invoices()->max('payment_installment') + 1,
                'created_by_employee_id' => $employee->employee_id,
            ])->fresh();
        });
    }

    public function canChangePaymentType(Invoice $invoice): bool
    {
        return Invoice::query()->where('order_id', $invoice->order_id)->count() === 1;
    }

    public function isTransferLocked(Invoice $invoice): bool
    {
        return TransferAlerts::find($invoice->invoice_id) !== null;
    }

    public function update(Invoice $invoice, Employee $employee, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $employee, $data) {
            $order = SalesOrder::query()->whereKey($invoice->order_id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($this->canEdit($invoice, $employee), 409);

            $reopen = $invoice->status === Invoice::STATUS_OVERDUE;
            abort_if($reopen && $data['due_date'] < today()->toDateString(), 422);
            $status = $reopen ? ['status' => Invoice::STATUS_UNPAID] : [];

            if ($this->isTransferLocked($invoice)) {
                $invoice->update(['due_date' => $data['due_date']] + $status);

                return $invoice->fresh();
            }

            abort_unless(in_array($data['method'], Invoice::METHODS, true), 422);

            $type = $order->payment_type;
            if ($this->canChangePaymentType($invoice) && in_array($data['payment_type'] ?? null, SalesOrder::PAYMENT_TYPES, true)) {
                $type = $data['payment_type'];
                $order->update(['payment_type' => $type]);
            }

            $invoice->update([
                'payment_amount' => $this->resolveAmount($order, $type, $data['amount'], $invoice->invoice_id),
                'due_date'       => $data['due_date'],
                'payment_method' => $data['method'],
            ] + $status);

            return $invoice->fresh();
        });
    }

    public function approveCash(Invoice $invoice, Employee $accountant): Invoice
    {
        abort_unless($this->canApproveCash($invoice, $accountant), 409);

        return $this->recordPayment($invoice, $accountant->employee_id, now());
    }

    public function confirmTransfer(Invoice $invoice, Employee $accountant): Invoice
    {
        abort_unless($this->canConfirmTransfer($invoice, $accountant), 409);

        Log::channel('payments')->info('Kế toán xác nhận chuyển khoản thủ công', [
            'invoice' => $invoice->invoice_id, 'amount' => $invoice->payment_amount, 'accountant' => $accountant->employee_id,
        ]);

        return $this->recordPayment($invoice, $accountant->employee_id, now());
    }

    public function handleBankTransfer(float $amount, string $content, ?string $reference = null, ?Carbon $paidAt = null): ?Invoice
    {
        $code = preg_match('/INV\d+/i', $content, $m) ? strtoupper($m[0]) : null;
        $invoice = $code ? Invoice::query()->find($code) : null;

        $matched = $invoice
            && $invoice->status === Invoice::STATUS_UNPAID
            && $invoice->payment_method === Invoice::METHOD_BANK_TRANSFER
            && abs((float) $invoice->payment_amount - $amount) < 0.01;

        if (! $matched) {
            Log::channel('payments')->warning('Giao dịch chuyển khoản không khớp hóa đơn', [
                'reference' => $reference, 'amount' => $amount, 'content' => $content,
                'invoice'   => $code, 'invoice_status' => $invoice?->status, 'invoice_amount' => $invoice?->payment_amount,
            ]);

            if ($invoice && $invoice->payment_method === Invoice::METHOD_BANK_TRANSFER
                && in_array($invoice->status, [Invoice::STATUS_UNPAID, Invoice::STATUS_OVERDUE], true)) {
                TransferAlerts::add($invoice->invoice_id, $amount, $paidAt, $invoice->status === Invoice::STATUS_OVERDUE);
            }

            return null;
        }

        Log::channel('payments')->info('Giao dịch chuyển khoản khớp hóa đơn', ['reference' => $reference, 'invoice' => $code, 'amount' => $amount]);

        return $this->recordPayment($invoice, null, $paidAt ?? now());
    }

    public function recordPayment(Invoice $invoice, ?string $accountantId, Carbon $paidAt): Invoice
    {
        $paid = DB::transaction(function () use ($invoice, $accountantId, $paidAt) {
            $order = SalesOrder::query()->whereKey($invoice->order_id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($invoice->status === Invoice::STATUS_UNPAID, 409);

            $isCash = $invoice->payment_method === Invoice::METHOD_CASH;
            abort_if($isCash && ! $accountantId, 409);

            $invoice->update([
                'status'        => Invoice::STATUS_PAID,
                'payment_date'  => $paidAt,
                'accountant_id' => $accountantId,
            ]);

            $paidAmount = min((float) $order->total_amount, (float) $order->paid_amount + (float) $invoice->payment_amount);
            $remaining = round((float) $order->total_amount - $paidAmount, 2);

            $order->update([
                'paid_amount'      => $paidAmount,
                'remaining_amount' => $remaining,
                'status'           => $remaining <= 0 ? SalesOrder::STATUS_COMPLETED : SalesOrder::STATUS_IN_PROGRESS,
            ]);

            $opportunity = $order->quotation?->opportunity;
            if ($opportunity && $opportunity->status !== Opportunity::STATUS_WON) {
                $opportunity->update(['status' => Opportunity::STATUS_WON]);
            }

            return $invoice->fresh(['order.quotation.opportunity.lead', 'createdBy', 'accountant']);
        });

        TransferAlerts::forget($paid->invoice_id);
        $this->sendInvoiceEmail($paid);

        return $paid;
    }

    public function sendInvoiceEmail(Invoice $invoice): bool
    {
        $email = $invoice->order?->quotation?->opportunity?->lead?->email;

        if (blank($email)) {
            Log::channel('payments')->warning('Không gửi được email hóa đơn: khách chưa có email', ['invoice' => $invoice->invoice_id]);

            return false;
        }

        try {
            Mail::to($email)->send(new InvoicePaidMail($invoice));

            return true;
        } catch (\Throwable $e) {
            Log::channel('payments')->error('Gửi email hóa đơn thất bại', ['invoice' => $invoice->invoice_id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function resolveAmount(SalesOrder $order, ?string $type, float $amount, ?string $except = null): float
    {
        $max = $this->maxAmount($order, $except);

        if ($type === SalesOrder::PAYMENT_FULL) {
            abort_if($max <= 0, 409);

            return $max;
        }

        abort_unless($amount > 0 && $amount <= $max + 0.001, 422);

        return round($amount, 2);
    }
}
