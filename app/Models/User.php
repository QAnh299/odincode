<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\HasApiTokens;

/**
 * Tài khoản đăng nhập - bảng `Accounts` trong odin.sql.
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
    /**
     * Trang đầu tiên sau khi đăng nhập: mục đầu tiên trong menu dọc của vai trò
     * (config/sidebar.php); mục có menu con thì lấy menu con đầu tiên.
     * Bỏ qua mục chưa có route.
     */
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

    /**
     * Được phép đăng nhập: tài khoản 'active' và nhân viên còn 'Working'.
     */
    public function canSignIn(): bool
    {
        return $this->isActive() && (bool) $this->employee?->isActive();
    }

    /**
     * Cờ "bắt buộc đổi mật khẩu" sau khi được cấp mật khẩu tạm (quên mật khẩu).
     * Bảng Accounts không có cột cho cờ này nên lưu trong cache file, không hết hạn.
     */
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
