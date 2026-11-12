<?php

namespace App\Livewire\Auth;

use App\Mail\TemporaryPasswordMail;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
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
    public const FORGOT_MAX_PER_IP = 5;

    // Thời gian chờ (giây) giữa hai lần cấp mật khẩu tạm cho cùng một tài khoản
    public const FORGOT_ACCOUNT_COOLDOWN = 120;

    // Nhập sai mật khẩu quá số lần này thì tạm khóa đăng nhập tài khoản trong LOGIN_LOCK_SECONDS
    public const LOGIN_MAX_ATTEMPTS = 5;
    public const LOGIN_LOCK_SECONDS = 900;

    // Cookie lưu tên đăng nhập khi chọn "Ghi nhớ tài khoản" (30 ngày, tính bằng phút)
    public const REMEMBER_COOKIE = 'odin_remember_username';
    public const REMEMBER_MINUTES = 60 * 24 * 30;

    // 'login' = form đăng nhập, 'forgot' = form quên mật khẩu
    public string $mode = 'login';

    public string $username = '';
    public string $password = '';

    // "Ghi nhớ tài khoản": điền sẵn tên đăng nhập ở lần mở trang sau
    public bool $remember = false;

    // Email nhập ở form quên mật khẩu
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
            'identifier.email'    => __('forgot_password.identifier_email'),
            'identifier.max'      => __('forgot_password.identifier_max', ['max' => 254]),
        ];
    }

    public function mount(): void
    {
        $saved = request()->cookie(self::REMEMBER_COOKIE);

        if (is_string($saved) && $saved !== '') {
            $this->username = $saved;
            $this->remember = true;
        }
    }

    public function login(): void
    {
        $this->resetErrorBag('auth');
        $this->validate();

        // Đếm số lần sai theo tên đăng nhập (kể cả tên không tồn tại, để không lộ tài khoản nào có thật)
        $limiter = $this->limiter();
        $lockKey = 'login:'.mb_strtolower(trim($this->username));

        if ($limiter->tooManyAttempts($lockKey, self::LOGIN_MAX_ATTEMPTS)) {
            $this->addError('auth', __('auth.locked', [
                'max'     => self::LOGIN_MAX_ATTEMPTS,
                'minutes' => (int) ceil($limiter->availableIn($lockKey) / 60),
            ]));
            return;
        }

        $user = User::with('employee.role')->where('username', $this->username)->first();

        if (! $user || ! Hash::check($this->password, $user->password)) {
            $limiter->hit($lockKey, self::LOGIN_LOCK_SECONDS);
            Log::warning('Đăng nhập sai mật khẩu', ['username' => $this->username, 'ip' => request()->ip(), 'attempts' => $limiter->attempts($lockKey)]);

            $this->addError('auth', $limiter->tooManyAttempts($lockKey, self::LOGIN_MAX_ATTEMPTS)
                ? __('auth.locked', ['max' => self::LOGIN_MAX_ATTEMPTS, 'minutes' => (int) ceil(self::LOGIN_LOCK_SECONDS / 60)])
                : __('auth.failed'));
            return;
        }

        $limiter->clear($lockKey);

        if (! $user->canSignIn()) {
            $this->addError('auth', __('auth.account_disabled'));
            return;
        }

        if (! $route = $user->homeRoute()) {
            $this->addError('auth', __('auth.no_role'));
            return;
        }

        // Bảng Accounts không có cột remember_token → "Ghi nhớ" chỉ lưu tên đăng nhập vào cookie,
        // không giữ phiên đăng nhập
        Auth::login($user);
        session()->regenerate();

        if ($this->remember) {
            Cookie::queue(self::REMEMBER_COOKIE, $user->username, self::REMEMBER_MINUTES);
        } else {
            Cookie::queue(Cookie::forget(self::REMEMBER_COOKIE));
        }

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
        $this->validate(['identifier' => 'required|string|email|max:254']);

        $limiter = $this->limiter();
        $ipKey = 'forgot-password:ip:'.request()->ip();

        if ($limiter->tooManyAttempts($ipKey, self::FORGOT_MAX_PER_IP)) {
            $this->addError('auth', __('auth.throttle', ['seconds' => $limiter->availableIn($ipKey)]));
            return;
        }
        $limiter->hit($ipKey, 600);

        // Chỉ tra theo email của nhân viên gắn với tài khoản
        $email = trim($this->identifier);
        $user = User::with('employee')
            ->whereHas('employee', fn ($q) => $q->where('email', $email))
            ->first();

        $email = $user?->employee?->email;
        $accountKey = 'forgot-password:account:'.$user?->account_id;

        // Chỉ cấp mật khẩu tạm cho tài khoản đang hoạt động, có email và chưa vừa được cấp
        if ($user && $email && $user->canSignIn() && ! $limiter->tooManyAttempts($accountKey, 1)) {
            $temporaryPassword = Str::password(10, symbols: false);

            try {
                // Gửi mail trước, thành công mới ghi đè để tránh khoá người dùng khi mail lỗi
                Mail::to($email)->send(new TemporaryPasswordMail(
                    $user->employee->full_name,
                    $user->username,
                    $temporaryPassword,
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
     * Đếm số lần yêu cầu (quên mật khẩu, đăng nhập sai) bằng cache file - không phụ thuộc bảng `cache` trong DB.
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
