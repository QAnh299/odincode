<?php

namespace App\Livewire\Opportunities;

use App\Models\Appointment;
use App\Models\CareActivity;
use App\Models\CareResult;
use App\Models\Employee;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\Stage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Chi tiết Opportunity: thanh giai đoạn, thông tin khách hàng,
 * 2 tab Lịch sử chăm sóc (mặc định) / Lịch hẹn, và báo giá.
 * Dùng chung cho Salesperson, Sale Leader, Giám đốc – xem được theo Opportunity::visibleTo().
 * Mọi thao tác (liên hệ, lịch hẹn, báo giá) chỉ dành cho salesperson đang phụ trách;
 * Sale Leader / Giám đốc chỉ xem. Sale Leader được chuyển người phụ trách sang salesperson khác
 * trong đội (khi cơ hội chưa tới stage Chốt) – lịch hẹn đang chờ chuyển theo.
 *
 * Lịch hẹn: mỗi mã lịch hẹn gồm 1–2 dòng (Test và/hoặc Tư vấn), mỗi dòng có thời gian và kết quả
 * riêng. Địa điểm cố định là cơ sở của khách (Leads.branch_id) nên không lưu trong lịch hẹn. Hàng nút ở đầu tab: Đổi lịch – Tạo lịch hẹn.
 *   - Đổi lịch: chọn dòng muốn đổi (chưa tới giờ hẹn, chưa có kết quả) rồi nhập thời gian / địa điểm mới.
 *   - Tạo lịch hẹn: hẹn lại loại khách không đến (chưa từng đến, chưa có lịch chờ); chỉ tạo được
 *     đúng các loại đó. Lịch hẹn đầu tiên tạo khi ghi nhận liên hệ thành công. Lịch hẹn đầu tiên chuyển "Data chưa tương tác" → "Data đã có lịch hẹn".
 *   - Từ giờ hẹn trở đi: cập nhật từng dòng Khách đã đến (Success) / Khách không đến (Failed).
 *     Mọi loại đã hẹn đều có lần khách đến → tự chuyển sang "Data đã xử lý".
 *   - Không tạo / đổi lịch hẹn khi đã tới stage Chốt.
 * Lịch sử chăm sóc: liên hệ để chốt lịch hẹn – ghi nhận lần liên hệ tiếp theo (Liên hệ lần 1 → 4,
 * theo danh mục CareActivities) với thời gian, kết quả, ghi chú. Chỉ khi chưa tới stage Chốt và
 * chưa có lần liên hệ thành công. Liên hệ thành công = đã chốt lịch hẹn → bắt buộc nhập lịch hẹn đó,
 * lưu cùng lúc (lịch hẹn đầu tiên chỉ được tạo theo cách này).
 * Phần Báo giá chỉ hiện ở stage Chốt (hoặc khi đã có báo giá);
 * nút "Tạo báo giá" chỉ hiện ở stage Chốt (hiện chỉ hiển thị, chưa có chức năng).
 */
#[Layout('layouts.app')]
class Show extends Component
{
    const TABS = ['care', 'appointments'];

    public Opportunity $opportunity;

    #[Url(except: 'care')]
    public string $tab = 'care';

    // Thông báo sau khi thao tác lịch hẹn: ['tone' => success|danger, 'text' => ...]
    public ?array $notice = null;

    // Chuyển người phụ trách (Sale Leader)
    public bool $reassigning = false;

    public string $newOwner = '';

    // Popup chuyển thành công: ['from' => tên cũ, 'to' => tên mới, 'moved' => số lịch hẹn đang chờ đã chuyển]
    public ?array $reassigned = null;

    // Form đang mở: null | 'care' (tab Lịch sử chăm sóc) | 'create' | 'reschedule' (tab Lịch hẹn)
    public ?string $form = null;

    // Form ghi nhận liên hệ
    public string $careTime = '';

    public string $careResult = '';

    public string $careNotes = '';

    // Form tạo lịch hẹn
    public array $newTypes = [];

    // Thời gian riêng cho từng loại: ['Test' => ..., 'Consultation' => ...]
    public array $newTimes = [];

    public string $newNotes = '';

    // Form đổi lịch: các dòng được chọn, dạng "APT006-Test"; thời gian mới riêng cho từng dòng
    public array $editKeys = [];

    public array $editTimes = [];

    public function mount(Opportunity $opportunity): void
    {
        abort_unless(
            Opportunity::query()->visibleTo(Auth::user()->employee)->whereKey($opportunity->getKey())->exists(),
            404
        );

        $this->opportunity = $opportunity->load([
            'lead.branch:branch_id,branch_name,address', 'stage', 'student', 'employee.team:team_id,team_name',
        ]);
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
        $this->reset('form', 'careTime', 'careResult', 'careNotes', 'newTypes', 'newTimes', 'newNotes', 'editKeys', 'editTimes');
        $this->resetValidation();
    }

    // ── Ghi nhận liên hệ (chăm sóc) ───────────────────────────────────

    /**
     * Lần liên hệ tiếp theo: hoạt động đầu tiên (theo mã) chưa ghi nhận cho Opportunity này.
     */
    #[Computed]
    public function nextActivity(): ?CareActivity
    {
        $done = $this->opportunity->careResults()->pluck('activity_id');

        return CareActivity::query()->whereNotIn('activity_id', $done)->orderBy('activity_id')->first();
    }

    /**
     * Đã có lần liên hệ thành công (đã chốt được lịch hẹn).
     */
    #[Computed]
    public function contactDone(): bool
    {
        return $this->opportunity->careResults()->where('result', CareResult::RESULT_SUCCESS)->exists();
    }

    /**
     * Cần liên hệ: chưa tới stage Chốt và chưa có lần liên hệ thành công.
     */
    public function needsContact(): bool
    {
        return $this->canCreateAppointment() && ! $this->contactDone;
    }

    /**
     * Được ghi nhận liên hệ: cần liên hệ và còn lần liên hệ (tối đa 4 lần).
     */
    public function canLogCare(): bool
    {
        return $this->needsContact() && $this->nextActivity !== null;
    }

    /**
     * Thời điểm sớm nhất được ghi: sau lần liên hệ trước và sau ngày chuyển đổi.
     */
    protected function earliestCareTime(): Carbon
    {
        $last = $this->opportunity->careResults()->max('performed_at');

        return collect([$this->opportunity->conversion_date, $last ? Carbon::parse($last) : null])
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
        $this->newTypes = Appointment::TYPES;
        $this->fillNewDefaults();
    }

    public function saveCare(): void
    {
        abort_unless($this->canLogCare(), 403);

        $earliest = $this->earliestCareTime();
        $success = $this->careResult === CareResult::RESULT_SUCCESS;

        // Liên hệ thành công = đã chốt lịch hẹn với khách → bắt buộc nhập lịch hẹn đó
        $this->validate([
            'careTime'   => ['required', 'date', 'before_or_equal:now', 'after_or_equal:'.$earliest->format('Y-m-d H:i')],
            'careResult' => ['required', Rule::in([CareResult::RESULT_SUCCESS, CareResult::RESULT_FAILED])],
            'careNotes'  => ['nullable', 'string', 'max:1000'],
        ] + ($success ? $this->appointmentRules(Appointment::TYPES) : []), [
            'careTime.before_or_equal' => __('opportunities.care_time_future'),
            'careTime.after_or_equal'  => __('opportunities.care_time_early', ['time' => $earliest->format('d/m/Y H:i')]),
            'careResult.required'      => __('opportunities.care_result_required'),
        ] + $this->appointmentMessages(), [
            'careTime'  => __('opportunities.care_time'),
            'careNotes' => __('opportunities.notes'),
        ] + $this->appointmentAttributes());

        $activity = $this->nextActivity;

        [$appointmentId, $moved] = DB::transaction(function () use ($activity, $success) {
            CareResult::create([
                'performed_at'   => Carbon::parse($this->careTime),
                'result'         => $this->careResult,
                'notes'          => trim($this->careNotes) ?: null,
                'activity_id'    => $activity->activity_id,
                'opportunity_id' => $this->opportunity->opportunity_id,
                'employee_id'    => $this->employeeId(),
            ]);

            return $success ? $this->insertAppointment() : [null, false];
        });

        $this->closeForm();
        $this->notice = ['tone' => 'success', 'text' => match (true) {
            $moved   => __('opportunities.care_saved_apt_moved', ['activity' => $activity->activity_name, 'id' => $appointmentId, 'stage' => $this->opportunity->stage->stage_name]),
            $success => __('opportunities.care_saved_apt', ['activity' => $activity->activity_name, 'id' => $appointmentId]),
            default  => __('opportunities.care_saved', ['activity' => $activity->activity_name]),
        }];
        unset($this->careResults, $this->nextActivity, $this->contactDone);
        $this->refreshAppointments();
    }

    // ── Cập nhật kết quả lịch hẹn ─────────────────────────────────────

    /**
     * Khách đã đến (Success) / Khách không đến (Failed) cho một dòng lịch hẹn.
     * Chỉ dòng còn "Scheduled", đã tới giờ hẹn, thuộc Opportunity này và do chính mình phụ trách.
     */
    public function markAppointment(string $appointmentId, string $type, string $status): void
    {
        abort_unless($this->isOwner(), 403);

        $this->tab = 'appointments';

        if (! in_array($status, [Appointment::STATUS_SUCCESS, Appointment::STATUS_FAILED], true)) {
            return;
        }

        $updated = $this->ownAppointments()
            ->where('appointment_id', $appointmentId)
            ->where('appointment_type', $type)
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->where('scheduled_time', '<=', now())
            ->update(['status' => $status]);

        if (! $updated) {
            $this->notice = ['tone' => 'danger', 'text' => __('opportunities.apt_cannot_mark')];

            return;
        }

        $this->closeForm();
        $this->notice = match (true) {
            $this->advanceStageIfDone() => ['tone' => 'success', 'text' => __('opportunities.apt_stage_moved', ['stage' => $this->opportunity->stage->stage_name])],
            $status === Appointment::STATUS_FAILED && $this->canCreateAppointment() => ['tone' => 'success', 'text' => __('opportunities.apt_marked_failed')],
            default => ['tone' => 'success', 'text' => __('opportunities.apt_marked')],
        };

        $this->refreshAppointments();
    }

    /**
     * Mọi loại lịch hẹn của Opportunity (Test, Tư vấn) đều đã có lần khách đến
     * → chuyển sang "Data đã xử lý" nếu đang ở stage trước đó. Trả về true nếu đã chuyển.
     */
    protected function advanceStageIfDone(): bool
    {
        $processed = $this->stages->firstWhere('stage_id', Stage::PROCESSED);

        if (! $processed || $this->stageOrder() >= $processed->sort_order) {
            return false;
        }

        $rows = $this->ownAppointments()->get(['appointment_type', 'status']);
        $types = $rows->pluck('appointment_type')->unique();
        $done = $rows->where('status', Appointment::STATUS_SUCCESS)->pluck('appointment_type')->unique();

        if ($types->isEmpty() || $types->diff($done)->isNotEmpty()) {
            return false;
        }

        $this->opportunity->update(['stage_id' => $processed->stage_id]);
        $this->opportunity->setRelation('stage', $processed);

        return true;
    }

    // ── Tạo lịch hẹn ──────────────────────────────────────────────────

    /**
     * Opportunity chưa tới stage Chốt (Chốt / Lưu trữ không cần hẹn nữa).
     */
    public function canCreateAppointment(): bool
    {
        if (! $this->isOwner()) {
            return false;
        }

        $closed = $this->stages->firstWhere('stage_id', Stage::CLOSED);

        return $closed && $this->stageOrder() < $closed->sort_order;
    }

    /**
     * Loại được tạo lịch hẹn bằng nút "Tạo lịch hẹn": loại đã có lần khách không đến, chưa từng đến
     * và chưa có lịch chờ (hẹn lại). Chưa có lịch hẹn nào thì lịch đầu tiên tạo khi ghi nhận liên hệ
     * thành công (trừ dữ liệu cũ đã liên hệ thành công mà chưa có lịch hẹn).
     */
    #[Computed]
    public function rebookTypes(): array
    {
        if (! $this->canCreateAppointment()) {
            return [];
        }

        $rows = $this->ownAppointments()->get(['appointment_type', 'status']);

        if ($rows->isEmpty()) {
            return $this->contactDone ? Appointment::TYPES : [];
        }

        return array_values(array_filter(Appointment::TYPES, function ($type) use ($rows) {
            $statuses = $rows->where('appointment_type', $type)->pluck('status');

            return $statuses->contains(Appointment::STATUS_FAILED)
                && ! $statuses->contains(Appointment::STATUS_SUCCESS)
                && ! $statuses->contains(Appointment::STATUS_SCHEDULED);
        }));
    }

    /**
     * Địa điểm lịch hẹn: cố định là cơ sở của khách (Leads.branch_id) – "Tên cơ sở - Địa chỉ".
     */
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

        [, $moved] = DB::transaction(fn () => $this->insertAppointment());

        $this->closeForm();
        $this->notice = $moved
            ? ['tone' => 'success', 'text' => __('opportunities.apt_created_moved', ['stage' => $this->opportunity->stage->stage_name])]
            : ['tone' => 'success', 'text' => __('opportunities.apt_created')];
        $this->refreshAppointments();
    }

    /**
     * Thời gian trống cho từng loại lịch hẹn.
     */
    protected function fillNewDefaults(): void
    {
        $this->newTimes = array_fill_keys(Appointment::TYPES, '');
    }

    /**
     * Kiểm tra form lịch hẹn (newTypes, newTimes, newNotes) – dùng cho cả
     * nút "Tạo lịch hẹn" và form ghi nhận liên hệ thành công.
     * Mỗi loại được chọn (Test / Tư vấn) có thời gian riêng; địa điểm là cơ sở của khách.
     */
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

    /**
     * Ghi lịch hẹn mới từ form (mỗi loại một dòng, cùng mã, thời gian riêng). Lịch hẹn đầu tiên chuyển
     * "Data chưa tương tác" → "Data đã có lịch hẹn". Gọi trong transaction.
     * Trả về [mã lịch hẹn, đã chuyển stage hay chưa].
     */
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

        $this->opportunity->update(['stage_id' => Stage::APPOINTED]);
        $this->opportunity->setRelation('stage', $this->stages->firstWhere('stage_id', Stage::APPOINTED));

        return [$id, true];
    }

    // ── Đổi thời gian / địa điểm ──────────────────────────────────────

    /**
     * Dòng lịch hẹn đổi được: chưa có kết quả, chưa tới giờ hẹn, chưa tới stage Chốt.
     * Khoá "APT006-Test" => nhãn hiển thị trong form.
     */
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

        // Chỉ một lịch đổi được thì chọn sẵn; nhiều lịch thì để salesperson tự chọn
        $this->form = 'reschedule';
        $this->editKeys = count($keys) === 1 ? $keys : [];
        $this->updatedEditKeys();
    }

    /**
     * Tick thêm lịch nào thì điền sẵn thời gian hiện tại của lịch đó.
     */
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

        DB::transaction(function () {
            foreach ($this->editKeys as $key) {
                [$id, $type] = explode('-', $key, 2);

                $this->ownAppointments()
                    ->where('appointment_id', $id)
                    ->where('appointment_type', $type)
                    ->where('status', Appointment::STATUS_SCHEDULED)
                    ->where('scheduled_time', '>', now())
                    ->update(['scheduled_time' => Carbon::parse($this->editTimes[$key])]);
            }
        });

        $this->closeForm();
        $this->notice = ['tone' => 'success', 'text' => __('opportunities.apt_rescheduled')];
        $this->refreshAppointments();
    }

    // ── Dữ liệu ───────────────────────────────────────────────────────

    protected function employeeId(): ?string
    {
        return Auth::user()->employee?->employee_id;
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * Salesperson đang phụ trách cơ hội – người duy nhất được thao tác.
     */
    public function isOwner(): bool
    {
        return $this->routePrefix === Role::SALESPERSON
            && $this->opportunity->employee_id === $this->employeeId();
    }

    // ── Chuyển người phụ trách (Sale Leader) ──────────────────────────

    /**
     * Salesperson đang làm việc trong đội của Leader (trừ người đang phụ trách),
     * chỉ khi cơ hội chưa tới stage Chốt.
     */
    #[Computed]
    public function reassignOptions(): Collection
    {
        $me = Auth::user()->employee;
        $closed = $this->stages->firstWhere('stage_id', Stage::CLOSED);

        if ($this->routePrefix !== Role::SALE_LEADER || ! $me?->team_id
            || ! $closed || $this->stageOrder() >= $closed->sort_order) {
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

    public function startReassign(): void
    {
        $this->resetValidation('newOwner');
        $this->reassigned = null;
        $this->reassigning = $this->reassignOptions->isNotEmpty();
        $this->newOwner = '';
    }

    public function cancelReassign(): void
    {
        $this->reset('reassigning', 'newOwner');
        $this->resetValidation('newOwner');
    }

    public function reassign(): void
    {
        $options = $this->reassignOptions;
        abort_unless($options->isNotEmpty(), 403);

        $this->validate(
            ['newOwner' => ['required', Rule::in($options->pluck('employee_id')->all())]],
            ['newOwner.required' => __('opportunities.reassign_required'), 'newOwner.in' => __('opportunities.reassign_required')],
        );

        $from = $this->opportunity->employee?->full_name;

        $moved = DB::transaction(function () {
            // Lịch hẹn đang chờ chuyển sang người mới; lịch đã có kết quả và lịch sử chăm sóc giữ nguyên người thực hiện
            $moved = DB::table('Appointments')
                ->where('opportunity_id', $this->opportunity->opportunity_id)
                ->where('status', Appointment::STATUS_SCHEDULED)
                ->update(['employee_id' => $this->newOwner]);

            $this->opportunity->update(['employee_id' => $this->newOwner]);

            return $moved;
        });

        $this->opportunity->load('employee.team:team_id,team_name');
        $this->cancelReassign();
        $this->reassigned = ['from' => $from, 'to' => $this->opportunity->employee->full_name, 'moved' => $moved];
        unset($this->reassignOptions, $this->appointments, $this->careResults);
    }

    public function closeReassigned(): void
    {
        $this->reassigned = null;
    }

    protected function stageOrder(): int
    {
        return $this->opportunity->stage?->sort_order ?? 0;
    }

    /**
     * Lịch hẹn của Opportunity này do chính mình phụ trách.
     */
    protected function ownAppointments()
    {
        return DB::table('Appointments')
            ->where('opportunity_id', $this->opportunity->opportunity_id)
            ->where('employee_id', $this->employeeId());
    }

    protected function refreshAppointments(): void
    {
        unset($this->appointments, $this->rebookTypes, $this->reschedulable);
    }

    #[Computed]
    public function stages(): Collection
    {
        return Stage::query()->orderBy('sort_order')->get();
    }

    #[Computed]
    public function careResults(): Collection
    {
        return $this->opportunity->careResults()
            ->with(['activity', 'employee:employee_id,full_name'])
            ->orderByDesc('performed_at')
            ->get();
    }

    /**
     * Lịch hẹn gom theo mã: mới nhất lên đầu, "lần N" đánh theo thời gian tăng dần.
     * Mỗi nhóm: id, no, rows (Test trước, Tư vấn sau). Mỗi dòng thêm can_mark, can_reschedule.
     */
    #[Computed]
    public function appointments(): Collection
    {
        $now = now();
        $open = $this->canCreateAppointment();
        $owner = $this->isOwner();

        return $this->opportunity->appointments()
            ->orderBy('scheduled_time')
            ->get()
            ->each(function ($row) use ($now, $open, $owner) {
                $pending = $row->status === Appointment::STATUS_SCHEDULED;
                $row->can_mark = $owner && $pending && $row->scheduled_time->lte($now);
                $row->can_reschedule = $open && $pending && $row->scheduled_time->gt($now);
            })
            ->groupBy('appointment_id')
            ->values()
            ->map(fn (Collection $rows, int $i) => [
                'id'   => $rows->first()->appointment_id,
                'no'   => $i + 1,
                'rows' => $rows->sortBy(fn ($r) => array_search($r->appointment_type, Appointment::TYPES))->values(),
            ])
            ->reverse()
            ->values();
    }

    #[Computed]
    public function quotations(): Collection
    {
        return $this->opportunity->quotations()
            ->withSum('details as total', 'line_total')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Nút "Tạo báo giá": chỉ salesperson phụ trách, ở stage Chốt.
     */
    public function canCreateQuotation(): bool
    {
        return $this->isOwner() && $this->opportunity->stage_id === Stage::CLOSED;
    }

    /**
     * Stage 1–3 chưa có báo giá nên ẩn phần Báo giá.
     */
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
