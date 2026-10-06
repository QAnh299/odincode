<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.auth')]
class Login extends Component
{
    public string $username = '';
    public string $password = '';

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
            'username.required' => __('auth.username_required'),
            'password.required' => __('auth.password_required'),
            'password.min'      => __('auth.password_min', ['min' => 6]),
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

        $this->redirectRoute($route);
    }

    public function updated(string $field): void
    {
        $this->resetErrorBag([$field, 'auth']);
    }

    public function render()
    {
        return view('livewire.auth.login')->title(__('auth.login'));
    }
}
