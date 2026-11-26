<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InvoiceNotices
{
    public const RECENT_DAYS = 1;

    public const LIMIT = 10;

    public static function for(?Employee $employee): Collection
    {
        return match ($employee?->role_name) {
            Role::ACCOUNTANT  => self::transferAlerts(null, Role::ACCOUNTANT, $employee)->concat(self::cashQueue($employee))
                ->concat(self::recentlyPaid(null, Role::ACCOUNTANT, $employee)),
            Role::SALESPERSON => self::transferAlerts($employee->employee_id, Role::SALESPERSON)->concat(self::overdue($employee->employee_id))
                ->concat(self::recentlyPaid($employee->employee_id, Role::SALESPERSON)),
            default           => collect(),
        };
    }

    public static function cashQueueCount(?Employee $accountant = null): int
    {
        return Invoice::query()
            ->when($accountant, fn ($q) => $q->visibleTo($accountant))
            ->where('status', Invoice::STATUS_UNPAID)
            ->where('payment_method', Invoice::METHOD_CASH)
            ->count();
    }

    private static function cashQueue(Employee $accountant): Collection
    {
        return Invoice::query()
            ->visibleTo($accountant)
            ->where('status', Invoice::STATUS_UNPAID)
            ->where('payment_method', Invoice::METHOD_CASH)
            ->with(['order.quotation.opportunity.lead:lead_id,full_name', 'createdBy:employee_id,full_name'])
            ->orderBy('issued_date')
            ->orderBy('invoice_id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'title' => __('invoices.notice.cash_pending', [
                    'code'   => $invoice->invoice_id,
                    'name'   => $invoice->order?->quotation?->opportunity?->lead?->full_name ?? '-',
                    'amount' => self::money($invoice->payment_amount),
                ]),
                'time'  => __('invoices.notice.cash_pending_time', [
                    'date' => $invoice->issued_date?->format('d/m/Y'),
                    'by'   => $invoice->createdBy?->full_name ?? '-',
                ]),
                'url'   => route('accountant.invoices.show', $invoice),

                'at'    => Carbon::create(1970),
                'kind'  => 'cash',
            ]);
    }

    private static function transferAlerts(?string $creatorId, string $role, ?Employee $accountant = null): Collection
    {
        $alerts = TransferAlerts::all();
        if (! $alerts) {
            return collect();
        }
        $prefix = $role === Role::ACCOUNTANT ? 'accountant' : 'salesperson';

        return Invoice::query()
            ->whereIn('invoice_id', array_keys($alerts))
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_OVERDUE])
            ->where('payment_method', Invoice::METHOD_BANK_TRANSFER)
            ->when($creatorId, fn ($q) => $q->where('created_by_employee_id', $creatorId))
            ->when($accountant, fn ($q) => $q->visibleTo($accountant))
            ->with('order.quotation.opportunity.lead:lead_id,full_name')
            ->get()
            ->map(function (Invoice $invoice) use ($prefix) {
                $alert = TransferAlerts::find($invoice->invoice_id);

                $case = match (true) {
                    $invoice->status === Invoice::STATUS_OVERDUE => 'transfer_overdue',
                    $alert['overdue']                            => 'transfer_reopened',
                    default                                      => 'transfer_mismatch',
                };

                return [
                    'title' => __('invoices.notice.'.$case, [
                        'code'     => $invoice->invoice_id,
                        'name'     => $invoice->order?->quotation?->opportunity?->lead?->full_name ?? '-',
                        'received' => self::money($alert['amount']),
                        'amount'   => self::money($invoice->payment_amount),
                    ]),

                    'time'  => __('invoices.notice.'.$case.'_time'.($prefix === 'accountant' ? '' : '_sp'), ['time' => $alert['at']->format('d/m H:i')]),
                    'url'   => route($prefix.'.invoices.show', $invoice),

                    'at'    => Carbon::create(1970),
                    'kind'  => 'transfer',
                ];
            })
            ->values();
    }

    private static function overdue(string $creatorId): Collection
    {
        return Invoice::query()
            ->where('status', Invoice::STATUS_OVERDUE)
            ->where('created_by_employee_id', $creatorId)
            ->with('order.quotation.opportunity.lead:lead_id,full_name')
            ->orderBy('due_date')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'title' => __('invoices.notice.overdue', [
                    'code'   => $invoice->invoice_id,
                    'name'   => $invoice->order?->quotation?->opportunity?->lead?->full_name ?? '-',
                    'amount' => self::money($invoice->payment_amount),
                ]),
                'time'  => __('invoices.notice.overdue_time', ['date' => $invoice->due_date?->format('d/m/Y')]),
                'url'   => route('salesperson.invoices.show', $invoice),
                'at'    => $invoice->due_date,
                'kind'  => 'overdue',
            ]);
    }

    private static function recentlyPaid(?string $creatorId, string $role, ?Employee $accountant = null): Collection
    {
        $prefix = $role === Role::ACCOUNTANT ? 'accountant' : 'salesperson';

        return Invoice::query()
            ->where('status', Invoice::STATUS_PAID)
            ->where('payment_date', '>=', today()->subDays(self::RECENT_DAYS))
            ->when($creatorId, fn ($q) => $q->where('created_by_employee_id', $creatorId))
            ->when($accountant, fn ($q) => $q->visibleTo($accountant))
            ->with(['order.quotation.opportunity.lead:lead_id,full_name', 'accountant:employee_id,full_name'])
            ->orderByDesc('payment_date')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'title' => __('invoices.notice.paid', [
                    'code'   => $invoice->invoice_id,
                    'name'   => $invoice->order?->quotation?->opportunity?->lead?->full_name ?? '-',
                    'amount' => self::money($invoice->payment_amount),
                ]),
                'time'  => ($invoice->payment_method === Invoice::METHOD_CASH
                        ? __('invoices.notice.by_accountant', ['name' => $invoice->accountant?->full_name ?? '-'])
                        : ($invoice->accountant
                            ? __('invoices.notice.by_accountant_transfer', ['name' => $invoice->accountant->full_name])
                            : __('invoices.notice.by_system')))
                    .' · '.$invoice->payment_date->format('d/m H:i'),
                'url'   => route($prefix.'.invoices.show', $invoice),
                'at'    => $invoice->payment_date,
                'kind'  => 'paid',
            ]);
    }

    private static function money($value): string
    {
        return number_format((float) $value, 0, ',', '.').' ₫';
    }
}
