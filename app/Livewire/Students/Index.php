<?php

namespace App\Livewire\Students;

use App\Models\Course;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\Role;
use App\Models\SalesTeam;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public const PER_PAGE = 10;

    public const FILTERS = ['search', 'status', 'course', 'team', 'employee', 'from', 'to', ...self::LEAD_FILTERS];

    public const LEAD_FILTERS = ['source', 'method', 'branch', 'lead_from', 'lead_to'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $course = '';

    #[Url(except: '')]
    public string $team = '';

    #[Url(except: '')]
    public string $employee = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: '')]
    public string $method = '';

    #[Url(except: '')]
    public string $branch = '';

    #[Url(except: '')]
    public string $lead_from = '';

    #[Url(except: '')]
    public string $lead_to = '';

    public function updated(string $property): void
    {
        if ($property === 'team') {
            $this->employee = '';
        }

        if (in_array($property, self::FILTERS, true)) {
            $this->resetPage();
        }
    }

    public function filterStatus(string $status): void
    {
        $this->status = $this->status === $status ? '' : $status;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(...self::FILTERS);
        $this->resetPage();
    }

    public function resetLeadFilters(): void
    {
        $this->reset(...self::LEAD_FILTERS);
        $this->resetPage();
    }

    public function leadFilterParts(): array
    {
        $t = fn (string $key, array $replace) => __('students.lead_filter.'.$key, $replace);
        $from = $this->validDate($this->lead_from) ? $this->lead_from : null;
        $to = $this->validDate($this->lead_to) ? $this->lead_to : null;

        return array_values(array_filter([
            $this->source !== '' ? $t('source', ['value' => $this->source]) : null,
            in_array($this->method, Lead::CONTACT_METHODS, true) ? $t('method', ['value' => __('leads.method.'.$this->method)]) : null,
            $this->branch !== '' ? $t('branch', ['value' => Branch::find($this->branch)?->branch_name ?? $this->branch]) : null,
            $from || $to ? $t('created', ['from' => $from ? date('d/m/Y', strtotime($from)) : '…', 'to' => $to ? date('d/m/Y', strtotime($to)) : '…']) : null,
        ]));
    }

    public function hasFilters(): bool
    {
        foreach (self::FILTERS as $filter) {
            if ($this->$filter !== '') {
                return true;
            }
        }

        return false;
    }

    #[Computed]
    public function me(): ?Employee
    {
        return Auth::user()->employee;
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    #[Computed]
    public function canFilterTeam(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN, Role::ACCOUNTANT], true);
    }

    #[Computed]
    public function canFilterEmployee(): bool
    {
        return $this->canFilterTeam || $this->routePrefix === Role::SALE_LEADER;
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
        if ($this->canFilterTeam) {
            return $this->teams->firstWhere('team_id', $this->team);
        }

        return $this->routePrefix === Role::SALE_LEADER && $this->me?->team_id
            ? SalesTeam::find($this->me->team_id)
            : null;
    }

    #[Computed]
    public function employeeOptions(): Collection
    {
        if (! $this->canFilterEmployee || ! $this->activeTeam) {
            return collect();
        }

        return Employee::query()
            ->where('team_id', $this->activeTeam->team_id)
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALESPERSON))
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'status']);
    }

    #[Computed]
    public function courses(): Collection
    {
        return Course::query()->orderBy('course_id')->get(['course_id', 'course_name']);
    }

    protected function filtered(bool $withStatus = true): Builder
    {
        $search = trim($this->search);

        return Student::query()
            ->visibleTo($this->me)
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($q) => $q->where('student_id', 'like', $like)
                    ->orWhere('full_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->when(
                $withStatus && in_array($this->status, Student::STATUSES, true),
                fn ($q) => $q->where('status', $this->status)
            )
            ->when(
                $this->courses->contains('course_id', $this->course),
                fn ($q) => $q->whereHas('lead.opportunities.quotations', fn ($quo) => $quo
                    ->whereHas('order', fn ($o) => $o->where('paid_amount', '>', 0))
                    ->whereHas('details', fn ($d) => $d->where('course_id', $this->course)))
            )
            ->when(
                $this->canFilterTeam && $this->activeTeam,
                fn ($q) => $q->whereHas('lead.opportunities.employee', fn ($e) => $e->where('team_id', $this->activeTeam->team_id))
            )
            ->when(
                $this->employeeOptions->contains('employee_id', $this->employee),
                fn ($q) => $q->whereHas('lead.opportunities', fn ($o) => $o->where('employee_id', $this->employee))
            )
            ->when($this->validDate($this->from), fn ($q) => $q->whereDate('conversion_date', '>=', $this->from))
            ->when($this->validDate($this->to), fn ($q) => $q->whereDate('conversion_date', '<=', $this->to))
            ->when($this->leadFilterParts() !== [], fn ($q) => $q->whereHas('lead', fn ($l) => $l
                ->when($this->source !== '', fn ($l) => $l->where('source_name', $this->source))
                ->when(in_array($this->method, Lead::CONTACT_METHODS, true), fn ($l) => $l->where('contact_method', $this->method))
                ->when($this->branch !== '', fn ($l) => $l->where('branch_id', $this->branch))
                ->when($this->validDate($this->lead_from), fn ($l) => $l->whereDate('created_at', '>=', $this->lead_from))
                ->when($this->validDate($this->lead_to), fn ($l) => $l->whereDate('created_at', '<=', $this->lead_to))
            ));
    }

    protected function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }

    #[Computed]
    public function stats(): array
    {
        $byStatus = $this->filtered(withStatus: false)
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return ['all' => (int) $byStatus->sum()] + collect(Student::STATUSES)
            ->mapWithKeys(fn ($s) => [$s => (int) ($byStatus[$s] ?? 0)])
            ->all();
    }

    public function render()
    {
        $students = $this->filtered()
            ->with(['lead:lead_id'])
            ->orderByDesc('conversion_date')
            ->orderByDesc('student_id')
            ->paginate(self::PER_PAGE);

        return view('livewire.students.index', [
            'students' => $students,
        ])->title(__('students.title'));
    }
}
