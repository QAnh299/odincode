<?php

namespace App\Livewire\Opportunities;

use App\Livewire\Concerns\StudentTrail;
use App\Livewire\Leads\Concerns\EditsLead;
use App\Models\Appointment;
use App\Models\CareActivity;
use App\Models\CareResult;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\OpportunityTransfer;
use App\Models\Role;
use App\Models\Stage;
use App\Services\AppointmentService;
use App\Services\LeadDistributionService;
use App\Services\LeadDuplicateService;
use App\Services\QuotationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    use StudentTrail;

    use EditsLead;

    public const TABS = ['care', 'appointments'];

    public Opportunity $opportunity;

    #[Url(except: 'care')]
    public string $tab = 'care';

    #[Url(as: 'apt', except: '')]
    public string $focusApt = '';

    #[Locked]
    public ?string $appointmentsBackUrl = null;

    public ?array $notice = null;

    public bool $reassigning = false;

    public string $newOwner = '';

    public string $transferReason = '';

    public bool $confirmingOver = false;

    public ?array $reassigned = null;

    public ?string $form = null;

    public string $careTime = '';

    public string $careResult = '';

    public string $careNotes = '';

    public array $newTypes = [];

    public array $newTimes = [];

    public string $newNotes = '';

    public array $editKeys = [];

    public array $editTimes = [];

    public ?string $attendKey = null;

    public string $attendResult = '';

    public ?string $cancelKey = null;

    public string $cancelReason = '';

    public function mount(Opportunity $opportunity): void
    {
        abort_unless(
            Opportunity::query()->visibleTo(Auth::user()->employee)->assigned()->whereKey($opportunity->getKey())->exists(),
            404
        );

        $this->opportunity = $opportunity->load([
            'lead.branch:branch_id,branch_name,address', 'stage', 'student', 'employee.team:team_id,team_name',
        ]);

        $this->initStudentTrail($opportunity->opportunity_id);
        $this->initAppointmentsBack();
    }

    protected function initAppointmentsBack(): void
    {
        if (request()->query('from') !== 'appointments' || ! Route::has($this->routePrefix.'.appointments')) {
            return;
        }

        parse_str((string) request()->query('list', ''), $list);
        $list = array_filter(
            array_intersect_key($list, array_flip(['q', 'status', 'period', 'page'])),
            fn ($value) => is_string($value) && $value !== '',
        );

        $this->appointmentsBackUrl = route($this->routePrefix.'.appointments', $list);
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'care';
        $this->notice = null;
        $this->closeForm();
    }

    public function dismissNotice(): void
    {
        $this->notice = null;
    }

    public function closeForm(): void
    {
        $this->reset('form', 'careTime', 'careResult', 'careNotes', 'newTypes', 'newTimes', 'newNotes', 'editKeys', 'editTimes', 'attendKey', 'attendResult', 'cancelKey', 'cancelReason');
        $this->resetValidation();
    }

    public const STAGE_ACTIVITIES = [
        Stage::UNTOUCHED => CareActivity::INITIAL,
        Stage::APPOINTED => CareActivity::RESCHEDULE,
        Stage::PROCESSED => CareActivity::DECISION,
    ];

    public const MAX_UNREACHABLE_CONTACTS = 4;

    public const UNREACHABLE_DAYS = [
        Stage::APPOINTED => Opportunity::REBOOK_DAYS,
    ];

    #[Computed]
    public function nextActivity(): ?CareActivity
    {
        $id = self::STAGE_ACTIVITIES[$this->opportunity->stage_id] ?? null;

        return $id ? CareActivity::find($id) : null;
    }

    #[Computed]
    public function stageSince(): Carbon
    {
        return $this->opportunity->stageEnteredAt();
    }

    protected function stageContacts()
    {
        return $this->opportunity->careResults()
            ->where('activity_id', $this->nextActivity?->activity_id)
            ->where('performed_at', '>=', $this->stageSince);
    }

    #[Computed]
    public function unreachableContacts(): int
    {
        return $this->stageContacts()->where('result', CareResult::RESULT_UNREACHABLE)->count();
    }

    #[Computed]
    public function stageContactDone(): bool
    {
        return $this->stageContacts()->where('result', CareResult::RESULT_SUCCESS)->exists();
    }

    protected function lastNoShow(): ?Carbon
    {
        $time = $this->opportunity->appointments()->where('status', Appointment::STATUS_FAILED)->max('scheduled_time');

        return $time ? Carbon::parse($time) : null;
    }

    public function needsContact(): bool
    {
        if (! $this->canCreateAppointment() || $this->nextActivity === null) {
            return false;
        }

        return $this->opportunity->stage_id !== Stage::APPOINTED || $this->rebookTypes !== [];
    }

    #[Computed]
    public function recontactAt(): ?Carbon
    {
        return $this->opportunity->recontactAt();
    }

    public function canLogCare(): bool
    {
        return $this->needsContact()
            && ($this->opportunity->stage_id !== Stage::UNTOUCHED || $this->unreachableContacts < self::MAX_UNREACHABLE_CONTACTS)
            && ! $this->recontactAt?->isFuture();
    }

    public function careLockHint(): ?string
    {
        if ($this->canLogCare()) {
            return null;
        }

        return $this->recontactAt?->isFuture()
            ? __('opportunities.care_hint_wait', ['time' => $this->recontactAt->format('d/m/Y H:i')])
            : __('opportunities.care_hint_full', ['max' => self::MAX_UNREACHABLE_CONTACTS]);
    }

    protected function earliestCareTime(): Carbon
    {
        $last = $this->opportunity->careResults()->max('performed_at');

        return collect([$this->stageSince, $last ? Carbon::parse($last) : null, $this->recontactAt])
            ->filter()
            ->max();
    }

    public function startCare(): void
    {
        $this->closeForm();

        if (! $this->canLogCare()) {
            return;
        }

        $this->form = 'care';
        $this->careTime = now()->format('Y-m-d\TH:i');
    }

    public function saveCare(): void
    {
        abort_unless($this->canLogCare(), 403);

        $earliest = $this->earliestCareTime();

        $this->validate([
            'careTime'   => ['required', 'date', 'before_or_equal:now', 'after_or_equal:'.$earliest->format('Y-m-d H:i')],
            'careResult' => ['required', Rule::in(CareResult::RESULTS)],
            'careNotes'  => ['nullable', 'required_if:careResult,'.CareResult::RESULT_FAILED, 'string', 'max:1000'],
        ], [
            'careTime.before_or_equal' => __('opportunities.care_time_future'),
            'careTime.after_or_equal'  => __('opportunities.care_time_early', ['time' => $earliest->format('d/m/Y H:i')]),
            'careResult.required'      => __('opportunities.care_result_required'),
            'careNotes.required_if'    => __('opportunities.care_reason_required'),
        ], [
            'careTime'  => __('opportunities.care_time'),
            'careNotes' => __('opportunities.notes'),
        ]);

        $activity = $this->nextActivity;
        $result = $this->careResult;
        $time = Carbon::parse($this->careTime);
        $notes = trim($this->careNotes);

        $text = DB::transaction(function () use ($activity, $result, $time, $notes) {
            CareResult::create([
                'performed_at'   => $time,
                'result'         => $result,
                'notes'          => $notes ?: null,
                'activity_id'    => $activity->activity_id,
                'opportunity_id' => $this->opportunity->opportunity_id,
                'employee_id'    => $this->employeeId(),
            ]);

            unset($this->careResults, $this->unreachableContacts, $this->stageContactDone, $this->recontactAt);

            return $this->applyCareOutcome($result, $time, $notes, $activity->activity_name);
        });

        $this->closeForm();
        $this->notice = ['tone' => 'success', 'text' => $text];
        $this->refreshAppointments();
    }

    protected function applyCareOutcome(string $result, Carbon $time, string $notes, string $activity): string
    {
        $params = ['activity' => $activity];

        if ($result === CareResult::RESULT_FAILED) {
            $reason = $this->opportunity->stage_id === Stage::PROCESSED ? 'Khách từ chối đăng ký: ' : 'Khách từ chối: ';

            return $this->closeOpportunity(Opportunity::STATUS_LOST, $reason.$notes, 'opportunities.care_closed_lost', $params);
        }

        if ($this->opportunity->stage_id === Stage::PROCESSED && $this->opportunity->closeIfDecisionOverdue($time)) {
            $this->refreshStage();

            return __('opportunities.care_closed_overdue', $params + [
                'reason' => $this->closeReason,
                'status' => __('opportunities.status_name.'.$this->opportunity->status),
            ]);
        }

        $reason = $result === CareResult::RESULT_UNREACHABLE ? $this->unreachableReason($time) : null;

        return $reason
            ? $this->closeOpportunity(Opportunity::STATUS_ARCHIVED, $reason, 'opportunities.care_closed_unreachable', $params)
            : __('opportunities.care_saved', $params);
    }

    protected function unreachableReason(Carbon $time): ?string
    {
        $stage = $this->opportunity->stage_id;

        if ($stage === Stage::UNTOUCHED) {
            return $this->unreachableContacts >= self::MAX_UNREACHABLE_CONTACTS
                ? 'Không liên hệ được ('.self::MAX_UNREACHABLE_CONTACTS.' lần không liên lạc được)'
                : null;
        }

        $days = self::UNREACHABLE_DAYS[$stage] ?? null;
        $from = $stage === Stage::APPOINTED ? $this->lastNoShow() : null;

        if (! $days || ! $from || $time->lte($from->copy()->addDays($days))) {
            return null;
        }

        return 'Không liên lạc được quá '.$days.' ngày kể từ buổi khách không đến';
    }

    protected function closeOpportunity(string $status, string $reason, string $key, array $params): string
    {
        $this->opportunity->close($status, $reason);
        $this->refreshStage();

        return __($key, $params + ['reason' => $reason]);
    }

    public function createQuotation()
    {
        abort_unless($this->canCreateQuotation(), 403);

        return $this->redirectRoute('salesperson.quotations.create', $this->opportunity, navigate: false);
    }

    public function canStartQuotation(): bool
    {
        return $this->isOwner()
            && $this->opportunity->stage_id === Stage::CLOSED
            && app(QuotationService::class)->blockerFor($this->opportunity, Auth::user()->employee) === null;
    }

    #[Computed]
    public function undecidedDays(): ?int
    {
        $deadline = $this->opportunity->decisionDeadline();

        return $deadline ? max(0, (int) today()->diffInDays($deadline->copy()->startOfDay(), false)) : null;
    }

    public function startAttended(string $appointmentId, string $type): void
    {
        $this->closeForm();
        $this->attendKey = $appointmentId.'-'.$type;
    }

    public function saveAttended(): void
    {
        [$id, $type] = array_pad(explode('-', (string) $this->attendKey, 2), 2, '');

        $this->validate(
            ['attendResult' => ['required', 'string', 'max:1000']],
            ['attendResult.required' => __('opportunities.apt_result_required')],
            ['attendResult' => __('opportunities.apt_result')],
        );

        $this->markAppointment($id, $type, Appointment::STATUS_SUCCESS, trim($this->attendResult));
    }

    public function markAppointment(string $appointmentId, string $type, string $status, ?string $result = null): void
    {
        abort_unless($this->isOwner(), 403);

        $this->tab = 'appointments';

        if (! in_array($status, [Appointment::STATUS_SUCCESS, Appointment::STATUS_FAILED], true)
            || ($status === Appointment::STATUS_SUCCESS && blank($result))) {
            return;
        }

        $this->notice = $this->appointmentService()->mark($this->opportunity, $appointmentId, $type, $status, $result);

        if ($this->notice['tone'] === 'success') {
            $this->closeForm();
            $this->refreshStage();
        }

        $this->refreshAppointments();
    }

    protected function activeAppointmentTypes(): Collection
    {
        return $this->appointmentService()->activeTypes($this->opportunity);
    }

    public function startCancel(string $appointmentId, string $type): void
    {
        $this->closeForm();
        $this->cancelKey = $appointmentId.'-'.$type;
    }

    public function saveCancel(): void
    {
        abort_unless($this->canCreateAppointment(), 403);

        [$id, $type] = array_pad(explode('-', (string) $this->cancelKey, 2), 2, '');

        $this->validate(
            ['cancelReason' => ['required', 'string', 'max:500']],
            ['cancelReason.required' => __('opportunities.apt_cancel_required')],
            ['cancelReason' => __('opportunities.apt_cancel_reason')],
        );
        $this->tab = 'appointments';
        $this->notice = $this->appointmentService()->cancel($this->opportunity, $id, $type, trim($this->cancelReason));

        if ($this->notice['tone'] === 'success') {
            $this->closeForm();
            $this->refreshStage();
        }

        $this->refreshAppointments();
    }

    protected function refreshStage(): void
    {
        $this->opportunity->setRelation('stage', $this->stages->firstWhere('stage_id', $this->opportunity->stage_id));
        unset($this->nextActivity, $this->stageSince, $this->unreachableContacts, $this->stageContactDone, $this->rebookTypes, $this->recontactAt);
    }

    public function canCreateAppointment(): bool
    {
        if (! $this->isOwner()) {
            return false;
        }

        $closed = $this->stages->firstWhere('stage_id', Stage::CLOSED);

        return $closed && $this->stageOrder() < $closed->sort_order;
    }

    #[Computed]
    public function rebookTypes(): array
    {
        if (! $this->canCreateAppointment()) {
            return [];
        }

        if ($this->opportunity->stage_id === Stage::UNTOUCHED) {
            return $this->stageContactDone ? Appointment::TYPES : [];
        }

        $types = $this->activeAppointmentTypes();

        return array_values(array_filter(Appointment::TYPES, function ($type) use ($types) {
            $statuses = $types->get($type, collect())->pluck('status');

            return $statuses->contains(Appointment::STATUS_FAILED)
                && ! $statuses->contains(Appointment::STATUS_SUCCESS)
                && ! $statuses->contains(Appointment::STATUS_SCHEDULED);
        }));
    }

    #[Computed]
    public function appointmentPlace(): ?string
    {
        $branch = $this->opportunity->lead?->branch;

        return $branch ? $branch->branch_name.' - '.$branch->address : null;
    }

    public function startCreate(): void
    {
        $this->tab = 'appointments';
        $this->closeForm();

        if (! $this->rebookTypes) {
            return;
        }

        $this->form = 'create';
        $this->newTypes = $this->rebookTypes;
        $this->fillNewDefaults();
    }

    public function saveAppointment(): void
    {
        $allowed = $this->rebookTypes;
        abort_unless($allowed, 403);

        $this->validate($this->appointmentRules($allowed), $this->appointmentMessages(), $this->appointmentAttributes());

        if (! $this->checkNewTimes()) {
            return;
        }

        [, $moved] = DB::transaction(fn () => $this->insertAppointment());

        $this->closeForm();
        $this->notice = $moved
            ? ['tone' => 'success', 'text' => __('opportunities.apt_created_moved', ['stage' => $this->opportunity->stage->stage_name])]
            : ['tone' => 'success', 'text' => __('opportunities.apt_created')];
        $this->refreshAppointments();
    }

    protected function fillNewDefaults(): void
    {
        $this->newTimes = array_fill_keys(Appointment::TYPES, '');
    }

    protected function checkNewTimes(): bool
    {
        foreach (array_intersect(Appointment::TYPES, $this->newTypes) as $type) {
            if ($clash = $this->appointmentService()->clashFor($this->employeeId(), Carbon::parse($this->newTimes[$type]))) {
                $this->addError("newTimes.$type", $this->appointmentService()->clashMessage($clash));
            }
        }

        return $this->getErrorBag()->isEmpty();
    }

    protected function appointmentRules(array $allowedTypes): array
    {
        $rules = [
            'newTypes'   => ['required', 'array', 'min:1'],
            'newTypes.*' => [Rule::in($allowedTypes)],
            'newNotes'   => ['nullable', 'string', 'max:1000'],
        ];

        foreach (array_intersect(Appointment::TYPES, $this->newTypes) as $type) {
            $rules["newTimes.$type"] = ['required', 'date', 'after:now'];
        }

        return $rules;
    }

    protected function appointmentMessages(): array
    {
        return [
            'newTypes.required'  => __('opportunities.apt_type_required'),
            'newTypes.min'       => __('opportunities.apt_type_required'),
            'newTypes.*.in'      => __('opportunities.apt_type_not_allowed'),
            'newTimes.*.after'   => __('opportunities.apt_time_future'),
        ];
    }

    protected function appointmentAttributes(): array
    {
        $attributes = ['newNotes' => __('opportunities.apt_notes')];

        foreach (Appointment::TYPES as $type) {
            $name = __('opportunities.appointment_type_name.'.$type);
            $attributes["newTimes.$type"] = __('opportunities.appointment_time').' ('.$name.')';
        }

        return $attributes;
    }

    protected function insertAppointment(): array
    {
        $id = Appointment::nextId();

        foreach (array_intersect(Appointment::TYPES, $this->newTypes) as $type) {
            DB::table('Appointments')->insert([
                'appointment_id'   => $id,
                'appointment_type' => $type,
                'scheduled_time'   => Carbon::parse($this->newTimes[$type]),
                'status'           => Appointment::STATUS_SCHEDULED,
                'notes'            => trim($this->newNotes) ?: null,
                'employee_id'      => $this->employeeId(),
                'opportunity_id'   => $this->opportunity->opportunity_id,
            ]);
        }

        if ($this->opportunity->stage_id !== Stage::UNTOUCHED) {
            return [$id, false];
        }

        $this->opportunity->moveToStage(Stage::APPOINTED);
        $this->refreshStage();

        return [$id, true];
    }

    #[Computed]
    public function reschedulable(): array
    {
        if (! $this->canCreateAppointment()) {
            return [];
        }

        $options = [];
        foreach ($this->appointments as $group) {
            foreach ($group['rows'] as $row) {
                if ($row->can_reschedule) {
                    $options[$row->appointment_id.'-'.$row->appointment_type] = __('opportunities.apt_option', [
                        'no'   => $group['no'],
                        'type' => __('opportunities.appointment_type_name.'.$row->appointment_type),
                        'time' => $row->scheduled_time->format('d/m/Y H:i'),
                    ]);
                }
            }
        }

        return $options;
    }

    public function startReschedule(): void
    {
        $this->tab = 'appointments';
        $this->closeForm();

        $keys = array_keys($this->reschedulable);
        if (! $keys) {
            return;
        }

        $this->form = 'reschedule';
        $this->editKeys = count($keys) === 1 ? $keys : [];
        $this->updatedEditKeys();
    }

    public function updatedEditKeys(): void
    {
        foreach ($this->editKeys as $key) {
            if (! isset($this->editTimes[$key])) {
                $this->fillEditDefaults($key);
            }
        }
    }

    protected function fillEditDefaults(string $key): void
    {
        [$id, $type] = array_pad(explode('-', $key, 2), 2, '');
        $row = $this->appointments->flatMap(fn ($g) => $g['rows'])
            ->first(fn ($r) => $r->appointment_id === $id && $r->appointment_type === $type);

        if ($row) {
            $this->editTimes[$key] = $row->scheduled_time->format('Y-m-d\TH:i');
        }
    }

    public function saveReschedule(): void
    {
        $options = $this->reschedulable;

        $rules = [
            'editKeys'   => ['required', 'array', 'min:1'],
            'editKeys.*' => [Rule::in(array_keys($options))],
        ];
        $attributes = [];

        foreach ($this->editKeys as $key) {
            $rules["editTimes.$key"] = ['required', 'date', 'after:now'];
            $attributes["editTimes.$key"] = __('opportunities.new_time').' ('.($options[$key] ?? $key).')';
        }

        $this->validate($rules, [
            'editKeys.required'  => __('opportunities.apt_pick_required'),
            'editKeys.min'       => __('opportunities.apt_pick_required'),
            'editKeys.*.in'      => __('opportunities.apt_cannot_reschedule'),
            'editTimes.*.after'  => __('opportunities.apt_time_future'),
        ], $attributes);

        if (! $this->checkEditTimes()) {
            return;
        }

        $this->appointmentService()->reschedule(
            $this->opportunity,
            array_intersect_key($this->editTimes, array_flip($this->editKeys)),
        );

        $this->closeForm();
        $this->notice = ['tone' => 'success', 'text' => __('opportunities.apt_rescheduled')];
        $this->refreshAppointments();
    }

    protected function checkEditTimes(): bool
    {
        foreach ($this->editKeys as $key) {
            [$id] = explode('-', $key, 2);

            if ($clash = $this->appointmentService()->clashFor($this->employeeId(), Carbon::parse($this->editTimes[$key]), $id)) {
                $this->addError("editTimes.$key", $this->appointmentService()->clashMessage($clash));
            }
        }

        return $this->getErrorBag()->isEmpty();
    }

    protected function leadToEdit(): ?Lead
    {
        return $this->isOwner()
            && $this->opportunity->status === Opportunity::STATUS_IN_PROGRESS
            && $this->leadEditableFields() !== []
            ? $this->opportunity->lead
            : null;
    }

    public function leadEditableFields(): array
    {
        $lead = $this->opportunity->lead;

        if (! $lead) {
            return [];
        }

        return array_values(array_intersect(
            app(LeadDuplicateService::class)->editableFields($lead),
            ['full_name', ...LeadDuplicateService::CONTACT_FIELDS],
        ));
    }

    protected function leadUpdated(Lead $lead): void
    {
        $this->opportunity->setRelation('lead', $lead->refresh()->load('branch:branch_id,branch_name,address'));
        $this->notice = ['tone' => 'success', 'text' => __('leads.edit_saved')];
    }

    protected function appointmentService(): AppointmentService
    {
        return app(AppointmentService::class);
    }

    protected function employeeId(): ?string
    {
        return Auth::user()->employee?->employee_id;
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    public function isOwner(): bool
    {
        return $this->routePrefix === Role::SALESPERSON
            && $this->opportunity->employee_id === $this->employeeId();
    }

    #[Computed]
    public function reassignOptions(): Collection
    {
        $me = Auth::user()->employee;
        $closed = $this->stages->firstWhere('stage_id', Stage::CLOSED);

        if ($this->routePrefix !== Role::SALE_LEADER || ! $me?->team_id
            || ! in_array($this->opportunity->status, [Opportunity::STATUS_IN_PROGRESS, Opportunity::STATUS_WON], true)
            || ! $closed || $this->stageOrder() > $closed->sort_order || $this->paidOrEnrolled()) {
            return collect();
        }

        return Employee::query()
            ->where('team_id', $me->team_id)
            ->where('status', 'Working')
            ->where('employee_id', '!=', $this->opportunity->employee_id)
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALESPERSON))
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name']);
    }

    protected function paidOrEnrolled(): bool
    {
        return $this->opportunity->student()->exists()
            || $this->opportunity->quotations()->whereHas('order', fn ($q) => $q->where('paid_amount', '>', 0))->exists();
    }

    #[Computed]
    public function reassignLoad(): array
    {
        $ids = $this->reassignOptions->pluck('employee_id')->push($this->opportunity->employee_id)->filter()->all();

        return app(LeadDistributionService::class)->currentLoad($ids);
    }

    public function startReassign(): void
    {
        $this->resetValidation('newOwner');
        $this->reassigned = null;
        $this->reassigning = $this->reassignOptions->isNotEmpty();
        $this->newOwner = '';
        $this->transferReason = '';
    }

    public function cancelReassign(): void
    {
        $this->reset('reassigning', 'newOwner', 'transferReason', 'confirmingOver');
        $this->resetValidation(['newOwner', 'transferReason']);
    }

    public function updatedNewOwner(): void
    {
        $this->confirmingOver = false;
    }

    public function cancelOverConfirm(): void
    {
        $this->confirmingOver = false;
    }

    #[Computed]
    public function reassignOver(): ?array
    {
        if ($this->newOwner === '') {
            return null;
        }

        $option = $this->reassignOptions->firstWhere('employee_id', $this->newOwner);
        $max = LeadDistributionService::MAX_PER_SALESPERSON;
        $load = $this->reassignLoad[$this->newOwner] ?? 0;

        return $option && $load + 1 > $max
            ? ['name' => $option->full_name, 'load' => $load, 'after' => $load + 1, 'over' => $load + 1 - $max]
            : null;
    }

    public function reassign(bool $confirmed = false): void
    {
        $options = $this->reassignOptions;
        abort_unless($options->isNotEmpty(), 403);

        $this->transferReason = trim($this->transferReason);
        $this->validate(
            [
                'newOwner'       => ['required', Rule::in($options->pluck('employee_id')->all())],
                'transferReason' => ['required', 'string', 'max:1000'],
            ],
            [
                'newOwner.required'       => __('opportunities.reassign_required'),
                'newOwner.in'             => __('opportunities.reassign_required'),
                'transferReason.required' => __('opportunities.reassign_reason_required'),
                'transferReason.max'      => __('opportunities.reassign_reason_max'),
            ],
        );

        if (! $confirmed && $this->reassignOver) {
            $this->confirmingOver = true;

            return;
        }

        $from = $this->opportunity->employee?->full_name;

        $moved = DB::transaction(function () {
            OpportunityTransfer::create([
                'transferred_at'   => now(),
                'reason'           => $this->transferReason,
                'opportunity_id'   => $this->opportunity->opportunity_id,
                'from_employee_id' => $this->opportunity->employee_id,
                'to_employee_id'   => $this->newOwner,
                'transferred_by'   => $this->employeeId(),
            ]);

            $moved = DB::table('Appointments')
                ->where('opportunity_id', $this->opportunity->opportunity_id)
                ->where('status', Appointment::STATUS_SCHEDULED)
                ->update(['employee_id' => $this->newOwner]);

            $quotes = $this->opportunity->quotations()->update(['employee_id' => $this->newOwner]);

            Invoice::query()
                ->whereHas('order.quotation', fn ($q) => $q->where('opportunity_id', $this->opportunity->opportunity_id))
                ->where('status', '!=', Invoice::STATUS_PAID)
                ->update(['created_by_employee_id' => $this->newOwner]);

            $this->opportunity->update(['employee_id' => $this->newOwner]);

            return ['appointments' => $moved, 'quotations' => $quotes];
        });

        $this->opportunity->load('employee.team:team_id,team_name');
        $this->cancelReassign();
        $this->reassigned = [
            'from'   => $from,
            'to'     => $this->opportunity->employee->full_name,
            'moved'  => $moved['appointments'],
            'quotes' => $moved['quotations'],
        ];
        unset($this->reassignOptions, $this->reassignLoad, $this->reassignOver, $this->appointments, $this->careResults, $this->transfers, $this->quotations);
    }

    #[Computed]
    public function transfers(): Collection
    {
        return $this->opportunity->transfers()
            ->with(['fromEmployee:employee_id,full_name', 'toEmployee:employee_id,full_name', 'transferrer:employee_id,full_name'])
            ->orderByDesc('transferred_at')
            ->orderByDesc('transfer_id')
            ->get();
    }

    public function closeReassigned(): void
    {
        $this->reassigned = null;
    }

    protected function stageOrder(): int
    {
        return $this->opportunity->stage?->sort_order ?? 0;
    }

    protected function opportunityAppointments()
    {
        return DB::table('Appointments')
            ->where('opportunity_id', $this->opportunity->opportunity_id);
    }

    protected function refreshAppointments(): void
    {
        unset($this->appointments, $this->rebookTypes, $this->reschedulable);

        $this->dispatch('notifications-refresh');
    }

    #[Computed]
    public function closeReason(): ?string
    {
        return $this->opportunity->stagesHistory()
            ->where('to_stage_id', Stage::ARCHIVED)
            ->orderByDesc('changed_at')
            ->value('notes');
    }

    #[Computed]
    public function stages(): Collection
    {
        return Stage::query()->orderBy('sort_order')->get();
    }

    #[Computed]
    public function visitedStageIds(): array
    {
        return $this->opportunity->stagesHistory()
            ->get(['from_stage_id', 'to_stage_id'])
            ->flatMap(fn ($h) => [$h->from_stage_id, $h->to_stage_id])
            ->filter()
            ->unique()
            ->all();
    }

    #[Computed]
    public function careResults(): Collection
    {
        return $this->opportunity->careResults()
            ->with(['activity', 'employee:employee_id,full_name'])
            ->orderByDesc('performed_at')
            ->get();
    }

    #[Computed]
    public function appointments(): Collection
    {
        $now = now();
        $open = $this->canCreateAppointment();
        $owner = $this->isOwner();

        return Appointment::groupForDisplay(
            $this->opportunity->appointments()
                ->with('employee:employee_id,full_name')
                ->orderBy('scheduled_time')
                ->get()
                ->each(function ($row) use ($now, $open, $owner) {
                    $pending = $row->status === Appointment::STATUS_SCHEDULED;
                    $row->can_mark = $owner && $pending && $row->scheduled_time->lte($now);
                    $row->can_reschedule = $open && $pending && $row->scheduled_time->gt($now);
                })
        );
    }

    #[Computed]
    public function quotations(): Collection
    {
        return $this->opportunity->quotations()
            ->withTotal()
            ->orderByDesc('created_at')
            ->orderByDesc('quotation_id')
            ->get();
    }

    public function canCreateQuotation(): bool
    {
        return $this->isOwner() && $this->opportunity->agreedToRegister();
    }

    public function showQuotations(): bool
    {
        return $this->opportunity->stage_id === Stage::CLOSED || $this->quotations->isNotEmpty();
    }

    public function render()
    {
        return view('livewire.opportunities.show')
            ->title(__('opportunities.detail_title', ['code' => $this->opportunity->opportunity_id]));
    }
}
