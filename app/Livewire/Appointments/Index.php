<?php

namespace App\Livewire\Appointments;

use App\Models\Appointment;
use App\Models\Stage;
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Danh sách lịch hẹn của Salesperson (menu Quản lý Opportunity → Lịch hẹn): các lịch hẹn mình đang phụ trách.
 *   - Thẻ thống kê Tổng / Đã lên lịch / Khách đã đến / Khách không đến (bấm để lọc theo trạng thái).
 *   - Tìm theo tên khách, SĐT, mã cơ hội / mã lịch hẹn; lọc trạng thái + khoảng thời gian.
 *   - Mỗi dòng là một loại (Test / Tư vấn) vì hai loại có thể khác giờ. Bấm dòng → chi tiết cơ hội, tab Lịch hẹn,
 *     làm nổi bật đúng lịch hẹn đó (?apt=APT006-Test).
 *   - Cột thao tác mở popup: Đổi lịch / Hủy lịch (chưa tới giờ hẹn, chưa tới stage Chốt), Cập nhật kết quả
 *     (từ giờ hẹn trở đi: Khách đã đến - bắt buộc ghi kết quả / Khách không đến). Nghiệp vụ (chuyển stage,
 *     trùng lịch…) dùng chung với chi tiết cơ hội qua AppointmentService.
 * Địa điểm là cơ sở của khách (Leads.branch_id).
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public const PER_PAGE = 10;

    // Lọc trạng thái: Overdue = đã qua giờ hẹn mà chưa cập nhật kết quả
    public const STATUSES = ['Scheduled', 'Overdue', 'Success', 'Failed', 'Cancelled'];

    // Lọc thời gian
    public const PERIODS = ['today', 'tomorrow', 'week', 'month', 'past'];

    // Popup thao tác
    public const DIALOGS = ['result', 'reschedule', 'cancel'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $period = '';

    // Thông báo sau thao tác: ['tone' => success|danger, 'text' => ...]
    public ?array $notice = null;

    // Popup đang mở (result | reschedule | cancel) và dòng lịch hẹn ("APT006-Test")
    public ?string $dialog = null;

    public string $targetKey = '';

    // Popup cập nhật kết quả
    public string $outcome = Appointment::STATUS_SUCCESS;

    public string $resultNote = '';

    // Popup đổi lịch
    public string $newTime = '';

    // Popup hủy lịch
    public string $cancelReason = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'period'], true)) {
            $this->resetPage();
        }
    }

    public function setStatus(string $status): void
    {
        $this->status = in_array($status, self::STATUSES, true) ? $status : '';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'period');
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->status !== '' || $this->period !== '';
    }

    public function dismissNotice(): void
    {
        $this->notice = null;
    }

    // ── Popup thao tác ────────────────────────────────────────────────

    public function openDialog(string $dialog, string $appointmentId, string $type): void
    {
        $this->closeDialog();
        $this->notice = null;

        if (! in_array($dialog, self::DIALOGS, true)) {
            return;
        }

        $this->targetKey = $appointmentId.'-'.$type;
        $row = $this->target;
        $allowed = $row && match ($dialog) {
            'result' => $row->can_mark,
            default  => $row->can_change,
        };

        if (! $allowed) {
            $this->closeDialog();
            $this->notice = ['tone' => 'danger', 'text' => __($dialog === 'result' ? 'opportunities.apt_cannot_mark' : 'appointments.cannot_change')];

            return;
        }

        $this->dialog = $dialog;
        $this->newTime = $dialog === 'reschedule' ? $row->scheduled_time->format('Y-m-d\TH:i') : '';
    }

    public function closeDialog(): void
    {
        $this->reset('dialog', 'targetKey', 'outcome', 'resultNote', 'newTime', 'cancelReason');
        $this->resetValidation();
        unset($this->target);
    }

    /**
     * Dòng lịch hẹn đang mở popup - chỉ lịch hẹn của mình, kèm cờ can_mark / can_change.
     */
    #[Computed]
    public function target(): ?Appointment
    {
        [$id, $type] = array_pad(explode('-', $this->targetKey, 2), 2, '');

        if ($id === '' || $type === '') {
            return null;
        }

        $row = $this->own()
            ->where('appointment_id', $id)
            ->where('appointment_type', $type)
            ->with(self::RELATIONS)
            ->first();

        return $row ? $this->withFlags($row) : null;
    }

    /**
     * Khách đã đến (bắt buộc ghi kết quả) / Khách không đến.
     */
    public function saveResult(): void
    {
        $this->validate([
            'outcome'    => ['required', 'in:'.Appointment::STATUS_SUCCESS.','.Appointment::STATUS_FAILED],
            'resultNote' => [$this->outcome === Appointment::STATUS_SUCCESS ? 'required' : 'nullable', 'string', 'max:1000'],
        ], [
            'resultNote.required' => __('opportunities.apt_result_required'),
        ], [
            'resultNote' => __('opportunities.apt_result'),
        ]);

        $row = $this->target;
        if (! $row?->can_mark) {
            $this->failed(__('opportunities.apt_cannot_mark'));

            return;
        }

        $this->done($this->service()->mark(
            $row->opportunity,
            $row->appointment_id,
            $row->appointment_type,
            $this->outcome,
            $this->outcome === Appointment::STATUS_SUCCESS ? trim($this->resultNote) : null,
        ));
    }

    /**
     * Đổi giờ hẹn: sau thời điểm hiện tại, không trùng lịch đang chờ khác của mình.
     */
    public function saveReschedule(): void
    {
        $this->validate(
            ['newTime' => ['required', 'date', 'after:now']],
            ['newTime.after' => __('opportunities.apt_time_future')],
            ['newTime' => __('opportunities.new_time')],
        );

        $row = $this->target;
        if (! $row?->can_change) {
            $this->failed(__('opportunities.apt_cannot_reschedule'));

            return;
        }

        $time = Carbon::parse($this->newTime);
        if ($clash = $this->service()->clashFor($this->employeeId(), $time, $row->appointment_id)) {
            $this->addError('newTime', $this->service()->clashMessage($clash));

            return;
        }

        $updated = $this->service()->reschedule($row->opportunity, [$this->targetKey => $time]);

        $this->done($updated
            ? ['tone' => 'success', 'text' => __('appointments.rescheduled', ['time' => $time->format('H:i d/m/Y')])]
            : ['tone' => 'danger', 'text' => __('opportunities.apt_cannot_reschedule')]);
    }

    /**
     * Hủy lịch hẹn trước giờ hẹn, bắt buộc lý do.
     */
    public function saveCancel(): void
    {
        $this->validate(
            ['cancelReason' => ['required', 'string', 'max:500']],
            ['cancelReason.required' => __('opportunities.apt_cancel_required')],
            ['cancelReason' => __('opportunities.apt_cancel_reason')],
        );

        $row = $this->target;
        if (! $row?->can_change) {
            $this->failed(__('opportunities.apt_cannot_cancel'));

            return;
        }

        $this->done($this->service()->cancel($row->opportunity, $row->appointment_id, $row->appointment_type, trim($this->cancelReason)));
    }

    protected function done(array $notice): void
    {
        $this->closeDialog();
        $this->notice = $notice;
        unset($this->counts);

        // Chuông bỏ ngay thông báo lịch hẹn đã xử lý
        if ($notice['tone'] === 'success') {
            $this->dispatch('notifications-refresh');
        }
    }

    protected function failed(string $text): void
    {
        $this->done(['tone' => 'danger', 'text' => $text]);
    }

    // ── Dữ liệu ───────────────────────────────────────────────────────

    public const RELATIONS = [
        'opportunity:opportunity_id,lead_id,stage_id,employee_id',
        'opportunity.lead:lead_id,full_name,phone,branch_id',
        'opportunity.lead.branch:branch_id,branch_name',
        'opportunity.stage:stage_id,stage_name,sort_order',
    ];

    protected function service(): AppointmentService
    {
        return app(AppointmentService::class);
    }

    protected function employeeId(): ?string
    {
        return Auth::user()->employee?->employee_id;
    }

    /**
     * Lịch hẹn của salesperson đang đăng nhập.
     */
    protected function own(): Builder
    {
        return Appointment::query()->where('employee_id', $this->employeeId());
    }

    /**
     * Thứ tự stage Chốt - từ Chốt trở đi không đổi / hủy lịch hẹn nữa.
     */
    #[Computed]
    public function closedOrder(): int
    {
        return (int) Stage::query()->whereKey(Stage::CLOSED)->value('sort_order');
    }

    /**
     * Gắn cờ thao tác cho một dòng (chỉ người đang phụ trách cơ hội):
     *   can_mark   - còn chờ, đã tới giờ hẹn → cập nhật kết quả;
     *   can_change - còn chờ, chưa tới giờ hẹn, cơ hội chưa tới stage Chốt → đổi / hủy lịch.
     */
    protected function withFlags(Appointment $row): Appointment
    {
        $opp = $row->opportunity;
        $pending = $row->status === Appointment::STATUS_SCHEDULED && $opp?->employee_id === $this->employeeId();
        $past = $row->scheduled_time->lte(now());

        $row->display_status = $row->status === Appointment::STATUS_SCHEDULED && $past ? 'Overdue' : $row->status;
        $row->can_mark = $pending && $past;
        $row->can_change = $pending && ! $past && ($opp->stage?->sort_order ?? 0) < $this->closedOrder;

        return $row;
    }

    protected function applyStatus(Builder $query): Builder
    {
        return match ($this->status) {
            'Overdue' => $query->where('status', Appointment::STATUS_SCHEDULED)->where('scheduled_time', '<=', now()),
            'Scheduled', 'Success', 'Failed', 'Cancelled' => $query->where('status', $this->status),
            default => $query,
        };
    }

    protected function applyPeriod(Builder $query): Builder
    {
        $now = CarbonImmutable::now();
        $today = $now->startOfDay();

        return match ($this->period) {
            'today'    => $query->whereBetween('scheduled_time', [$today, $today->endOfDay()]),
            'tomorrow' => $query->whereBetween('scheduled_time', [$today->addDay(), $today->addDay()->endOfDay()]),
            'week'     => $query->whereBetween('scheduled_time', [$today, $today->addDays(6)->endOfDay()]),
            'month'    => $query->whereBetween('scheduled_time', [$now->startOfMonth(), $now->endOfMonth()]),
            'past'     => $query->where('scheduled_time', '<=', $now),
            default    => $query,
        };
    }

    /**
     * Số lịch hẹn cho các thẻ thống kê (toàn bộ lịch hẹn của mình).
     */
    #[Computed]
    public function counts(): array
    {
        $byStatus = $this->own()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total'                         => (int) $byStatus->sum(),
            Appointment::STATUS_SCHEDULED   => (int) ($byStatus[Appointment::STATUS_SCHEDULED] ?? 0),
            Appointment::STATUS_SUCCESS     => (int) ($byStatus[Appointment::STATUS_SUCCESS] ?? 0),
            Appointment::STATUS_FAILED      => (int) ($byStatus[Appointment::STATUS_FAILED] ?? 0),
        ];
    }

    public function render()
    {
        $search = trim($this->search);
        $scheduled = "'".Appointment::STATUS_SCHEDULED."'";

        $appointments = $this->applyPeriod($this->applyStatus($this->own()))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($q) => $q->where('appointment_id', 'like', $like)
                    ->orWhere('opportunity_id', 'like', $like)
                    ->orWhereHas('opportunity.lead', fn ($l) => $l->where('full_name', 'like', $like)->orWhere('phone', 'like', $like)));
            })
            ->with(self::RELATIONS)
            // Lịch còn chờ lên đầu (gần nhất trước), sau đó lịch đã có kết quả / đã hủy (mới nhất trước)
            ->orderByRaw("CASE WHEN status = $scheduled THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status = $scheduled THEN scheduled_time END ASC")
            ->orderByDesc('scheduled_time')
            ->orderBy('appointment_id')
            // Cùng giờ: Test trước, Tư vấn sau
            ->orderByRaw('FIELD(appointment_type, '.implode(', ', array_map(fn ($t) => "'$t'", Appointment::TYPES)).')')
            ->paginate(self::PER_PAGE);

        $appointments->getCollection()->each(fn (Appointment $row) => $this->withFlags($row));

        return view('livewire.appointments.index', [
            'appointments' => $appointments,
        ])->title(__('appointments.title'));
    }
}
