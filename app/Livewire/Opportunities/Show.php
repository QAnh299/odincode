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
 *     đúng các loại đó. Lịch hẹn đầu tiên đặt sau khi có lần liên hệ thành công (nút "Tạo lịch hẹn" ở tab Lịch hẹn).
 *     Lịch hẹn đầu tiên chuyển "Data chưa tương tác" → "Data đã có lịch hẹn".
 *   - Từ giờ hẹn trở đi: cập nhật từng dòng Khách đã đến (Success) / Khách không đến (Failed).
 *     Mọi loại đã hẹn đều có lần khách đến → tự chuyển sang "Data đã xử lý".
 *   - Không tạo / đổi lịch hẹn khi đã tới stage Chốt.
 * Lịch sử chăm sóc: ghi nhận lần liên hệ (thời gian, kết quả, ghi chú) với hoạt động theo stage, đếm từ lúc
 * Opportunity vào stage (StagesHistory). Kết quả: Thành công (gọi được) / Thất bại (khách từ chối – bắt buộc
 * ghi lý do) / Không liên lạc được (không nghe máy, thuê bao).
 *   - Data chưa tương tác – "Liên hệ ban đầu": Thất bại → Lost; đủ 4 lần không liên lạc được → Archived;
 *     Thành công → "Tạo lịch hẹn" (→ Data đã có lịch hẹn) / "Tạo báo giá" (→ Chốt), hoặc liên hệ tiếp.
 *   - Data đã có lịch hẹn – "Xếp lại lịch" (khi khách không đến): Thất bại → Lost; không liên lạc được quá
 *     7 ngày từ buổi khách không đến → Archived; Thành công → "Tạo lịch hẹn".
 *   - Data đã xử lý – "Chốt đăng ký": Thành công → nút "Tạo báo giá" (→ Chốt); Thất bại → quay về
 *     Data chưa tương tác (quá 3 lần → Archived); không liên lạc được quá 14 ngày từ lần liên hệ đầu tiên
 *     ở stage → Archived.
 * Lost / Archived: chuyển sang stage Lưu trữ (lý do ghi vào StagesHistory), xoá lịch hẹn chưa diễn ra.
 * Phần Báo giá chỉ hiện ở stage Chốt (hoặc khi đã có báo giá).
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

    // Hoạt động liên hệ theo stage
    const STAGE_ACTIVITIES = [
        Stage::UNTOUCHED => CareActivity::INITIAL,
        Stage::APPOINTED => CareActivity::RESCHEDULE,
        Stage::PROCESSED => CareActivity::DECISION,
    ];

    // Data chưa tương tác: đủ số lần không liên lạc được này → Archived
    const MAX_UNREACHABLE_CONTACTS = 4;

    // Không liên lạc được quá số ngày này → Archived: Data đã có lịch hẹn (tính từ buổi khách không đến),
    // Data đã xử lý (tính từ lần liên hệ đầu tiên ở stage)
    const UNREACHABLE_DAYS = [
        Stage::APPOINTED => 7,
        Stage::PROCESSED => 14,
    ];

    // Khách từ chối đăng ký ở Data đã xử lý → quay về Data chưa tương tác; quá số lần này → Archived
    const MAX_RETURNS = 3;

    /**
     * Hoạt động của lần liên hệ tiếp theo theo stage hiện tại: "Liên hệ ban đầu" (Data chưa tương tác),
     * "Xếp lại lịch" (Data đã có lịch hẹn) hoặc "Chốt đăng ký" (Data đã xử lý).
     */
    #[Computed]
    public function nextActivity(): ?CareActivity
    {
        $id = self::STAGE_ACTIVITIES[$this->opportunity->stage_id] ?? null;

        return $id ? CareActivity::find($id) : null;
    }

    /**
     * Thời điểm Opportunity vào stage hiện tại: lần chuyển sang stage này gần nhất trong StagesHistory,
     * hoặc ngày chuyển đổi nếu chưa có.
     */
    #[Computed]
    public function stageSince(): Carbon
    {
        $changed = $this->opportunity->stagesHistory()
            ->where('to_stage_id', $this->opportunity->stage_id)
            ->max('changed_at');

        // Làm tròn xuống phút: thời gian liên hệ chỉ nhập tới phút, lần liên hệ trong cùng phút vẫn tính vào stage này
        return $changed ? Carbon::parse($changed)->startOfMinute() : $this->opportunity->conversion_date;
    }

    /**
     * Các lần liên hệ của hoạt động theo stage hiện tại, kể từ lúc Opportunity vào stage này.
     */
    protected function stageContacts()
    {
        return $this->opportunity->careResults()
            ->where('activity_id', $this->nextActivity?->activity_id)
            ->where('performed_at', '>=', $this->stageSince);
    }

    /**
     * Số lần không liên lạc được (không nghe máy, thuê bao) ở stage hiện tại.
     */
    #[Computed]
    public function unreachableContacts(): int
    {
        return $this->stageContacts()->where('result', CareResult::RESULT_UNREACHABLE)->count();
    }

    /**
     * Đã có lần liên hệ thành công ở stage hiện tại.
     */
    #[Computed]
    public function stageContactDone(): bool
    {
        return $this->stageContacts()->where('result', CareResult::RESULT_SUCCESS)->exists();
    }

    /**
     * Buổi khách không đến gần nhất của Opportunity (thời gian hẹn), null nếu chưa có.
     */
    protected function lastNoShow(): ?Carbon
    {
        $time = $this->opportunity->appointments()->where('status', Appointment::STATUS_FAILED)->max('scheduled_time');

        return $time ? Carbon::parse($time) : null;
    }

    /**
     * Cần liên hệ: salesperson phụ trách, Opportunity ở stage có hoạt động liên hệ. Ở Data đã có lịch hẹn
     * chỉ liên hệ (xếp lại lịch) khi có buổi khách không đến chưa được hẹn lại.
     */
    public function needsContact(): bool
    {
        if (! $this->canCreateAppointment() || $this->nextActivity === null) {
            return false;
        }

        return $this->opportunity->stage_id !== Stage::APPOINTED || $this->rebookTypes !== [];
    }

    /**
     * Được ghi nhận liên hệ: cần liên hệ và (ở Data chưa tương tác) chưa đủ số lần không liên lạc được.
     */
    public function canLogCare(): bool
    {
        return $this->needsContact()
            && ($this->opportunity->stage_id !== Stage::UNTOUCHED || $this->unreachableContacts < self::MAX_UNREACHABLE_CONTACTS);
    }

    /**
     * Thời điểm sớm nhất được ghi: sau lần liên hệ trước và sau lúc vào stage hiện tại.
     */
    protected function earliestCareTime(): Carbon
    {
        $last = $this->opportunity->careResults()->max('performed_at');

        return collect([$this->stageSince, $last ? Carbon::parse($last) : null])
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

        // Thất bại (khách từ chối) bắt buộc ghi lý do – lý do được lưu vào lịch sử chuyển stage
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

            unset($this->careResults, $this->unreachableContacts, $this->stageContactDone);

            return $this->applyCareOutcome($result, $time, $notes, $activity->activity_name);
        });

        $this->closeForm();
        $this->notice = ['tone' => 'success', 'text' => $text];
        $this->refreshAppointments();
    }

    /**
     * Xử lý sau khi ghi nhận liên hệ, theo kết quả và stage; trả về nội dung thông báo.
     *   - Thất bại (khách từ chối): Data đã xử lý → quay về Data chưa tương tác (quá 3 lần → Archived);
     *     stage khác → Lost.
     *   - Không liên lạc được: vượt giới hạn của stage → Archived.
     */
    protected function applyCareOutcome(string $result, Carbon $time, string $notes, string $activity): string
    {
        $params = ['activity' => $activity];

        if ($result === CareResult::RESULT_FAILED) {
            if ($this->opportunity->stage_id !== Stage::PROCESSED) {
                return $this->closeOpportunity(Opportunity::STATUS_LOST, 'Khách từ chối: '.$notes, 'opportunities.care_closed_lost', $params);
            }

            // Lần quay về này vượt giới hạn → lưu trữ luôn từ Data đã xử lý
            $returns = $this->opportunity->returnsToUntouched() + 1;

            if ($returns > self::MAX_RETURNS) {
                return $this->closeOpportunity(
                    Opportunity::STATUS_ARCHIVED,
                    'Khách từ chối đăng ký quá '.self::MAX_RETURNS.' lần: '.$notes,
                    'opportunities.care_closed_returns',
                    $params + ['max' => self::MAX_RETURNS],
                );
            }

            $this->opportunity->returnToUntouched('Khách từ chối đăng ký: '.$notes);
            $this->refreshStage();

            return __('opportunities.care_returned', $params + [
                'stage' => $this->opportunity->stage->stage_name, 'count' => $returns, 'max' => self::MAX_RETURNS,
            ]);
        }

        $reason = $result === CareResult::RESULT_UNREACHABLE ? $this->unreachableReason($time) : null;

        return $reason
            ? $this->closeOpportunity(Opportunity::STATUS_ARCHIVED, $reason, 'opportunities.care_closed_unreachable', $params)
            : __('opportunities.care_saved', $params);
    }

    /**
     * Lý do lưu trữ khi không liên lạc được đã vượt giới hạn của stage, null nếu chưa vượt:
     *   - Data chưa tương tác: đủ 4 lần không liên lạc được;
     *   - Data đã có lịch hẹn: quá 7 ngày kể từ buổi khách không đến;
     *   - Data đã xử lý: quá 14 ngày kể từ lần liên hệ đầu tiên ở stage.
     */
    protected function unreachableReason(Carbon $time): ?string
    {
        $stage = $this->opportunity->stage_id;

        if ($stage === Stage::UNTOUCHED) {
            return $this->unreachableContacts >= self::MAX_UNREACHABLE_CONTACTS
                ? 'Không liên hệ được ('.self::MAX_UNREACHABLE_CONTACTS.' lần không liên lạc được)'
                : null;
        }

        $days = self::UNREACHABLE_DAYS[$stage] ?? null;
        $from = match ($stage) {
            Stage::APPOINTED => $this->lastNoShow(),
            Stage::PROCESSED => ($first = $this->stageContacts()->min('performed_at')) ? Carbon::parse($first) : null,
            default          => null,
        };

        if (! $days || ! $from || $time->lte($from->copy()->addDays($days))) {
            return null;
        }

        return $stage === Stage::APPOINTED
            ? 'Không liên lạc được quá '.$days.' ngày kể từ buổi khách không đến'
            : 'Không liên lạc được quá '.$days.' ngày kể từ lần liên hệ chốt đăng ký đầu tiên';
    }

    /**
     * Kết thúc xử lý (Lost / Archived) và trả về thông báo.
     */
    protected function closeOpportunity(string $status, string $reason, string $key, array $params): string
    {
        $this->opportunity->close($status, $reason);
        $this->refreshStage();

        return __($key, $params + ['reason' => $reason]);
    }

    // ── Tạo báo giá ───────────────────────────────────────────────────

    /**
     * Khách đồng ý đăng ký: chuyển sang stage Chốt để lập báo giá.
     */
    public function createQuotation(): void
    {
        abort_unless($this->canCreateQuotation(), 403);

        $this->opportunity->moveToStage(Stage::CLOSED, 'Khách đồng ý đăng ký – lập báo giá.');
        $this->refreshStage();

        $this->closeForm();
        $this->notice = ['tone' => 'success', 'text' => __('opportunities.quote_stage_moved', ['stage' => $this->opportunity->stage->stage_name])];
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

        $this->opportunity->moveToStage($processed->stage_id);
        $this->refreshStage();

        return true;
    }

    /**
     * Sau khi chuyển stage: cập nhật quan hệ stage, tính lại hoạt động liên hệ và bộ đếm theo stage mới.
     */
    protected function refreshStage(): void
    {
        $this->opportunity->setRelation('stage', $this->stages->firstWhere('stage_id', $this->opportunity->stage_id));
        unset($this->nextActivity, $this->stageSince, $this->unreachableContacts, $this->stageContactDone, $this->rebookTypes);
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
     * Loại được đặt lịch hẹn:
     *   - Data chưa tương tác: mọi loại, sau khi đã liên hệ thành công ở stage này (kể cả khi quay lại stage);
     *   - stage sau: loại đã có lần khách không đến, chưa từng đến và chưa có lịch chờ (hẹn lại).
     */
    #[Computed]
    public function rebookTypes(): array
    {
        if (! $this->canCreateAppointment()) {
            return [];
        }

        if ($this->opportunity->stage_id === Stage::UNTOUCHED) {
            return $this->stageContactDone ? Appointment::TYPES : [];
        }

        $rows = $this->ownAppointments()->get(['appointment_type', 'status']);

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
     * Kiểm tra form lịch hẹn (newTypes, newTimes, newNotes) – dùng cho
     * nút "Tạo lịch hẹn".
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

        $this->opportunity->moveToStage(Stage::APPOINTED);
        $this->refreshStage();

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

        return Appointment::groupForDisplay(
            $this->opportunity->appointments()
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
            ->withSum('details as total', 'line_total')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Nút "Tạo báo giá": chỉ salesperson phụ trách, khi đã có lần liên hệ thành công ở stage hiện tại –
     * "Liên hệ ban đầu" ở Data chưa tương tác (khách đăng ký ngay) hoặc "Chốt đăng ký" ở Data đã xử lý.
     */
    public function canCreateQuotation(): bool
    {
        return $this->isOwner()
            && in_array($this->opportunity->stage_id, [Stage::UNTOUCHED, Stage::PROCESSED], true)
            && $this->stageContactDone;
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
