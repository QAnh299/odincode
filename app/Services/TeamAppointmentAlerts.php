<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Thông báo cho Sale Leader (chuông thông báo trên topbar): lịch hẹn của Salesperson trong đội đã qua giờ hẹn
 * quá LEADER_AFTER_HOURS giờ mà chưa cập nhật kết quả (Khách đã đến / Khách không đến).
 *
 * Salesperson được nhắc ngay khi lịch quá giờ (AppointmentReminders); Leader chỉ được báo khi Salesperson để quá
 * thời gian này, để theo dõi. Leader chỉ xem (link mở trang chi tiết cơ hội ở chế độ xem), không cập nhật hay xử lý.
 * Thông báo suy ra từ dữ liệu: Salesperson cập nhật kết quả (hoặc cơ hội được chuyển / kết thúc) thì lịch không còn
 * "Scheduled" và thông báo tự biến mất; chuông không giữ các thông báo này trong lịch sử "Đã xử lý" của Leader.
 * Tính cả Salesperson đã nghỉ việc còn trong đội, để Leader biết lịch hẹn của người nghỉ cần chuyển giao.
 */
class TeamAppointmentAlerts
{
    // Quá giờ hẹn bao lâu mà Salesperson chưa cập nhật thì báo Leader
    public const LEADER_AFTER_HOURS = 24;

    /**
     * @return Collection<int, array{title: string, time: string, url: string, at: Carbon, history: bool}>
     */
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
                    // Đã xử lý thì xóa khỏi chuông của Leader, không đưa vào lịch sử "Đã xử lý"
                    'history' => false,
                ];
            })
            ->values();
    }
}
