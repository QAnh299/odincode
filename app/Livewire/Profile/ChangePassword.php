<?php

namespace App\Livewire\Profile;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Đổi mật khẩu cho tài khoản đang đăng nhập – dùng chung cho mọi vai trò.
 */
#[Layout('layouts.app')]
class ChangePassword extends Component
{
    // Số lần nhập sai mật khẩu hiện tại tối đa trong 1 phút
    const MAX_ATTEMPTS = 5;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    // Hiện thông báo thành công sau khi đổi
    public bool $changed = false;

    protected function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'password'         => [
                'required', 'string', 'max:64', 'confirmed', 'different:current_password',
                Password::min(8)->letters()->numbers(),
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'current_password.required' => __('change_password.current_required'),
            'password.required'         => __('change_password.new_required'),
            'password.confirmed'        => __('change_password.mismatch'),
            'password.different'        => __('change_password.same_as_current'),
            'password.max'              => __('change_password.too_long', ['max' => 64]),
            'password.min'              => __('change_password.too_short', ['min' => 8]),
            'password.letters'          => __('change_password.need_letter'),
            'password.numbers'          => __('change_password.need_number'),
        ];
    }

    public function save(): void
    {
        $this->changed = false;
        $this->validate();

        $user = Auth::user();
        $limiter = $this->limiter();
        $key = 'change-password:'.$user->account_id;

        if ($limiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $this->addError('current_password', __('change_password.throttle', ['seconds' => $limiter->availableIn($key)]));
            return;
        }

        if (! Hash::check($this->current_password, $user->password)) {
            $limiter->hit($key, 60);
            $this->addError('current_password', __('change_password.current_wrong'));
            return;
        }

        $limiter->clear($key);

        // Cột password có cast 'hashed' nên gán mật khẩu thô, model tự băm
        $user->update(['password' => $this->password]);

        // Đăng xuất các thiết bị dùng API (token Sanctum) sau khi đổi mật khẩu.
        // Bảng personal_access_tokens tạo bằng migration, có thể chưa có nếu chỉ import odin.sql.
        if (Schema::hasTable('personal_access_tokens')) {
            $user->tokens()->delete();
        }

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->resetErrorBag();
        $this->changed = true;
    }

    /**
     * Đếm số lần nhập sai bằng cache file – không phụ thuộc bảng `cache` trong DB.
     */
    private function limiter(): RateLimiter
    {
        return new RateLimiter(Cache::store('file'));
    }

    public function updated(string $field): void
    {
        $this->changed = false;
        $this->resetErrorBag($field === 'password_confirmation' ? 'password' : $field);
    }

    public function render()
    {
        return view('livewire.profile.change-password')->title(__('change_password.title'));
    }
}
