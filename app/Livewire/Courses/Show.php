<?php

namespace App\Livewire\Courses;

use App\Models\Course;
use App\Models\Employee;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\SalesTeam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Show extends Component
{
    use WithPagination;

    public const PER_PAGE = 5;

    public Course $course;

    #[Url]
    public ?string $status = null;

    #[Url(except: '')]
    public string $team = '';

    #[Url(except: '')]
    public string $employee = '';

    public function mount(Course $course): void
    {
        $this->course = $course;
    }

    public function filterStatus(?string $status = null): void
    {
        $status = $this->validStatus($status);
        $this->status = $status === $this->activeStatus() ? null : $status;
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedTeam(): void
    {
        $this->employee = '';
        $this->resetPage();
    }

    public function updatedEmployee(): void
    {
        $this->resetPage();
    }

    public function clearScopeFilters(): void
    {
        $this->reset('team', 'employee');
        $this->resetPage();
    }

    public function activeStatus(): ?string
    {
        return $this->validStatus($this->status);
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    #[Computed]
    public function canFilterTeam(): bool
    {
        return $this->routePrefix === Role::SALE_ADMIN;
    }

    #[Computed]
    public function canFilterEmployee(): bool
    {
        return in_array($this->routePrefix, [Role::SALE_ADMIN, Role::SALE_LEADER], true);
    }

    #[Computed]
    public function teams(): Collection
    {
        return $this->canFilterTeam
            ? SalesTeam::query()->orderBy('team_name')->get(['team_id', 'team_name'])
            : collect();
    }

    #[Computed]
    public function activeTeam(): ?SalesTeam
    {
        return match ($this->routePrefix) {
            Role::SALE_ADMIN  => $this->teams->firstWhere('team_id', $this->team),
            Role::SALE_LEADER => ($teamId = Auth::user()->employee?->team_id) ? SalesTeam::find($teamId) : null,
            default           => null,
        };
    }

    #[Computed]
    public function employeeOptions(): Collection
    {
        if (! $this->canFilterEmployee || ! $this->activeTeam) {
            return collect();
        }

        return Employee::query()
            ->where('team_id', $this->activeTeam->team_id)
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'status']);
    }

    #[Computed]
    public function activeEmployee(): ?Employee
    {
        return $this->employee === '' ? null : $this->employeeOptions->firstWhere('employee_id', $this->employee);
    }

    #[Computed]
    public function scopeEmployeeIds(): ?array
    {
        $own = Auth::user()->employee?->employee_id;

        return match ($this->routePrefix) {
            Role::SALESPERSON => $own ? [$own] : [],
            Role::SALE_LEADER => match (true) {
                $this->activeEmployee !== null => [$this->activeEmployee->employee_id],
                $this->activeTeam !== null     => $this->employeeOptions->pluck('employee_id')->all(),
                default                        => $own ? [$own] : [],
            },
            Role::SALE_ADMIN => match (true) {
                $this->activeEmployee !== null => [$this->activeEmployee->employee_id],
                $this->activeTeam !== null     => $this->employeeOptions->pluck('employee_id')->all(),
                default                        => null,
            },
            default => null,
        };
    }

    #[Computed]
    public function scopeNote(): ?string
    {
        $team = $this->activeTeam?->team_name;
        $name = $this->activeEmployee?->full_name;

        return match (true) {
            $this->routePrefix === Role::SALESPERSON => __('courses.scope_own'),
            $name !== null                           => __('courses.scope_employee', ['name' => $name, 'team' => $team]),
            $team !== null                           => __('courses.scope_team', ['team' => $team]),
            $this->routePrefix === Role::SALE_LEADER => __('courses.scope_own'),
            default                                  => null,
        };
    }

    #[Computed]
    public function stats(): array
    {
        return $this->course->quotationStats($this->scopeEmployeeIds);
    }

    public function render()
    {
        $status = $this->activeStatus();

        $quotations = $this->course->quotationLines($this->scopeEmployeeIds)
            ->leftJoin('Employees', 'Employees.employee_id', '=', 'Quotations.employee_id')
            ->when($status !== null, fn ($q) => $q->where('Quotations.status', $status))
            ->select([
                'QuotationDetails.quotation_id',
                'QuotationDetails.quantity',
                'Quotations.status as quotation_status',
                'Quotations.created_at as quotation_created_at',
                'Employees.full_name as employee_name',
            ])
            ->withCasts(['quotation_created_at' => 'datetime'])
            ->orderByDesc('Quotations.created_at')
            ->orderByDesc('QuotationDetails.quotation_id')
            ->paginate(self::PER_PAGE);

        return view('livewire.courses.show', [
            'quotations'   => $quotations,
            'activeStatus' => $status,
        ])->title(__('courses.detail_title', ['code' => $this->course->course_id]));
    }

    private function validStatus(?string $status): ?string
    {
        return in_array($status, Quotation::STATUSES, true) ? $status : null;
    }
}
