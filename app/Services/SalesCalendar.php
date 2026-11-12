<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CareResult;
use App\Models\Employee;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Models\Stage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Hoạt động trong 1 tháng của Salesperson (lịch trên topbar), suy ra từ dữ liệu vì odin.sql không có bảng công việc:
 *   - Lịch hẹn Test / Tư vấn mình phụ trách: theo giờ hẹn; xong khi đã cập nhật kết quả. Test + Tư vấn cùng mã gom 1 dòng.
 *   - Liên hệ ban đầu: Cơ hội ở stage đầu chưa liên hệ lần nào, theo ngày được giao.
 *   - Liên hệ lại: lần liên hệ gần nhất là "Không liên lạc được", theo Opportunity::recontactAt().
 *   - Báo giá Nháp: theo ngày hết hạn.
 *   - Lần chăm sóc đã thực hiện: việc đã xong.
 * Việc chưa xong đã qua thời điểm là quá hạn; việc quá hạn nằm ở đúng ngày phát sinh của nó.
 */
class SalesCalendar
{
    /**
     * @return array<int, array{key: string, day: string, at: Carbon, time: ?string, kind: string, label: string,
     *                          name: string, detail: ?string, url: string, done: bool, overdue: bool}>
     */
    public function month(Employee $employee, CarbonImmutable $month): array
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();

        $items = array_merge(
            $this->appointments($employee, $from, $to),
            $this->contacts($employee, $from, $to),
            $this->quotations($employee, $from, $to),
            $this->careDone($employee, $from, $to),
        );

        usort($items, fn ($a, $b) => [$a['at'], $a['key']] <=> [$b['at'], $b['key']]);

        return $items;
    }

    protected function appointments(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Appointment::query()
            ->where('employee_id', $employee->employee_id)
            ->whereBetween('scheduled_time', [$from, $to])
            ->with(['opportunity.lead:lead_id,full_name', 'opportunity.stage:stage_id,stage_name'])
            ->orderBy('scheduled_time')
            ->get()
            ->groupBy('appointment_id')
            ->map(function ($rows, $id) {
                $first = $rows->first();
                $types = $rows->sortBy(fn ($r) => array_search($r->appointment_type, Appointment::TYPES));
                $done = $rows->every(fn ($r) => $r->status !== Appointment::STATUS_SCHEDULED);

                return $this->item(
                    "appointment-$id",
                    $first->scheduled_time,
                    strtolower($types->first()->appointment_type),
                    $types->map(fn ($r) => __('opportunities.appointment_type_name.'.$r->appointment_type))->implode(' + '),
                    $first->opportunity,
                    $first->notes,
                    route('salesperson.opportunities.show', ['opportunity' => $first->opportunity_id, 'tab' => 'appointments']),
                    $done
                );
            })
            ->values()
            ->all();
    }

    /**
     * Liên hệ ban đầu + liên hệ lại của các Cơ hội đang xử lý.
     */
    protected function contacts(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $items = [];
        $opportunities = Opportunity::query()
            ->select('Opportunities.*')
            ->where('employee_id', $employee->employee_id)
            ->where('status', Opportunity::STATUS_IN_PROGRESS)
            ->whereIn('stage_id', [Stage::UNTOUCHED, Stage::APPOINTED, Stage::PROCESSED])
            ->withLastCare()
            ->withCount('careResults')
            ->with(['lead:lead_id,full_name', 'stage:stage_id,stage_name'])
            ->get();

        foreach ($opportunities as $opportunity) {
            $url = route('salesperson.opportunities.show', $opportunity);

            if ($opportunity->stage_id === Stage::UNTOUCHED && ! $opportunity->care_results_count) {
                $at = $opportunity->conversion_date;
                $kind = 'initial';
            } elseif ($opportunity->last_care_result === CareResult::RESULT_UNREACHABLE
                && ($opportunity->stage_id !== Stage::APPOINTED
                    || ! $opportunity->appointments()->where('status', Appointment::STATUS_SCHEDULED)->exists())) {
                $at = $opportunity->recontactAt();
                $kind = 'recontact';
            } else {
                continue;
            }

            if ($at && $at->between($from, $to)) {
                $items[] = $this->item("$kind-{$opportunity->opportunity_id}", $at, $kind, __('calendar.kind.'.$kind), $opportunity, null, $url, false);
            }
        }

        return $items;
    }

    protected function quotations(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Quotation::query()
            ->where('employee_id', $employee->employee_id)
            ->where('status', Quotation::STATUS_DRAFT)
            ->whereBetween('expiry_date', [$from->toDateString(), $to->toDateString()])
            ->with(['opportunity.lead:lead_id,full_name', 'opportunity.stage:stage_id,stage_name'])
            ->get()
            ->map(fn (Quotation $quotation) => $this->item(
                "quotation-{$quotation->quotation_id}",
                $quotation->expiry_date->copy()->endOfDay(),
                'quotation',
                __('calendar.kind.quotation'),
                $quotation->opportunity,
                $quotation->quotation_id,
                route('salesperson.quotations.show', $quotation),
                false,
                false
            ))
            ->all();
    }

    protected function careDone(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return CareResult::query()
            ->where('employee_id', $employee->employee_id)
            ->whereBetween('performed_at', [$from, $to])
            ->with(['activity:activity_id,activity_name', 'opportunity.lead:lead_id,full_name', 'opportunity.stage:stage_id,stage_name'])
            ->get()
            ->map(fn (CareResult $care) => $this->item(
                "care-{$care->result_id}",
                $care->performed_at,
                'care',
                $care->activity?->activity_name ?? __('calendar.kind.care'),
                $care->opportunity,
                $care->notes,
                route('salesperson.opportunities.show', $care->opportunity_id),
                true
            ))
            ->all();
    }

    protected function item(
        string $key,
        Carbon $at,
        string $kind,
        string $label,
        ?Opportunity $opportunity,
        ?string $note,
        string $url,
        bool $done,
        bool $timed = true
    ): array {
        return [
            'key'     => $key,
            'day'     => $at->toDateString(),
            'at'      => $at,
            'time'    => $timed ? $at->format('H:i') : null,
            'kind'    => $kind,
            'label'   => $label,
            'name'    => $opportunity?->lead?->full_name ?? '-',
            'detail'  => implode(' · ', array_filter([$opportunity?->stage?->stage_name, $note])),
            'url'     => $url,
            'done'    => $done,
            'overdue' => ! $done && $at->lt(now()),
        ];
    }
}
