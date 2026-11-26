<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Lead extends Model
{
    use HasFactory;
    use HasStringId;

    public const STATS_VERSION_KEY = 'leads.stats.version';

    public static function touchStats(): void
    {
        Cache::forever(self::STATS_VERSION_KEY, Str::random(8));
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::touchStats());
        static::deleted(fn () => self::touchStats());
    }

    public const STATUS_NEW = 'New';
    public const STATUS_CONVERTED = 'Converted';
    public const STATUSES = [self::STATUS_NEW, self::STATUS_CONVERTED];

    public const CONTACT_METHODS = ['Zalo', 'Phone', 'Facebook'];

    public const SOURCES = ['Facebook', 'Workshop', 'Website ODIN', 'Zalo', 'Instagram', 'TikTok'];

    public const METHODS_PHONE_REQUIRED = ['Phone', 'Zalo'];
    public const METHODS_LINK_REQUIRED = ['Facebook'];

    protected $table = 'Leads';

    protected $primaryKey = 'lead_id';

    protected string $idPrefix = 'LEAD';

    protected int $idLength = 3;

    public const UPDATED_AT = null;

    protected $fillable = [
        'lead_id',
        'full_name',
        'phone',
        'email',
        'source_name',
        'source_url',
        'contact_method',
        'status',
        'branch_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function scopeVisibleTo(Builder $query, ?Employee $employee): Builder
    {
        if (in_array($employee?->role_name, [Role::DIRECTOR, Role::SALE_ADMIN], true)) {
            return $query;
        }

        $team = $employee?->role_name === Role::SALE_LEADER ? ($employee->ledTeam ?? $employee->team) : null;

        return $team
            ? $query->where('branch_id', $employee->branch_id)
                ->whereHas('opportunities', fn ($q) => $q->where(fn ($q) => $q
                    ->where('employee_id', $team->team_leader_id)
                    ->orWhereHas('employee', fn ($e) => $e->where('team_id', $team->team_id))))
            : $query->whereRaw('1 = 0');
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NEW)
            ->whereDoesntHave('opportunities', fn ($q) => $q->where('status', Opportunity::STATUS_IN_PROGRESS));
    }

    public function scopeReturning(Builder $query): Builder
    {
        return $query->unassigned()
            ->whereHas('opportunities', fn ($q) => $q->where('status', '!=', Opportunity::STATUS_IN_PROGRESS));
    }

    public function isReturning(): bool
    {
        return $this->opportunities()->where('status', '!=', Opportunity::STATUS_IN_PROGRESS)->exists();
    }

    public function scopePendingSalesperson(Builder $query): Builder
    {
        return $query->whereHas('opportunities', fn ($q) => $q
            ->where('stage_id', Stage::UNTOUCHED)
            ->where('status', Opportunity::STATUS_IN_PROGRESS)
            ->whereDoesntHave('careResults')
            ->whereHas('employee.ledTeam'));
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function sourceUrlIsLink(): bool
    {
        return (bool) preg_match('#^https?://#i', (string) $this->source_url);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class, 'lead_id', 'lead_id');
    }

    public function latestOpportunity()
    {
        return $this->hasOne(Opportunity::class, 'lead_id', 'lead_id')->latestOfMany('conversion_date');
    }

    public function student()
    {
        return $this->hasOne(Student::class, 'lead_id', 'lead_id');
    }
}
