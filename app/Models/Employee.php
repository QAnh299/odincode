<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory, HasStringId;

    // Trạng thái nhân viên (cột status): Đang làm việc hoặc Đã nghỉ việc
    const STATUS_WORKING = 'Working';
    const STATUS_RESIGNED = 'Resigned';

    // Key trạng thái dùng cho bộ lọc / nhãn hiển thị (lang employees.state.*)
    const STATE_WORKING = 'working';   // status = 'Working'
    const STATE_RESIGNED = 'resigned'; // status khác 'Working'

    const STATES = [self::STATE_WORKING, self::STATE_RESIGNED];

    protected $table = 'Employees';

    protected $primaryKey = 'employee_id';

    protected string $idPrefix = 'EMP';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'full_name',
        'date_of_birth',
        'email',
        'phone',
        'job_title',
        'hire_date',
        'status',
        'role_id',
        'account_id',
        'team_id',
        'branch_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'hire_date'     => 'date',
    ];

    // ── Relationships ──────────────────────────────────────

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function account()
    {
        return $this->belongsTo(User::class, 'account_id', 'account_id');
    }

    public function team()
    {
        return $this->belongsTo(SalesTeam::class, 'team_id', 'team_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class, 'employee_id', 'employee_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'employee_id', 'employee_id');
    }

    public function careResults()
    {
        return $this->hasMany(CareResult::class, 'employee_id', 'employee_id');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'employee_id', 'employee_id');
    }

    public function invoicesCreated()
    {
        return $this->hasMany(Invoice::class, 'created_by_employee_id', 'employee_id');
    }

    public function invoicesAccountant()
    {
        return $this->hasMany(Invoice::class, 'accountant_id', 'employee_id');
    }

    // ── Helpers ────────────────────────────────────────────

    /**
     * Lấy role_name của nhân viên (shortcut).
     */
    public function getRoleNameAttribute(): ?string
    {
        return $this->role?->role_name;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role_name, $roles, true);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_WORKING;
    }

    public function scopeState(Builder $query, string $state): Builder
    {
        return match ($state) {
            self::STATE_WORKING  => $query->where('status', self::STATUS_WORKING),
            self::STATE_RESIGNED => $query->where('status', '<>', self::STATUS_WORKING),
            default              => $query,
        };
    }

    public function state(): string
    {
        return $this->isActive() ? self::STATE_WORKING : self::STATE_RESIGNED;
    }

    /**
     * Nhân viên mà $viewer được xem:
     *   - Giám đốc, Sale Admin: tất cả.
     *   - Sale Leader: nhân viên trong đội của mình (chưa thuộc đội nào thì chỉ thấy chính mình).
     *   - Vai trò khác: không có.
     */
    public function scopeVisibleTo(Builder $query, ?self $viewer): Builder
    {
        return match ($viewer?->role_name) {
            Role::DIRECTOR, Role::SALE_ADMIN => $query,
            Role::SALE_LEADER => $viewer->team_id
                ? $query->where('team_id', $viewer->team_id)
                : $query->whereKey($viewer->employee_id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Chữ cái đại diện (avatar): chữ đầu của tên, VD "Nguyễn Minh Anh" → "A".
     */
    public function initial(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->full_name)) ?: [];

        return mb_strtoupper(mb_substr(end($parts) ?: '?', 0, 1));
    }
}
