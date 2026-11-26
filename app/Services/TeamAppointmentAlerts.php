<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TeamAppointmentAlerts
{
    public const LEADER_AFTER_HOURS = 24;

    public static function for(?Employee $employee): Collection
    {
        $team = $employee?->role_name === Role::SALE_LEADER ? $employee->ledTeam : null;
        if (! $team) {
            return collect();
        }

        return Appointment::query()
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->where('scheduled_time', '<=', now()->subHours(self::LEADER_AFTER_HOURS))
            ->whereHas('employee', fn ($q) => $q
                ->where('team_id', $team->team_id)
                ->whereHas('role', fn ($r) => $r->where('role_name', Role::SALESPERSON)))
            ->with(['employee:employee_id,full_name', 'opportunity.lead:lead_id,full_name'])
            ->orderBy('scheduled_time')
            ->get()
            ->groupBy('appointment_id')
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'title'   => __('appointments.leader_overdue_title', [
                        'employee' => $first->employee?->full_name ?? '-',
                        'types'    => $rows->sortBy(fn ($r) => array_search($r->appointment_type, Appointment::TYPES))
                            ->map(fn ($r) => __('opportunities.appointment_type_name.'.$r->appointment_type))
                            ->implode(' + '),
                        'name'     => $first->opportunity?->lead?->full_name ?? '-',
                    ]),
                    'time'    => __('appointments.leader_overdue_time', [
                        'time' => $first->scheduled_time->format('d/m/Y H:i'),
                        'ago'  => $first->scheduled_time->diffForHumans(),
                    ]),
                    'url'     => route('sale_leader.opportunities.show', ['opportunity' => $first->opportunity_id, 'tab' => 'appointments']),
                    'at'      => $first->scheduled_time,

                    'history' => false,
                ];
            })
            ->values();
    }
}
