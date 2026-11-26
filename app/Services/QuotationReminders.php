<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Quotation;
use App\Models\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class QuotationReminders
{
    public const REMIND_DAYS = 1;

    public static function for(?Employee $employee): Collection
    {
        if ($employee?->role_name !== Role::SALESPERSON) {
            return collect();
        }

        return Quotation::query()
            ->where('employee_id', $employee->employee_id)
            ->where('status', Quotation::STATUS_DRAFT)
            ->whereDate('expiry_date', '<=', today()->addDays(self::REMIND_DAYS)->toDateString())
            ->with('opportunity.lead:lead_id,full_name')
            ->orderBy('expiry_date')
            ->orderBy('quotation_id')
            ->get()
            ->map(function (Quotation $quotation) {
                $days = $quotation->daysUntilExpiry();
                $name = $quotation->opportunity?->lead?->full_name ?? '-';

                $key = match (true) {
                    $days < 0   => 'quotations.reminder.overdue',
                    $days === 0 => 'quotations.reminder.today',
                    default     => 'quotations.reminder.tomorrow',
                };

                return [
                    'title' => __($key, ['code' => $quotation->quotation_id, 'name' => $name, 'days' => abs($days)]),
                    'time'  => __('quotations.reminder.time', ['date' => $quotation->expiry_date->format('d/m/Y')]),
                    'url'   => route('salesperson.quotations.show', $quotation),
                    'at'    => $quotation->expiry_date->copy()->endOfDay(),
                ];
            });
    }
}
