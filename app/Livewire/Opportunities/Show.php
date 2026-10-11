<?php

namespace App\Livewire\Opportunities;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CareActivity;
use App\Models\CareResult;
use App\Models\Opportunity;
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
 * Chi tiết Opportunity của Salesperson: thanh giai đoạn, thông tin khách hàng,
 * 2 tab Lịch sử chăm sóc (mặc định) / Lịch hẹn, và báo giá.
 * Chỉ xem được Opportunity do chính mình phụ trách.
 *
 * Lịch hẹn: mỗi mã lịch hẹn gồm 1–2 dòng (Test và/hoặc Tư vấn), mỗi dòng có thời gian,
 * địa điểm (chọn trong các cơ sở đang hoạt động) và kết quả riêng. Hàng nút ở đầu tab: Đổi lịch – Tạo lịch hẹn.
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

    // Form đang mở: null | 'care' (tab Lịch sử chăm sóc) | 'create' | 'reschedule' (tab Lịch hẹn)
    public ?string $form = null;

    // Form ghi nhận liên hệ
    public string $careTime = '';

    public string $careResult = '';

    public string $careNotes = '';

    // Form tạo lịch hẹn
    public array $newTypes = [];

    // Thời gian / cơ sở riêng cho từng loại: ['Test' => ..., 'Consultation' => ...]
    public array $newTimes = [];

    public array $newLocations = [];

    public string $newNotes = '';

    // Form đổi lịch: các dòng được chọn, dạng "APT006-Test"; thời gian / cơ sở mới riêng cho từng dòng
    public array $editKeys = [];

    public array $editTimes = [];

    public array $editLocations = [];

    public function mount(Opportunity $opportunity): void
    {
        abort_unless($opportunity->employee_id === $this->employeeId(), 404);

        $this->opportunity = $opportunity->load(['lead.branch:branch_id,branch_name,address', 'stage', 'student']);
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
        $this->reset('form', 'careTime', 'careResult', 'careNotes', 'newTypes', 'newTimes', 'newLocations', 'newNotes', 'editKeys', 'editTimes', 'editLocations');
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
     * Địa điểm lịch hẹn: chỉ chọn trong các cơ sở đang hoạt động, lưu dạng "Tên cơ sở - Địa chỉ".
     */
    #[Computed]
    public function branchOptions(): array
    {
        return Branch::query()
            ->where('status', 'Active')
            ->orderBy('branch_id')
            ->get(['branch_name', 'address'])
            ->map(fn ($b) => $b->branch_name.' - '.$b->address)
            ->all();
    }

    /**
     * Cơ sở mặc định: cơ sở của khách (nếu đang hoạt động), không thì cơ sở đầu tiên.
     */
    protected function defaultLocation(): string
    {
        $branch = $this->opportunity->lead?->branch;
        $own = $branch ? $branch->branch_name.' - '.$branch->address : null;

        return in_array($own, $this->branchOptions, true) ? $own : ($this->branchOptions[0] ?? '');
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
     * Thời gian trống, cơ sở mặc định là cơ sở của khách – cho từng loại lịch hẹn.
     */
    protected function fillNewDefaults(): void
    {
        $this->newTimes = array_fill_keys(Appointment::TYPES, '');
        $this->newLocations = array_fill_keys(Appointment::TYPES, $this->defaultLocation());
    }

    /**
     * Kiểm tra form lịch hẹn (newTypes, newTimes, newLocations, newNotes) – dùng cho cả
     * nút "Tạo lịch hẹn" và form ghi nhận liên hệ thành công.
     * Mỗi loại được chọn (Test / Tư vấn) có thời gian và cơ sở riêng.
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
            $rules["newLocations.$type"] = ['required', Rule::in($this->branchOptions)];
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
            'newLocations.*.in'  => __('opportunities.apt_location_invalid'),
        ];
    }

    protected function appointmentAttributes(): array
    {
        $attributes = ['newNotes' => __('opportunities.apt_notes')];

        foreach (Appointment::TYPES as $type) {
            $name = __('opportunities.appointment_type_name.'.$type);
            $attributes["newTimes.$type"] = __('opportunities.appointment_time').' ('.$name.')';
            $attributes["newLocations.$type"] = __('opportunities.location').' ('.$name.')';
        }

        return $attributes;
    }

    /**
     * Ghi lịch hẹn mới từ form (mỗi loại một dòng, cùng mã, thời gian / cơ sở riêng). Lịch hẹn đầu tiên chuyển
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
                'location'         => $this->newLocations[$type],
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
     * Tick thêm lịch nào thì điền sẵn thời gian / cơ sở hiện tại của lịch đó.
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
            // Lịch cũ ở địa điểm không còn trong danh sách cơ sở → gợi ý cơ sở của khách
            $this->editLocations[$key] = in_array($row->location, $this->branchOptions, true) ? $row->location : $this->defaultLocation();
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
            $rules["editLocations.$key"] = ['required', Rule::in($this->branchOptions)];
            $attributes["editTimes.$key"] = __('opportunities.new_time').' ('.($options[$key] ?? $key).')';
            $attributes["editLocations.$key"] = __('opportunities.new_location').' ('.($options[$key] ?? $key).')';
        }

        $this->validate($rules, [
            'editKeys.required'  => __('opportunities.apt_pick_required'),
            'editKeys.min'       => __('opportunities.apt_pick_required'),
            'editKeys.*.in'      => __('opportunities.apt_cannot_reschedule'),
            'editTimes.*.after'  => __('opportunities.apt_time_future'),
            'editLocations.*.in' => __('opportunities.apt_location_invalid'),
        ], $attributes);

        DB::transaction(function () {
            foreach ($this->editKeys as $key) {
                [$id, $type] = explode('-', $key, 2);

                $this->ownAppointments()
                    ->where('appointment_id', $id)
                    ->where('appointment_type', $type)
                    ->where('status', Appointment::STATUS_SCHEDULED)
                    ->where('scheduled_time', '>', now())
                    ->update([
                        'scheduled_time' => Carbon::parse($this->editTimes[$key]),
                        'location'       => $this->editLocations[$key],
                    ]);
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
        return $this->opportunity->careResults()->with('activity')->orderByDesc('performed_at')->get();
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

        return $this->opportunity->appointments()
            ->orderBy('scheduled_time')
            ->get()
            ->each(function ($row) use ($now, $open) {
                $pending = $row->status === Appointment::STATUS_SCHEDULED;
                $row->can_mark = $pending && $row->scheduled_time->lte($now);
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

    public function canCreateQuotation(): bool
    {
        return $this->opportunity->stage_id === Stage::CLOSED;
    }

    /**
     * Stage 1–3 chưa có báo giá nên ẩn phần Báo giá.
     */
    public function showQuotations(): bool
    {
        return $this->canCreateQuotation() || $this->quotations->isNotEmpty();
    }

    public function render()
    {
        return view('livewire.opportunities.show')
            ->title(__('opportunities.detail_title', ['code' => $this->opportunity->opportunity_id]));
    }
}
