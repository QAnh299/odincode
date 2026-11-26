<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;
    use HasStringId;

    public const STATUS_WORKING = 'Working';
    public const STATUS_RESIGNED = 'Resigned';

    public const STATE_WORKING = 'working';
    public const STATE_RESIGNED = 'resigned';

    public const STATES = [self::STATE_WORKING, self::STATE_RESIGNED];

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

    public function ledTeam()
    {
        return $this->hasOne(SalesTeam::class, 'team_leader_id', 'employee_id');
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

    public function getRoleNameAttribute(): ?string
    {
        return $this->role?->role_name;
    }

    public function coveredBranchIds(): array
    {
        if ($this->role_name !== Role::ACCOUNTANT) {
            return [];
        }

        static $cache = [];

        return $cache[$this->employee_id] ??= $this->branch_id !== Branch::HEAD_OFFICE
            ? [$this->branch_id]
            : Branch::query()
                ->where('branch_id', $this->branch_id)
                ->orWhereNotIn('branch_id', self::query()
                    ->where('status', self::STATUS_WORKING)
                    ->whereHas('role', fn ($q) => $q->where('role_name', Role::ACCOUNTANT))
                    ->select('branch_id'))
                ->pluck('branch_id')->all();
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

    public static function visibleRolesFor(?string $viewerRole): ?array
    {
        return match ($viewerRole) {
            Role::DIRECTOR    => null,
            Role::SALE_ADMIN  => [Role::SALE_LEADER, Role::SALESPERSON],
            Role::SALE_LEADER => [Role::SALESPERSON],
            default           => [],
        };
    }

    public function scopeVisibleTo(Builder $query, ?self $viewer): Builder
    {
        $roles = self::visibleRolesFor($viewer?->role_name);

        if ($roles === null) {
            return $query;
        }

        if ($roles === [] || ($viewer->hasRole(Role::SALE_LEADER) && ! $viewer->team_id)) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereHas('role', fn ($q) => $q->whereIn('role_name', $roles))
            ->when($viewer->hasRole(Role::SALE_LEADER), fn ($q) => $q->where('team_id', $viewer->team_id));
    }

    public function initial(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->full_name)) ?: [];

        return mb_strtoupper(mb_substr(end($parts) ?: '?', 0, 1));
    }
}
