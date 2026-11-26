<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class OpportunityCreateService
{
    public function __construct(
        protected LeadDuplicateService $duplicate,
        protected LeadImportService $import,
    ) {
    }

    public function candidates(Employee $salesperson, string $search = ''): Collection
    {
        $search = trim($search);

        $viaStudent = Opportunity::query()
            ->where('employee_id', $salesperson->employee_id)
            ->whereHas('student')
            ->select('lead_id');

        $viaOldOpp = Opportunity::query()
            ->where('employee_id', $salesperson->employee_id)
            ->whereIn('status', [Opportunity::STATUS_LOST, Opportunity::STATUS_ARCHIVED])
            ->whereDoesntHave('student')
            ->select('lead_id');

        $query = Lead::query()
            ->whereIn('lead_id', $viaStudent->union($viaOldOpp))
            ->with($this->leadDetails());

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn (Builder $q) => $q
                ->where('full_name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhereHas('opportunities', fn (Builder $o) => $o
                    ->where('employee_id', $salesperson->employee_id)
                    ->where('opportunity_id', 'like', $like))
                ->orWhereHas('opportunities.student', fn (Builder $s) => $s
                    ->where('student_id', 'like', $like)));
        }

        return $query->orderBy('full_name')->get();
    }

    public function candidatesPaginated(Employee $salesperson, int $perPage = 10): LengthAwarePaginator
    {
        $viaStudent = Opportunity::query()
            ->where('employee_id', $salesperson->employee_id)
            ->whereHas('student')
            ->select('lead_id');

        $viaOldOpp = Opportunity::query()
            ->where('employee_id', $salesperson->employee_id)
            ->whereIn('status', [Opportunity::STATUS_LOST, Opportunity::STATUS_ARCHIVED])
            ->whereDoesntHave('student')
            ->select('lead_id');

        return Lead::query()
            ->whereIn('lead_id', $viaStudent->union($viaOldOpp))
            ->with($this->leadDetails())
            ->orderBy('full_name')
            ->paginate($perPage, pageName: 'defaultPage');
    }

    public function findForSalesperson(string $leadId, Employee $salesperson): ?Lead
    {
        $lead = Lead::query()
            ->whereKey($leadId)
            ->with($this->leadDetails())
            ->first();

        if (! $lead) {
            return null;
        }

        if (! $lead->opportunities->contains('employee_id', $salesperson->employee_id)) {
            return null;
        }

        return $lead;
    }

    public function check(Lead $lead, Employee $salesperson): array
    {
        $state = $this->duplicate->stateOf($lead);

        $blocks   = [];
        $warnings = [];

        if ($state === LeadDuplicateService::IN_QUEUE) {
            $blocks[] = ['type' => 'in_queue'];
        } elseif ($state === LeadDuplicateService::IN_PROGRESS) {
            $active = $lead->opportunities->firstWhere('status', Opportunity::STATUS_IN_PROGRESS);

            if ($active && $active->employee_id === $salesperson->employee_id) {
                $blocks[] = [
                    'type'           => 'own_in_progress',
                    'opportunity_id' => $active->opportunity_id,
                ];
            } elseif ($active) {
                $owner = $active->employee;
                $blocks[] = [
                    'type'        => 'other_in_progress',
                    'owner_name'  => $owner?->full_name ?? '-',
                    'owner_phone' => $owner?->phone ?? null,
                ];
            }
        }

        $studyingCourses = $this->studyingCourses($lead);
        if ($studyingCourses->isNotEmpty()) {
            $warnings[] = [
                'type'    => 'studying',
                'courses' => $studyingCourses->all(),
            ];
        }

        $debts = $this->outstandingDebts($lead);
        if ($debts->isNotEmpty()) {
            $warnings[] = [
                'type'  => 'debt',
                'items' => $debts->all(),
            ];
        }

        return [
            'state'    => $blocks ? 'blocked' : ($warnings ? 'warning' : 'ok'),
            'blocks'   => $blocks,
            'warnings' => $warnings,
        ];
    }

    public function create(Lead $lead, Employee $salesperson, array $contact = []): Opportunity
    {
        return DB::transaction(function () use ($lead, $salesperson, $contact) {
            $locked = Lead::query()
                ->whereKey($lead->getKey())
                ->lockForUpdate()
                ->firstOrFail()
                ->load($this->leadDetails());

            if (! $locked->opportunities->contains('employee_id', $salesperson->employee_id)) {
                throw new RuntimeException(__('opportunities.create.error_no_permission'));
            }

            $state = $this->duplicate->stateOf($locked);

            if ($state === LeadDuplicateService::IN_QUEUE) {
                throw new RuntimeException(__('opportunities.create.blocked_in_queue'));
            }

            if ($state === LeadDuplicateService::IN_PROGRESS) {
                $active = $locked->opportunities->firstWhere('status', Opportunity::STATUS_IN_PROGRESS);

                if ($active && $active->employee_id === $salesperson->employee_id) {
                    throw new RuntimeException(__('opportunities.create.blocked_own_in_progress', [
                        'opp' => $active->opportunity_id,
                    ]));
                }

                $owner = $active?->employee;
                throw new RuntimeException(__('opportunities.create.blocked_other_in_progress', [
                    'name'  => $owner?->full_name ?? '-',
                    'phone' => $owner?->phone ?? __('opportunities.create.no_phone'),
                ]));
            }

            $contactUpdate = $this->resolveContactUpdate($locked, $contact);
            if ($contactUpdate !== []) {
                $locked->update($contactUpdate);
            }

            [$id] = Opportunity::nextIds(1);

            Opportunity::insert([
                'opportunity_id'  => $id,
                'conversion_date' => now(),
                'status'          => Opportunity::STATUS_IN_PROGRESS,
                'lead_id'         => $locked->lead_id,
                'stage_id'        => Stage::UNTOUCHED,
                'employee_id'     => $salesperson->employee_id,
            ]);

            return Opportunity::find($id);
        });
    }

    public function resolveContactUpdate(Lead $lead, array $contact): array
    {
        $update = [];

        foreach (LeadDuplicateService::CONTACT_FIELDS as $field) {
            if (! array_key_exists($field, $contact)) {
                continue;
            }

            $new = trim((string) $contact[$field]);
            $new = match (true) {
                $new === ''        => null,
                $field === 'phone' => $this->import->normalizePhone($new),
                $field === 'email' => mb_strtolower($new),
                default            => $new,
            };
            $old = $lead->$field;

            if ($old === $new || ($new !== null && $this->duplicate->sameValue($field, $old, $new))) {
                continue;
            }

            $update[$field] = $new;
        }

        if ($update === []) {
            return [];
        }

        $merged = array_merge(
            $lead->only(array_keys(array_flip(LeadImportService::COLUMNS))),
            $update,
        );

        $validator = Validator::make(
            $merged,
            $this->import->rules(),
            $this->import->messages(),
            $this->import->attributes(),
        );

        if ($validator->fails()) {
            throw new RuntimeException($validator->errors()->first());
        }

        return $update;
    }

    public function studyingCourses(Lead $lead): Collection
    {
        if ($lead->student?->status !== Student::STATUS_STUDYING) {
            return collect();
        }

        return $lead->opportunities
            ->flatMap(fn (Opportunity $opp) => $opp->quotations
                ->filter(fn ($q) => (float) ($q->order?->paid_amount ?? 0) > 0)
                ->flatMap(fn ($q) => $q->details->map(fn ($d) => $d->course?->course_name ?? $d->course_id)))
            ->filter()
            ->unique()
            ->values();
    }

    public function outstandingDebts(Lead $lead): Collection
    {
        $debts = collect();

        foreach ($lead->opportunities as $opp) {
            foreach ($opp->quotations as $quotation) {
                $order = $quotation->order;

                if (! $order
                    || (float) $order->paid_amount <= 0
                    || (float) $order->remaining_amount <= 0) {
                    continue;
                }

                $courses = $quotation->details
                    ->map(fn ($d) => $d->course?->course_name ?? $d->course_id)
                    ->filter()
                    ->implode(', ') ?: '-';

                $debts->push([
                    'course'    => $courses,
                    'remaining' => (float) $order->remaining_amount,
                ]);
            }
        }

        return $debts;
    }

    protected function leadDetails(): array
    {
        return [
            'opportunities'                              => fn ($q) => $q->orderByDesc('conversion_date'),
            'opportunities.employee:employee_id,full_name,phone',
            'opportunities.student:student_id,opportunity_id,status',
            'opportunities.quotations.details.course:course_id,course_name',
            'opportunities.quotations.order',
            'student:student_id,lead_id,status',
        ];
    }
}
