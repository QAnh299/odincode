<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Nhắc lịch hẹn cho Salesperson (hiển thị ở chuông thông báo trên topbar):
 * lịch hẹn "Scheduled" do mình phụ trách, diễn ra hôm nay (chưa tới giờ) hoặc ngày mai
 * - tức nhắc trước 1 ngày để salesperson kịp nhắc khách hàng.
 * Một mã lịch hẹn (Test + Tư vấn) gom thành một thông báo, lấy giờ sớm nhất.
 */
class AppointmentReminders
{
    /**
     * @return Collection<int, array{title: string, time: string, url: string, at: Carbon}>
     */
    public static function for(?Employee $employee): Collection
    {
        if ($employee?->role_name !== Role::SALESPERSON) {
            return collect();
        }

        $now = now();

        return Appointment::query()
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
                $types = $rows->sortBy(fn ($r) => array_search($r->appointment_type, Appointment::TYPES))
                    ->map(fn ($r) => __('opportunities.appointment_type_name.'.$r->appointment_type))
                    ->implode(' + ');
                $day = $first->scheduled_time->isToday() ? __('appointments.today') : __('appointments.tomorrow');

                return [
                    'title' => __('appointments.reminder_title', ['types' => $types, 'name' => $lead?->full_name ?? '—']),
                    'time'  => $day.' '.$first->scheduled_time->format('H:i')
                        .($lead?->branch ? ' · '.$lead->branch->branch_name : ''),
                    'url'   => route('salesperson.opportunities.show', ['opportunity' => $first->opportunity_id, 'tab' => 'appointments']),
                    'at'    => $first->scheduled_time,
                ];
            })
            ->values();
    }
}
