<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Tài khoản đăng nhập – bảng `Accounts` trong odin.sql.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasStringId, Notifiable;

    protected $table = 'Accounts';

    protected $primaryKey = 'account_id';

    protected string $idPrefix = 'ACC';

    protected int $idLength = 3;

    // Bảng Accounts chỉ có created_at
    const UPDATED_AT = null;

    // Bảng Accounts không có cột remember_token → tắt "ghi nhớ đăng nhập"
    protected $rememberTokenName = '';

    protected $fillable = ['account_id', 'username', 'password', 'status'];

    protected $hidden = ['password'];

    protected $casts = [
        'password'   => 'hashed',
        'created_at' => 'datetime',
    ];

    /**
     * Lấy thông tin nhân viên gắn với tài khoản này.
     */
    public function employee()
    {
        return $this->hasOne(Employee::class, 'account_id', 'account_id');
    }

    /**
     * Lấy role thông qua employee.
     */
    public function getRoleNameAttribute(): ?string
    {
        return $this->employee?->role?->role_name;
    }

    /**
     * Route name trang home theo vai trò, null nếu chưa được phân quyền.
     */
    public function homeRoute(): ?string
    {
        return Role::HOME_ROUTES[$this->role_name] ?? null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Được phép đăng nhập: tài khoản 'active' và nhân viên còn 'Working'.
     */
    public function canSignIn(): bool
    {
        return $this->isActive() && (bool) $this->employee?->isActive();
    }
}
