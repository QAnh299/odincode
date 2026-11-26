<?php

namespace App\Livewire\Opportunities;

use App\Models\Appointment;
use App\Models\Employee;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\SalesTeam;
use App\Models\Stage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Kanban extends Component
{
    public const PRESETS = ['today', 'yesterday', 'last7', 'month'];

    public const VIEWS = ['kanban', 'list'];

    public const SORTS = ['default', 'newest', 'value'];

    public const PAGE_SIZE = 20;

    public const DECISION_WARN_DAYS = 3;

    public const URGENCY = [
        'overdue_appointment' => 0,
        'needs_quotation'     => 1,
        'decision_due'        => 2,
        'recontact'           => 3,
        'not_contacted'       => 4,
    ];

    #[Url(except: 'kanban')]
    public string $view = 'kanban';

    #[Url(except: 'default')]
    public string $sort = 'default';

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'stage', except: '')]
    public string $stageFilter = '';

    #[Url(except: false)]
    public bool $urgent = false;

    public array $shown = [];

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: '')]
    public string $team = '';

    #[Url(except: '')]
    public string $employee = '';

    public function updatedTeam(): void
    {
        $this->employee = '';
    }

    public function applyPreset(string $preset): void
    {
        [$this->from, $this->to] = $this->presetRange($preset) ?? ['', ''];
    }

    public function resetFilters(): void
    {
        $this->reset('from', 'to', 'source', 'team', 'employee', 'urgent', 'search', 'stageFilter', 'shown');
    }

    public function hasFilters(): bool
    {
        return $this->from !== '' || $this->to !== '' || $this->source !== '' || $this->team !== '' || $this->employee !== '' || $this->urgent || $this->search !== '' || $this->stageFilter !== '';
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'to', 'source', 'team', 'employee', 'search', 'stageFilter'], true)) {
            $this->shown = [];
        }
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, self::VIEWS, true) ? $view : 'kanban';
        $this->shown = [];

        if ($this->activeView() === 'kanban') {
            $this->stageFilter = '';
        }
    }

    public function setSort(string $sort): void
    {
        $this->sort = in_array($sort, self::SORTS, true) ? $sort : 'default';
        $this->shown = [];
    }

    public function activeView(): string
    {
        return in_array($this->view, self::VIEWS, true) ? $this->view : 'kanban';
    }

    public function activeSort(): string
    {
        return in_array($this->sort, self::SORTS, true) ? $this->sort : 'default';
    }

    public function toggleUrgent(): void
    {
        $this->urgent = ! $this->urgent;
        $this->shown = [];
    }

    public function showMore(string $stageId): void
    {
        $this->shown[$stageId] = $this->limitFor($stageId) + self::PAGE_SIZE;
    }

    public function limitFor(string $stageId): int
    {
        return max(self::PAGE_SIZE, (int) ($this->shown[$stageId] ?? 0));
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

    public function isSalesperson(): bool
    {
        return $this->routePrefix === Role::SALESPERSON;
    }

    public function canCreateOpportunity(): bool
    {
        return $this->isSalesperson();
    }

    #[Computed]
    public function canFilterTeam(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN], true);
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
            Role::DIRECTOR, Role::SALE_ADMIN => $this->teams->firstWhere('team_id', $this->team),
            Role::SALE_LEADER => $this->me?->team_id ? SalesTeam::find($this->me->team_id) : null,
            default           => null,
        };
    }

    #[Computed]
    public function employeeOptions(): Collection
    {
        if ($this->isSalesperson() || ! $this->activeTeam) {
            return collect();
        }

        return Employee::query()
            ->where('team_id', $this->activeTeam->team_id)
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALESPERSON))
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'status']);
    }

    public function activePreset(): ?string
    {
        if ($this->from === '' && $this->to === '') {
            return '';
        }

        foreach (self::PRESETS as $preset) {
            if ($this->presetRange($preset) === [$this->from, $this->to]) {
                return $preset;
            }
        }

        return null;
    }

    protected function presetRange(string $preset): ?array
    {
        $today = CarbonImmutable::today();

        $range = match ($preset) {
            'today'     => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'last7'     => [$today->subDays(6), $today],
            'month'     => [$today->startOfMonth(), $today],
            default     => null,
        };

        return $range ? [$range[0]->toDateString(), $range[1]->toDateString()] : null;
    }

    protected function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }

    #[Computed]
    public function stages(): Collection
    {
        return Stage::query()->orderBy('sort_order')->get();
    }

    public function render()
    {
        $opportunities = Opportunity::query()
            ->visibleTo($this->me)
            ->assigned()
            ->whereDoesntHave('student')
            ->whereNot(fn ($q) => $q
                ->where('status', \App\Models\Opportunity::STATUS_WON)
                ->whereHas('lead.student')
            )
            ->when(
                $this->activeTeam && $this->canFilterTeam,
                fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('team_id', $this->activeTeam->team_id))
            )
            ->when(
                $this->employeeOptions->contains('employee_id', $this->employee),
                fn ($q) => $q->where('employee_id', $this->employee)
            )
            ->when($this->validDate($this->from), fn ($q) => $q->whereRaw(
                'DATE(COALESCE((SELECT MAX(changed_at) FROM StagesHistory WHERE StagesHistory.opportunity_id = Opportunities.opportunity_id AND StagesHistory.to_stage_id = Opportunities.stage_id), conversion_date)) >= ?',
                [$this->from]
            ))
            ->when($this->validDate($this->to), fn ($q) => $q->whereRaw(
                'DATE(COALESCE((SELECT MAX(changed_at) FROM StagesHistory WHERE StagesHistory.opportunity_id = Opportunities.opportunity_id AND StagesHistory.to_stage_id = Opportunities.stage_id), conversion_date)) <= ?',
                [$this->to]
            ))
            ->when($this->source !== '', fn ($q) => $q->whereRelation('lead', 'source_name', $this->source))
            ->when(trim($this->search) !== '', fn ($q) => $this->applySearch($q))
            ->when($this->activeView() === 'kanban', fn ($q) => $q->where('stage_id', '!=', Stage::ARCHIVED))
            ->when(
                $this->activeView() === 'list' && $this->stages->contains('stage_id', $this->stageFilter),
                fn ($q) => $q->where('stage_id', $this->stageFilter)
            )
            ->with(['lead:lead_id,full_name,phone,email,source_name,contact_method', 'employee:employee_id,full_name'])

            ->withCount([
                'quotations',
                'appointments as overdue_appointments' => fn ($q) => $q
                    ->where('status', Appointment::STATUS_SCHEDULED)
                    ->where('scheduled_time', '<=', now()),
                'careResults',
            ])
            ->withLastCare()
            ->withStageEnteredAt()
            ->orderByDesc('conversion_date')
            ->get();

        $this->markUrgency($opportunities);
        $urgentCount = $opportunities->whereNotNull('urgency')->count();

        if ($this->urgent) {
            $opportunities = $opportunities->whereNotNull('urgency')->values();
        }

        $opportunities = match ($this->activeSort()) {
            'newest' => $opportunities,
            'value'  => $opportunities->sortByDesc(fn (Opportunity $opp) => (float) $opp->expected_value)->values(),
            default  => $opportunities
                ->sortBy(fn (Opportunity $opp) => [self::URGENCY[$opp->urgency] ?? 99, $opp->urgency_days ?? 0])
                ->values(),
        };

        $columns = $opportunities->groupBy('stage_id');

        return view('livewire.opportunities.kanban', [
            'columns'       => $columns,
            'opportunities' => $opportunities,
            'total'         => $opportunities->count(),
            'totalValue'    => $opportunities->sum('expected_value'),
            'urgentCount'   => $urgentCount,
        ])->title(__('opportunities.title'));
    }

    protected function applySearch($query): void
    {
        $like = '%'.addcslashes(trim($this->search), '%_\\').'%';

        $query->where(fn ($q) => $q
            ->where('opportunity_id', 'like', $like)
            ->orWhereHas('lead', fn ($l) => $l
                ->where('full_name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like)));
    }

    protected function markUrgency(Collection $opportunities): void
    {
        foreach ($opportunities as $opp) {
            $days = null;

            if ($opp->status === Opportunity::STATUS_IN_PROGRESS && $opp->stage_id === Stage::PROCESSED
                && $deadline = $opp->decisionDeadline()) {
                $days = max(0, (int) today()->diffInDays($deadline->copy()->startOfDay(), false));
            }

            $opp->urgency = match (true) {
                $opp->status !== Opportunity::STATUS_IN_PROGRESS                                  => null,
                $opp->overdue_appointments > 0                                                    => 'overdue_appointment',
                $opp->needsQuotation()                                                            => 'needs_quotation',
                $days !== null && $days <= self::DECISION_WARN_DAYS                               => 'decision_due',
                $opp->recontactDue()                                                              => 'recontact',
                $opp->stage_id === Stage::UNTOUCHED && $opp->care_results_count === 0             => 'not_contacted',
                default                                                                           => null,
            };
            $opp->urgency_days = $opp->urgency === 'decision_due' ? $days : null;
        }
    }
}
