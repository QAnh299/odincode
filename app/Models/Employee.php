<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory, HasStringId;

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
        return $this->status === 'Working';
    }
}
