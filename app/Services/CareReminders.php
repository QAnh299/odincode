<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\Stage;
use Illuminate\Support\Collection;

/**
 * Nhắc liên hệ lại cho Salesperson (chuông thông báo trên topbar): cơ hội mình phụ trách có lần liên hệ
 * gần nhất ở stage hiện tại là "Không liên lạc được" và đã qua Opportunity::RECONTACT_DAYS ngày.
 * Data đã có lịch hẹn chỉ nhắc khi chưa hẹn lại.
 */
class CareReminders
{
    /**
     * @return Collection<int, array{title: string, time: string, url: string, at: \Illuminate\Support\Carbon}>
     */
    public static function for(?Employee $employee): Collection
    {
        if ($employee?->role_name !== Role::SALESPERSON) {
            return collect();
        }

        return Opportunity::query()
            ->select('Opportunities.*')
            ->where('employee_id', $employee->employee_id)
            ->where('status', Opportunity::STATUS_IN_PROGRESS)
            ->whereIn('stage_id', [Stage::UNTOUCHED, Stage::APPOINTED, Stage::PROCESSED])
            ->withLastCare()
            ->with('lead:lead_id,full_name')
            ->get()
            ->filter(fn (Opportunity $opportunity) => $opportunity->recontactDue())
            ->map(fn (Opportunity $opportunity) => [
                'title' => __('opportunities.recontact_title', ['name' => $opportunity->lead?->full_name ?? '—']),
                'time'  => __('opportunities.recontact_time', ['time' => $opportunity->recontactAt()->format('d/m/Y H:i')]),
                'url'   => route('salesperson.opportunities.show', $opportunity),
                'at'    => $opportunity->recontactAt(),
            ])
            ->values();
    }
}
