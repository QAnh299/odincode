<?php

namespace App\Livewire\Profile;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Trang thông tin tài khoản cá nhân – dùng chung cho mọi vai trò.
 * Mỗi người chỉ xem được tài khoản của chính mình; chỉ được tự sửa email và số điện thoại.
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public bool $editing = false;

    // Hiện thông báo "đã lưu" sau khi cập nhật
    public bool $saved = false;

    public string $email = '';
    public string $phone = '';

    #[Computed]
    public function account(): User
    {
        return Auth::user()->load('employee.role', 'employee.branch', 'employee.team.leader');
    }

    #[Computed]
    public function employee(): ?Employee
    {
        return $this->account->employee;
    }

    protected function rules(): array
    {
        return [
            'email' => 'nullable|email|max:254',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s.\-]{8,20}$/'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'email' => __('profile.email'),
            'phone' => __('profile.phone'),
        ];
    }

    public function edit(): void
    {
        abort_unless($this->employee, 403);

        $this->email = (string) $this->employee->email;
        $this->phone = (string) $this->employee->phone;
        $this->resetErrorBag();
        $this->saved = false;
        $this->editing = true;
    }

    public function cancel(): void
    {
        $this->resetErrorBag();
        $this->editing = false;
    }

    public function save(): void
    {
        abort_unless($this->employee, 403);

        $data = $this->validate();

        $this->employee->update([
            'email' => trim($data['email'] ?? '') ?: null,
            'phone' => trim($data['phone'] ?? '') ?: null,
        ]);

        unset($this->account, $this->employee);
        $this->editing = false;
        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.profile.show')->title(__('profile.title'));
    }
}
