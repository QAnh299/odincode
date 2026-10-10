<?php

namespace App\Livewire\SalesTeams;

use App\Models\Employee;
use App\Models\Role;
use App\Models\SalesTeam;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Danh sách đội kinh doanh + tra cứu (tìm theo mã / tên đội / trưởng nhóm, sắp xếp).
 * Chỉ xem. Dùng cho Giám đốc và Sale Admin.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    const PER_PAGE = 10;

    const SORTS = ['name', 'newest', 'oldest', 'members'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'name')]
    public string $sort = 'name';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'sort');
        $this->resetPage();
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * Số liệu tổng quan: số đội, nhân viên kinh doanh đang thuộc đội, nhân viên kinh doanh chưa có đội.
     */
    #[Computed]
    public function summary(): array
    {
        $sales = fn () => Employee::query()
            ->state(Employee::STATE_WORKING)
            ->whereHas('role', fn ($q) => $q->whereIn('role_name', [Role::SALE_LEADER, Role::SALESPERSON]));

        return [
            'teams'   => SalesTeam::count(),
            'in_team' => $sales()->whereNotNull('team_id')->count(),
            'without' => $sales()->whereNull('team_id')->count(),
        ];
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->sort !== 'name';
    }

    public function render()
    {
        $search = trim($this->search);

        $teams = SalesTeam::query()
            ->with([
                'leader',
                'employees' => fn ($q) => $q->state(Employee::STATE_WORKING)->orderBy('full_name'),
            ])
            ->withCount(['employees as members_count' => fn ($q) => $q->state(Employee::STATE_WORKING)])
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($q) => $q->where('team_id', 'like', $like)
                    ->orWhere('team_name', 'like', $like)
                    ->orWhereHas('leader', fn ($q) => $q->where('full_name', 'like', $like))
                    ->orWhereHas('employees', fn ($q) => $q->where('full_name', 'like', $like)));
            })
            ->tap(fn ($q) => match ($this->sort) {
                'newest'  => $q->orderByDesc('established_date')->orderBy('team_id'),
                'oldest'  => $q->orderBy('established_date')->orderBy('team_id'),
                'members' => $q->orderByDesc('members_count')->orderBy('team_name'),
                default   => $q->orderBy('team_name')->orderBy('team_id'),
            })
            ->paginate(self::PER_PAGE);

        return view('livewire.sales-teams.index', [
            'teams' => $teams,
        ])->title(__('employees.teams.title'));
    }
}
