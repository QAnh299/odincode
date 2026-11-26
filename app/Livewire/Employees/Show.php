<?php

namespace App\Livewire\Employees;

use App\Models\Employee;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Employee $employee;

    public function mount(Employee $employee): void
    {
        abort_unless(
            Employee::query()->visibleTo(Auth::user()->employee)->whereKey($employee->getKey())->exists(),
            403
        );

        $this->employee = $employee->load(['role', 'team.leader', 'branch']);
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    #[Computed]
    public function canViewTeams(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN], true);
    }

    #[Computed]
    public function isSales(): bool
    {
        return $this->employee->hasRole(Role::SALE_LEADER, Role::SALESPERSON);
    }

    #[Computed]
    public function teammates()
    {
        if (! $this->employee->team_id) {
            return collect();
        }

        return Employee::query()
            ->with('role')
            ->where('team_id', $this->employee->team_id)
            ->whereKeyNot($this->employee->getKey())
            ->state(Employee::STATE_WORKING)
            ->orderBy('full_name')
            ->get();
    }

    #[Computed]
    public function linkableIds(): array
    {
        return Employee::query()
            ->visibleTo(Auth::user()->employee)
            ->whereKey($this->teammates->modelKeys())
            ->pluck('employee_id')
            ->all();
    }

    public function render()
    {
        return view('livewire.employees.show')
            ->title(__('employees.detail_title', ['name' => $this->employee->full_name]));
    }

}
