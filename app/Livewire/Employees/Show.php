<?php

namespace App\Livewire\Employees;

use App\Models\Employee;
use App\Models\Quotation;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Chi tiết một nhân viên (chỉ xem): thông tin cá nhân, công việc, tài khoản,
 * đội kinh doanh và kết quả kinh doanh (với Sale Leader / Salesperson).
 * Sale Leader chỉ xem được nhân viên trong đội của mình.
 */
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

        $this->employee = $employee->load(['role', 'team.leader', 'branch', 'account']);
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * Có trang Đội kinh doanh (Giám đốc, Sale Admin).
     */
    #[Computed]
    public function canViewTeams(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN], true);
    }

    /**
     * Nhân viên kinh doanh (Sale Leader, Salesperson) thì hiện khối kết quả kinh doanh.
     */
    #[Computed]
    public function isSales(): bool
    {
        return $this->employee->hasRole(Role::SALE_LEADER, Role::SALESPERSON);
    }

    /**
     * Thành viên cùng đội (đang làm việc, trừ chính nhân viên này).
     */
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

    /**
     * Kết quả kinh doanh: Opportunity phụ trách / đã chốt, báo giá đã lập / đã xác nhận, lịch hẹn.
     */
    #[Computed]
    public function stats(): array
    {
        $opportunities = $this->employee->opportunities()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $quotations = $this->employee->quotations()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $won = (int) ($opportunities['Won'] ?? 0);
        $lost = (int) ($opportunities['Lost'] ?? 0);

        return [
            'opportunities' => (int) $opportunities->sum(),
            'in_progress'   => (int) ($opportunities['InProgress'] ?? 0),
            'won'           => $won,
            'win_rate'      => $won + $lost > 0 ? $won / ($won + $lost) * 100 : null,
            'quotations'    => (int) $quotations->sum(),
            'confirmed'     => (int) ($quotations[Quotation::STATUS_CONFIRMED] ?? 0),
            'appointments'  => $this->employee->appointments()->count(),
        ];
    }

    public function render()
    {
        return view('livewire.employees.show')
            ->title(__('employees.detail_title', ['name' => $this->employee->full_name]));
    }
}
