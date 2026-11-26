<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Opportunity;
use App\Models\Stage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function beforeClosed(Opportunity $opportunity): bool
    {
        $closed = $this->stages()->firstWhere('stage_id', Stage::CLOSED);

        return $closed && $this->stageOrder($opportunity) < $closed->sort_order;
    }

    public function mark(Opportunity $opportunity, string $appointmentId, string $type, string $status, ?string $result = null): array
    {
        if (! in_array($status, [Appointment::STATUS_SUCCESS, Appointment::STATUS_FAILED], true)
            || ($status === Appointment::STATUS_SUCCESS && blank($result))) {
            return ['tone' => 'danger', 'text' => __('opportunities.apt_cannot_mark')];
        }

        $query = $this->rows($opportunity)
            ->where('appointment_id', $appointmentId)
            ->where('appointment_type', $type)
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->where('scheduled_time', '<=', now());

        $row = (clone $query)->first(['notes']);
        $values = ['status' => $status];

        if ($row && $result) {
            $values['notes'] = ($row->notes ? $row->notes."\n" : '').'Kết quả: '.$result;
        }

        if (! ($row ? $query->update($values) : 0)) {
            return ['tone' => 'danger', 'text' => __('opportunities.apt_cannot_mark')];
        }

        return ['tone' => 'success', 'text' => $this->advanceStageIfDone($opportunity)
            ? __('opportunities.apt_stage_moved', ['stage' => $opportunity->stage->stage_name])
            : __('opportunities.apt_marked')];
    }

    public function cancel(Opportunity $opportunity, string $appointmentId, string $type, string $reason): array
    {
        $query = $this->rows($opportunity)
            ->where('appointment_id', $appointmentId)
            ->where('appointment_type', $type)
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->where('scheduled_time', '>', now());
        $row = (clone $query)->first(['notes']);

        if (! $row || ! $this->beforeClosed($opportunity)) {
            return ['tone' => 'danger', 'text' => __('opportunities.apt_cannot_cancel')];
        }

        $query->update([
            'status' => Appointment::STATUS_CANCELLED,
            'notes'  => ($row->notes ? $row->notes."\n" : '').'Lý do hủy: '.$reason,
        ]);

        $statuses = $this->rows($opportunity)->pluck('status');

        if (! $statuses->contains(Appointment::STATUS_SCHEDULED) && ! $statuses->contains(Appointment::STATUS_SUCCESS)) {
            $closeReason = 'Khách hủy lịch hẹn: '.$reason;
            $opportunity->close(Opportunity::STATUS_LOST, $closeReason);
            $this->refreshStage($opportunity);

            return ['tone' => 'success', 'text' => __('opportunities.apt_cancelled_lost', ['reason' => $closeReason])];
        }

        return ['tone' => 'success', 'text' => $this->advanceStageIfDone($opportunity)
            ? __('opportunities.apt_cancelled_moved', ['stage' => $opportunity->stage->stage_name])
            : __('opportunities.apt_cancelled')];
    }

    public function reschedule(Opportunity $opportunity, array $times): int
    {
        if (! $this->beforeClosed($opportunity)) {
            return 0;
        }

        return DB::transaction(function () use ($opportunity, $times) {
            $updated = 0;

            foreach ($times as $key => $time) {
                [$id, $type] = array_pad(explode('-', (string) $key, 2), 2, '');

                $updated += $this->rows($opportunity)
                    ->where('appointment_id', $id)
                    ->where('appointment_type', $type)
                    ->where('status', Appointment::STATUS_SCHEDULED)
                    ->where('scheduled_time', '>', now())
                    ->update(['scheduled_time' => Carbon::parse($time)]);
            }

            return $updated;
        });
    }

    public function clashFor(?string $employeeId, Carbon $time, ?string $appointmentId = null): ?Appointment
    {
        return Appointment::query()
            ->where('employee_id', $employeeId)
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->whereBetween('scheduled_time', [$time->copy()->startOfMinute(), $time->copy()->endOfMinute()])
            ->when($appointmentId, fn ($q) => $q->where('appointment_id', '!=', $appointmentId))
            ->first();
    }

    public function clashMessage(Appointment $clash): string
    {
        return __('opportunities.apt_clash', [
            'type' => __('opportunities.appointment_type_name.'.$clash->appointment_type),
            'time' => $clash->scheduled_time->format('d/m/Y H:i'),
            'code' => $clash->appointment_id,
        ]);
    }

    public function activeTypes(Opportunity $opportunity): Collection
    {
        return $this->rows($opportunity)
            ->orderBy('scheduled_time')
            ->get(['appointment_type', 'status'])
            ->groupBy('appointment_type')
            ->reject(fn (Collection $rows) => $rows->last()->status === Appointment::STATUS_CANCELLED);
    }

    public function advanceStageIfDone(Opportunity $opportunity): bool
    {
        $processed = $this->stages()->firstWhere('stage_id', Stage::PROCESSED);

        if (! $processed || $this->stageOrder($opportunity) >= $processed->sort_order) {
            return false;
        }

        $types = $this->activeTypes($opportunity);

        if ($types->isEmpty() || $types->contains(fn (Collection $rows) => ! $rows->contains('status', Appointment::STATUS_SUCCESS))) {
            return false;
        }

        $opportunity->moveToStage($processed->stage_id);
        $this->refreshStage($opportunity);

        return true;
    }

    protected function rows(Opportunity $opportunity)
    {
        return DB::table('Appointments')->where('opportunity_id', $opportunity->opportunity_id);
    }

    protected function stages(): Collection
    {
        return once(fn () => Stage::query()->orderBy('sort_order')->get());
    }

    protected function stageOrder(Opportunity $opportunity): int
    {
        return $this->stages()->firstWhere('stage_id', $opportunity->stage_id)?->sort_order ?? 0;
    }

    protected function refreshStage(Opportunity $opportunity): void
    {
        $opportunity->setRelation('stage', $this->stages()->firstWhere('stage_id', $opportunity->stage_id));
    }
}
