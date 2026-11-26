<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AppointmentReminders
{
    public static function for(?Employee $employee): Collection
    {
        if ($employee?->role_name !== Role::SALESPERSON) {
            return collect();
        }

        $now = now();

        $overdue = Appointment::query()
            ->where('employee_id', $employee->employee_id)
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->where('scheduled_time', '<=', $now)
            ->with('opportunity.lead:lead_id,full_name')
            ->orderBy('scheduled_time')
            ->get()
            ->groupBy('appointment_id')
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'title' => __('appointments.overdue_title', [
                        'types' => self::typeNames($rows),
                        'name'  => $first->opportunity?->lead?->full_name ?? '-',
                    ]),
                    'time'  => __('appointments.overdue_time', ['time' => $first->scheduled_time->format('d/m/Y H:i')]),
                    'url'   => route('salesperson.opportunities.show', ['opportunity' => $first->opportunity_id, 'tab' => 'appointments']),
                    'at'    => $first->scheduled_time,

                    'key'   => 'appointment-overdue:'.$first->appointment_id,
                ];
            })
            ->values();

        return $overdue->concat(Appointment::query()
            ->where('employee_id', $employee->employee_id)
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->where('scheduled_time', '>', $now)
            ->where('scheduled_time', '<', $now->copy()->addDays(2)->startOfDay())
            ->with('opportunity.lead.branch:branch_id,branch_name')
            ->orderBy('scheduled_time')
            ->get()
            ->groupBy('appointment_id')
            ->map(function (Collection $rows) {
                $first = $rows->first();
                $lead = $first->opportunity?->lead;
                $types = self::typeNames($rows);
                $day = $first->scheduled_time->isToday() ? __('appointments.today') : __('appointments.tomorrow');

                return [
                    'title' => __('appointments.reminder_title', ['types' => $types, 'name' => $lead?->full_name ?? '-']),
                    'time'  => $day.' '.$first->scheduled_time->format('H:i')
                        .($lead?->branch ? ' · '.$lead->branch->branch_name : ''),
                    'url'   => route('salesperson.opportunities.show', ['opportunity' => $first->opportunity_id, 'tab' => 'appointments']),
                    'at'    => $first->scheduled_time,
                ];
            })
            ->values());
    }

    protected static function typeNames(Collection $rows): string
    {
        return $rows->sortBy(fn ($r) => array_search($r->appointment_type, Appointment::TYPES))
            ->map(fn ($r) => __('opportunities.appointment_type_name.'.$r->appointment_type))
            ->implode(' + ');
    }
}
