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
    public const FORGOT_MAX_PER_IP = 5;

    public const FORGOT_ACCOUNT_COOLDOWN = 120;

    public const LOGIN_MAX_ATTEMPTS = 5;
    public const LOGIN_LOCK_SECONDS = 900;

    public const REMEMBER_COOKIE = 'odin_remember_username';
    public const REMEMBER_MINUTES = 60 * 24 * 30;

    public string $mode = 'login';

    public string $username = '';
    public string $password = '';

    public bool $remember = false;

    public string $identifier = '';

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

        Auth::login($user);
        session()->regenerate();

        if ($this->remember) {
            Cookie::queue(self::REMEMBER_COOKIE, $user->username, self::REMEMBER_MINUTES);
        } else {
            Cookie::queue(Cookie::forget(self::REMEMBER_COOKIE));
        }

        $this->redirectRoute($user->mustChangePassword() ? 'profile.password' : $route);
    }

    public function switchMode(string $mode): void
    {
        $this->mode = $mode === 'forgot' ? 'forgot' : 'login';
        $this->resetSent = false;
        $this->reset('password', 'identifier');
        $this->resetErrorBag();
    }

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

        $email = trim($this->identifier);
        $user = User::with('employee')
            ->whereHas('employee', fn ($q) => $q->where('email', $email))
            ->first();

        $email = $user?->employee?->email;
        $accountKey = 'forgot-password:account:'.$user?->account_id;

        if ($user && $email && $user->canSignIn() && ! $limiter->tooManyAttempts($accountKey, 1)) {
            $temporaryPassword = Str::password(10, symbols: false);

            try {
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

            $user->update(['password' => $temporaryPassword]);
            $user->setMustChangePassword(true);
            $limiter->hit($accountKey, self::FORGOT_ACCOUNT_COOLDOWN);
        }

        $this->resetSent = true;
    }

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
