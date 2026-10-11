<?php

namespace App\Livewire\Auth;

use App\Mail\TemporaryPasswordMail;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.auth')]
class Login extends Component
{
    // Số yêu cầu quên mật khẩu tối đa từ một IP trong 10 phút
    const FORGOT_MAX_PER_IP = 5;

    // Thời gian chờ (giây) giữa hai lần cấp mật khẩu tạm cho cùng một tài khoản
    const FORGOT_ACCOUNT_COOLDOWN = 120;

    // 'login' = form đăng nhập, 'forgot' = form quên mật khẩu
    public string $mode = 'login';

    public string $username = '';
    public string $password = '';

    // Tên đăng nhập hoặc email nhập ở form quên mật khẩu
    public string $identifier = '';

    // Đã xử lý yêu cầu quên mật khẩu → hiện thông báo kiểm tra email
    public bool $resetSent = false;

    protected function rules(): array
    {
        return [
            'username' => 'required|string|max:100',
            'password' => 'required|string|min:6',
        ];
    }

    protected function messages(): array
    {
        return [
            'username.required'   => __('auth.username_required'),
            'password.required'   => __('auth.password_required'),
            'password.min'        => __('auth.password_min', ['min' => 6]),
            'identifier.required' => __('forgot_password.identifier_required'),
            'identifier.max'      => __('forgot_password.identifier_max', ['max' => 254]),
        ];
    }

    public function login(): void
    {
        $this->resetErrorBag('auth');
        $this->validate();

        $user = User::with('employee.role')->where('username', $this->username)->first();

        if (! $user || ! Hash::check($this->password, $user->password)) {
            $this->addError('auth', __('auth.failed'));
            return;
        }

        if (! $user->canSignIn()) {
            $this->addError('auth', __('auth.account_disabled'));
            return;
        }

        if (! $route = $user->homeRoute()) {
            $this->addError('auth', __('auth.no_role'));
            return;
        }

        // Bảng Accounts không có cột remember_token → không hỗ trợ "ghi nhớ đăng nhập"
        Auth::login($user);
        session()->regenerate();

        // Đăng nhập bằng mật khẩu tạm → bắt buộc đổi mật khẩu trước khi dùng hệ thống
        $this->redirectRoute($user->mustChangePassword() ? 'profile.password' : $route);
    }

    /**
     * Chuyển giữa form đăng nhập và form quên mật khẩu.
     */
    public function switchMode(string $mode): void
    {
        $this->mode = $mode === 'forgot' ? 'forgot' : 'login';
        $this->resetSent = false;
        $this->reset('password', 'identifier');
        $this->resetErrorBag();
    }

    /**
     * Quên mật khẩu: tạo mật khẩu ngẫu nhiên, gửi qua email của nhân viên rồi ghi đè mật khẩu cũ.
     * Luôn báo cùng một thông điệp để không lộ tài khoản nào tồn tại.
     */
    public function sendTemporaryPassword(): void
    {
        $this->resetErrorBag('auth');
        $this->validate(['identifier' => 'required|string|max:254']);

        $limiter = $this->limiter();
        $ipKey = 'forgot-password:ip:'.request()->ip();

        if ($limiter->tooManyAttempts($ipKey, self::FORGOT_MAX_PER_IP)) {
            $this->addError('auth', __('auth.throttle', ['seconds' => $limiter->availableIn($ipKey)]));
            return;
        }
        $limiter->hit($ipKey, 600);

        $identifier = trim($this->identifier);
        $user = User::with('employee')
            ->where('username', $identifier)
            ->orWhereHas('employee', fn ($q) => $q->where('email', $identifier))
            ->first();

        $email = $user?->employee?->email;
        $accountKey = 'forgot-password:account:'.$user?->account_id;

        // Chỉ cấp mật khẩu tạm cho tài khoản đang hoạt động, có email và chưa vừa được cấp
        if ($user && $email && $user->canSignIn() && ! $limiter->tooManyAttempts($accountKey, 1)) {
            $temporaryPassword = Str::password(10, symbols: false);

            try {
                // Gửi mail trước, thành công mới ghi đè để tránh khoá người dùng khi mail lỗi
                Mail::to($email)->send(new TemporaryPasswordMail(
                    $user->employee->full_name, $user->username, $temporaryPassword,
                ));
            } catch (\Throwable $e) {
                Log::error('Gửi mật khẩu tạm thất bại', ['account_id' => $user->account_id, 'error' => $e->getMessage()]);
                $this->addError('auth', __('forgot_password.mail_failed'));
                return;
            }

            // Cột password có cast 'hashed' nên gán mật khẩu thô, model tự băm
            $user->update(['password' => $temporaryPassword]);
            $user->setMustChangePassword(true);
            $limiter->hit($accountKey, self::FORGOT_ACCOUNT_COOLDOWN);
        }

        $this->resetSent = true;
    }

    /**
     * Đếm số lần yêu cầu bằng cache file – không phụ thuộc bảng `cache` trong DB.
     */
    private function limiter(): RateLimiter
    {
        return new RateLimiter(Cache::store('file'));
    }

    public function updated(string $field): void
    {
        $this->resetErrorBag([$field, 'auth']);
    }

    public function render()
    {
        return view('livewire.auth.login')
            ->title($this->mode === 'forgot' ? __('forgot_password.title') : __('auth.login'));
    }
}
