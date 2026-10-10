<?php

namespace App\Livewire\Employees;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\SalesTeam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Danh sách nhân viên + tra cứu (tìm kiếm, lọc vai trò / đội / chi nhánh / trạng thái).
 * Chỉ xem. Phạm vi theo Employee::scopeVisibleTo:
 *   - Giám đốc: toàn bộ nhân viên.
 *   - Sale Admin: Sale Leader + Salesperson.
 *   - Sale Leader: Salesperson trong đội của mình (không có bộ lọc vai trò / đội / chi nhánh).
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    const PER_PAGE = 10;

    const SORTS = ['code', 'name', 'newest', 'oldest'];

    // Giá trị bộ lọc đặc biệt: nhân viên chưa thuộc đội nào
    const NO_TEAM = 'none';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: '')]
    public string $team = '';

    #[Url(except: '')]
    public string $branch = '';

    #[Url(except: '')]
    public string $state = '';

    #[Url(except: 'code')]
    public string $sort = 'code';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'team', 'branch', 'state', 'sort'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Bấm vào thẻ thống kê: lọc theo trạng thái, bấm lại thì bỏ lọc.
     */
    public function filterState(string $state): void
    {
        $this->state = $this->state === $state ? '' : $state;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'role', 'team', 'branch', 'state', 'sort');
        $this->resetPage();
    }

    /**
     * Prefix route theo vai trò, VD: 'director' → route 'director.employees.show'.
     */
    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * Có bộ lọc Đội và Chi nhánh (Giám đốc, Sale Admin); Sale Leader chỉ xem đội của mình.
     */
    #[Computed]
    public function canFilterScope(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN], true);
    }

    /**
     * Vai trò cho bộ lọc: chỉ các vai trò mà người đang đăng nhập được xem.
     */
    #[Computed]
    public function roles(): Collection
    {
        $visible = Employee::visibleRolesFor($this->routePrefix) ?? array_keys(Role::HOME_ROUTES);

        return Role::query()->whereIn('role_name', $visible)
            ->orderBy('role_id')->get(['role_id', 'role_name']);
    }

    /**
     * Chỉ hiện bộ lọc Vai trò khi có từ 2 vai trò trở lên (Sale Leader chỉ xem Salesperson).
     */
    #[Computed]
    public function canFilterRole(): bool
    {
        return $this->roles->count() > 1;
    }

    #[Computed]
    public function teams(): Collection
    {
        return $this->canFilterScope
            ? SalesTeam::query()->orderBy('team_name')->get(['team_id', 'team_name'])
            : collect();
    }

    #[Computed]
    public function branches(): Collection
    {
        return $this->canFilterScope
            ? Branch::query()->orderBy('branch_name')->get(['branch_id', 'branch_name'])
            : collect();
    }

    /**
     * Đội đang xem của Sale Leader (hiện trong tiêu đề).
     */
    #[Computed]
    public function ownTeam(): ?SalesTeam
    {
        $teamId = Auth::user()->employee?->team_id;

        return $this->routePrefix === Role::SALE_LEADER && $teamId ? SalesTeam::find($teamId) : null;
    }

    /**
     * Số nhân viên theo từng trạng thái trong phạm vi được xem (cho các thẻ thống kê).
     */
    #[Computed]
    public function counts(): array
    {
        $counts = ['all' => $this->baseQuery()->count()];

        foreach (Employee::STATES as $state) {
            $counts[$state] = $this->baseQuery()->state($state)->count();
        }

        return $counts;
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->role !== '' || $this->team !== ''
            || $this->branch !== '' || $this->state !== '' || $this->sort !== 'code';
    }

    public function render()
    {
        $search = trim($this->search);

        $employees = $this->baseQuery()
            ->with(['role', 'team', 'branch'])
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($q) => $q->where('employee_id', 'like', $like)
                    ->orWhere('full_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like));
            })
            ->when($this->roles->contains('role_id', $this->role), fn ($q) => $q->where('role_id', $this->role))
            ->when($this->canFilterScope && $this->team === self::NO_TEAM, fn ($q) => $q->whereNull('team_id'))
            ->when($this->teams->contains('team_id', $this->team), fn ($q) => $q->where('team_id', $this->team))
            ->when($this->branches->contains('branch_id', $this->branch), fn ($q) => $q->where('branch_id', $this->branch))
            ->when(in_array($this->state, Employee::STATES, true), fn ($q) => $q->state($this->state))
            ->tap(fn ($q) => match ($this->sort) {
                'name'   => $q->orderBy('full_name')->orderBy('employee_id'),
                'newest' => $q->orderByDesc('hire_date')->orderByDesc('employee_id'),
                'oldest' => $q->orderBy('hire_date')->orderBy('employee_id'),
                default  => $q->orderBy('employee_id'),
            })
            ->paginate(self::PER_PAGE);

        return view('livewire.employees.index', [
            'employees' => $employees,
        ])->title(__('employees.title'));
    }

    /**
     * Nhân viên trong phạm vi được xem của người đang đăng nhập.
     */
    private function baseQuery(): Builder
    {
        return Employee::query()->visibleTo(Auth::user()->employee);
    }
}
