<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Opportunity extends Model
{
    use HasFactory, HasStringId;

    const STATUS_IN_PROGRESS = 'InProgress';
    const STATUS_WON = 'Won';
    const STATUS_LOST = 'Lost';
    const STATUS_ARCHIVED = 'Archived';

    const STATUSES = [self::STATUS_IN_PROGRESS, self::STATUS_WON, self::STATUS_LOST, self::STATUS_ARCHIVED];

    // Data đã xử lý: số ngày để khách quyết định, tính từ lúc chuyển sang stage này
    const DECISION_DAYS = 14;

    // Data chưa tương tác: được phân quá số ngày này mà chưa liên hệ lần nào → nhắc trên chuông thông báo
    const FIRST_CONTACT_DAYS = 1;

    // Không liên lạc được: lần liên hệ tiếp theo sau số ngày này (hệ thống nhắc khi tới hạn)
    const RECONTACT_DAYS = 1;

    // Data đã có lịch hẹn: quá số ngày này kể từ buổi khách không đến mà chưa hẹn lại → Archived
    const REBOOK_DAYS = 7;

    protected $table = 'Opportunities';

    protected $primaryKey = 'opportunity_id';

    protected string $idPrefix = 'OPP';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'opportunity_id',
        'conversion_date',
        'expected_value',
        'status',
        'lead_id',
        'stage_id',
        'employee_id',
    ];

    protected $casts = [
        'conversion_date' => 'datetime',
        'expected_value'  => 'decimal:2',
    ];

    public function displayValue(): ?string
    {
        return $this->expected_value === null
            ? null
            : number_format((float) $this->expected_value, 0, ',', '.').' ₫';
    }

    public function scopeVisibleTo(Builder $query, ?Employee $employee): Builder
    {
        return match ($employee?->role_name) {
            Role::DIRECTOR, Role::SALE_ADMIN => $query,
            // Kế toán: khách thuộc cơ sở mình phụ trách (Employee::coveredBranchIds)
            Role::ACCOUNTANT  => $query->whereHas('lead', fn ($q) => $q->whereIn('branch_id', $employee->coveredBranchIds())),
            Role::SALE_LEADER => $employee->team_id
                ? $query->whereHas('employee', fn ($q) => $q->where('team_id', $employee->team_id))
                : $query->whereRaw('1 = 0'),
            Role::SALESPERSON => $query->where('employee_id', $employee->employee_id),
            default           => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Cơ hội đã phân cho salesperson (Lead đã 'Converted'). Cơ hội trưởng nhóm đang giữ chờ phân
     * (Lead còn 'New') chưa xuất hiện ở Quản lý cơ hội.
     * Khách cũ được đưa về hàng chờ (Lead 'New' lại): các cơ hội đã kết thúc trước đó vẫn hiển thị.
     */
    public function scopeAssigned(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->where($q->qualifyColumn('status'), '!=', self::STATUS_IN_PROGRESS)
            ->orWhereHas('lead', fn ($l) => $l->where('status', Lead::STATUS_CONVERTED)));
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id', 'lead_id');
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class, 'stage_id', 'stage_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function transfers()
    {
        return $this->hasMany(OpportunityTransfer::class, 'opportunity_id', 'opportunity_id');
    }

    public function student()
    {
        return $this->hasOne(Student::class, 'opportunity_id', 'opportunity_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'opportunity_id', 'opportunity_id');
    }

    public function careResults()
    {
        return $this->hasMany(CareResult::class, 'opportunity_id', 'opportunity_id');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'opportunity_id', 'opportunity_id');
    }

    public function stagesHistory()
    {
        return $this->hasMany(StageHistory::class, 'opportunity_id', 'opportunity_id');
    }

    public function moveToStage(string $stageId, ?string $notes = null): void
    {
        if ($this->stage_id === $stageId) {
            return;
        }

        DB::transaction(function () use ($stageId, $notes) {
            StageHistory::create([
                'changed_at'     => now(),
                'notes'          => $notes,
                'opportunity_id' => $this->opportunity_id,
                'from_stage_id'  => $this->stage_id,
                'to_stage_id'    => $stageId,
            ]);

            $this->update(['stage_id' => $stageId]);
        });
    }

    public function stageEnteredAt(): Carbon
    {
        $changed = $this->stagesHistory()->where('to_stage_id', $this->stage_id)->max('changed_at');

        return $changed ? Carbon::parse($changed)->startOfMinute() : $this->conversion_date;
    }

    public function scopeWithLastCare(Builder $query): Builder
    {
        return $query->addSelect(['last_care_result' => CareResult::query()
            ->select('result')
            ->whereColumn('CareResults.opportunity_id', 'Opportunities.opportunity_id')
            ->orderByDesc('performed_at')
            ->orderByDesc('result_id')
            ->limit(1)]);
    }

    public function recontactAt(): ?Carbon
    {
        $last = $this->careResults()
            ->where('performed_at', '>=', $this->stageEnteredAt())
            ->orderByDesc('performed_at')
            ->orderByDesc('result_id')
            ->first(['result', 'performed_at']);

        return $last?->result === CareResult::RESULT_UNREACHABLE
            ? Carbon::parse($last->performed_at)->addDays(self::RECONTACT_DAYS)
            : null;
    }

    public function recontactDue(): bool
    {
        if ($this->status !== self::STATUS_IN_PROGRESS
            || ! in_array($this->stage_id, [Stage::UNTOUCHED, Stage::APPOINTED, Stage::PROCESSED], true)
            || (array_key_exists('last_care_result', $this->getAttributes()) && $this->last_care_result !== CareResult::RESULT_UNREACHABLE)) {
            return false;
        }

        $at = $this->recontactAt();

        return $at && $at->lte(now())
            && ($this->stage_id !== Stage::APPOINTED
                || ! $this->appointments()->where('status', Appointment::STATUS_SCHEDULED)->exists());
    }

    /**
     * Khách đồng ý đăng ký, được lập báo giá đầu tiên: đang xử lý ở Data chưa tương tác (đã "Liên hệ ban đầu"
     * thành công) hoặc Data đã xử lý (đã "Chốt đăng ký" thành công, chưa quá hạn quyết định) - tính từ lúc vào stage.
     * Lưu báo giá (kể cả Nháp) mới chuyển cơ hội sang stage Chốt.
     */
    public function agreedToRegister(): bool
    {
        $activity = [Stage::UNTOUCHED => CareActivity::INITIAL, Stage::PROCESSED => CareActivity::DECISION][$this->stage_id] ?? null;

        return $activity !== null
            && $this->status === self::STATUS_IN_PROGRESS
            && ! $this->decisionDeadline()?->isPast()
            && $this->careResults()
                ->where('activity_id', $activity)
                ->where('result', CareResult::RESULT_SUCCESS)
                ->where('performed_at', '>=', $this->stageEnteredAt())
                ->exists();
    }

    public function firstContactDue(): bool
    {
        return $this->stage_id === Stage::UNTOUCHED
            && $this->status === self::STATUS_IN_PROGRESS
            && $this->conversion_date->lte(now()->subDays(self::FIRST_CONTACT_DAYS))
            && ! (isset($this->care_results_count) ? $this->care_results_count > 0 : $this->careResults()->exists());
    }

    public function needsQuotation(): bool
    {
        return $this->stage_id === Stage::CLOSED
            && $this->status === self::STATUS_IN_PROGRESS
            && ($this->quotations_count ?? $this->quotations()->count()) === 0;
    }

    public function decisionDeadline(): ?Carbon
    {
        if ($this->stage_id !== Stage::PROCESSED) {
            return null;
        }

        return $this->stageEnteredAt()->copy()->addDays(self::DECISION_DAYS);
    }

    public function closeIfDecisionOverdue(?Carbon $at = null): bool
    {
        $deadline = $this->decisionDeadline();

        if (! $deadline || $this->status !== self::STATUS_IN_PROGRESS || ($at ?? now())->lte($deadline)) {
            return false;
        }

        $days = self::DECISION_DAYS;
        $last = $this->decisionContacts()->orderByDesc('performed_at')->orderByDesc('result_id')->value('result');

        $this->close(self::STATUS_ARCHIVED, match ($last) {
            null                            => "Không liên hệ chốt đăng ký trong {$days} ngày kể từ khi chuyển sang Data đã xử lý",
            CareResult::RESULT_UNREACHABLE  => "Không liên lạc được trong {$days} ngày kể từ khi chuyển sang Data đã xử lý",
            default                         => "Khách không ra quyết định sau {$days} ngày kể từ khi chuyển sang Data đã xử lý",
        });

        return true;
    }

    public function closeIfRebookOverdue(?Carbon $at = null): bool
    {
        if ($this->stage_id !== Stage::APPOINTED || $this->status !== self::STATUS_IN_PROGRESS
            || $this->appointments()->where('status', Appointment::STATUS_SCHEDULED)->exists()) {
            return false;
        }

        $noShow = $this->appointments()->where('status', Appointment::STATUS_FAILED)->max('scheduled_time');

        if (! $noShow || ($at ?? now())->lte(Carbon::parse($noShow)->addDays(self::REBOOK_DAYS))) {
            return false;
        }

        $this->close(self::STATUS_ARCHIVED, 'Không hẹn lại được trong '.self::REBOOK_DAYS.' ngày kể từ buổi khách không đến');

        return true;
    }

    protected function decisionContacts()
    {
        return $this->careResults()
            ->where('activity_id', CareActivity::DECISION)
            ->where('performed_at', '>=', $this->stageEnteredAt());
    }


    public function close(string $status, string $reason): void
    {
        DB::transaction(function () use ($status, $reason) {
            $this->appointments()->where('status', Appointment::STATUS_SCHEDULED)->delete();
            $this->moveToStage(Stage::ARCHIVED, $reason);
            $this->update(['status' => $status]);
        });
    }
}
