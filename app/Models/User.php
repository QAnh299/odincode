<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasStringId;
    use Notifiable;

    protected $table = 'Accounts';

    protected $primaryKey = 'account_id';

    protected string $idPrefix = 'ACC';

    protected int $idLength = 3;

    public const UPDATED_AT = null;

    protected $rememberTokenName = '';

    protected $fillable = ['account_id', 'username', 'password', 'status'];

    protected $hidden = ['password'];

    protected $casts = [
        'password'   => 'hashed',
        'created_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->hasOne(Employee::class, 'account_id', 'account_id');
    }

    public function getRoleNameAttribute(): ?string
    {
        return $this->employee?->role?->role_name;
    }

    public function homeRoute(): ?string
    {
        $role = $this->role_name;
        $items = config('sidebar.items');

        foreach (config("sidebar.roles.$role", []) as $k => $v) {
            foreach (is_int($k) ? [$v] : $v as $key) {
                $routeName = "$role.{$items[$key]['route']}";

                if (Route::has($routeName)) {
                    return $routeName;
                }
            }
        }

        return null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canSignIn(): bool
    {
        return $this->isActive() && (bool) $this->employee?->isActive();
    }

    public function mustChangePassword(): bool
    {
        return (bool) Cache::store('file')->get($this->mustChangePasswordKey());
    }

    public function setMustChangePassword(bool $value): void
    {
        $value
            ? Cache::store('file')->forever($this->mustChangePasswordKey(), true)
            : Cache::store('file')->forget($this->mustChangePasswordKey());
    }

    private function mustChangePasswordKey(): string
    {
        return 'must-change-password:'.$this->account_id;
    }
}
