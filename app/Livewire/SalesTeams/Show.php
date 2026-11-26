<?php

namespace App\Livewire\SalesTeams;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\SalesTeam;
use App\Services\SalesPerformanceReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public const ALL_TIME = 'all';

    public const MONTHS = 12;

    public SalesTeam $team;

    #[Url(except: '')]
    public string $month = '';

    public function mount(SalesTeam $team): void
    {
        $this->team = $team->load('leader.role');
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    #[Computed]
    public function members(): Collection
    {
        return $this->team->employees()
            ->with('role')
            ->state(Employee::STATE_WORKING)
            ->get()
            ->sortBy(fn (Employee $e) => [$e->employee_id === $this->team->team_leader_id ? 0 : 1, $e->full_name])
            ->values();
    }

    #[Computed]
    public function branch(): ?Branch
    {
        return ($id = $this->team->branchId()) ? Branch::find($id) : null;
    }

    #[Computed]
    public function months(): array
    {
        $current = CarbonImmutable::today()->startOfMonth();

        return array_map(fn ($i) => $current->subMonths($i)->format('Y-m'), range(0, self::MONTHS - 1));
    }

    #[Computed]
    public function period(): string
    {
        return $this->month === self::ALL_TIME || in_array($this->month, $this->months, true)
            ? $this->month
            : $this->months[0];
    }

    public function periodLabel(string $period): string
    {
        return $period === self::ALL_TIME
            ? __('employees.perf.all_time')
            : __('employees.perf.month', ['month' => CarbonImmutable::parse($period.'-01')->format('m/Y')]);
    }

    #[Computed]
    public function performance(): array
    {
        return $this->report()->totals($this->filters($this->period));
    }

    #[Computed]
    public function previousPerformance(): ?array
    {
        if ($this->period === self::ALL_TIME) {
            return null;
        }

        $previous = CarbonImmutable::parse($this->period.'-01')->subMonth()->format('Y-m');

        return $this->report()->totals($this->filters($previous));
    }

    #[Computed]
    public function target(): array
    {
        if ($this->period === self::ALL_TIME) {
            return [];
        }

        return $this->report()->targets($this->period, [$this->team->team_id])[$this->team->team_id] ?? [];
    }

    #[Computed]
    public function reportUrl(): ?string
    {
        $route = $this->routePrefix.'.reports.sales-performance';

        return Route::has($route)
            ? route($route, array_filter([
                'month' => $this->period === self::ALL_TIME ? null : $this->period,
                'team'  => $this->team->team_id,
            ]))
            : null;
    }

    public function render()
    {
        return view('livewire.sales-teams.show')
            ->title(__('employees.teams.detail_title', ['name' => $this->team->team_name]));
    }

    private function filters(string $period): array
    {
        $range = $period === self::ALL_TIME ? [] : [
            'from' => ($start = CarbonImmutable::parse($period.'-01'))->toDateString(),
            'to'   => $start->endOfMonth()->toDateString(),
        ];

        return $range + ['team' => $this->team->team_id];
    }

    private function report(): SalesPerformanceReport
    {
        return app(SalesPerformanceReport::class);
    }
}
