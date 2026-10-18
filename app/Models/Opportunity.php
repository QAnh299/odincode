<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Opportunity extends Model
{
    use HasFactory, HasStringId;

    const STATUS_IN_PROGRESS = 'InProgress';
    const STATUS_WON = 'Won';
    const STATUS_LOST = 'Lost';
    const STATUS_ARCHIVED = 'Archived';

    const STATUSES = [self::STATUS_IN_PROGRESS, self::STATUS_WON, self::STATUS_LOST, self::STATUS_ARCHIVED];

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

    /**
     * Cơ hội mà nhân viên được phép xem:
     * Giám đốc / Sale Admin / Kế toán xem tất cả, Sale Leader xem của đội mình, Salesperson xem của chính mình.
     */
    public function scopeVisibleTo(Builder $query, ?Employee $employee): Builder
    {
        return match ($employee?->role_name) {
            Role::DIRECTOR, Role::SALE_ADMIN, Role::ACCOUNTANT => $query,
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
     */
    public function scopeAssigned(Builder $query): Builder
    {
        return $query->whereHas('lead', fn ($q) => $q->where('status', Lead::STATUS_CONVERTED));
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

    /**
     * Chuyển sang stage khác và ghi một dòng StagesHistory trong cùng transaction.
     * Không làm gì nếu đã ở stage đó.
     */
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

    /**
     * Kết thúc xử lý: xoá lịch hẹn chưa diễn ra, chuyển sang stage Lưu trữ (ghi lý do vào StagesHistory)
     * và đổi trạng thái thành Lost (khách từ chối) hoặc Archived (không liên lạc được).
     */
    public function close(string $status, string $reason): void
    {
        DB::transaction(function () use ($status, $reason) {
            $this->appointments()->where('status', Appointment::STATUS_SCHEDULED)->delete();
            $this->moveToStage(Stage::ARCHIVED, $reason);
            $this->update(['status' => $status]);
        });
    }

    /**
     * Quay về stage "Data chưa tương tác" (khách từ chối đăng ký ở stage Data đã xử lý):
     * xoá lịch hẹn chưa diễn ra và ghi lý do vào StagesHistory.
     */
    public function returnToUntouched(string $reason): void
    {
        DB::transaction(function () use ($reason) {
            $this->appointments()->where('status', Appointment::STATUS_SCHEDULED)->delete();
            $this->moveToStage(Stage::UNTOUCHED, $reason);
        });
    }

    /**
     * Số lần Opportunity đã quay về stage "Data chưa tương tác".
     */
    public function returnsToUntouched(): int
    {
        return $this->stagesHistory()->where('to_stage_id', Stage::UNTOUCHED)->count();
    }
}
