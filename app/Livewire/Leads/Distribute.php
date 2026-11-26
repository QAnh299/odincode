<?php

namespace App\Livewire\Leads;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Role;
use App\Models\SalesTeam;
use App\Services\LeadDistributionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

class Distribute extends Component
{
    public const MODES = ['auto', 'manual'];

    public bool $open = false;

    public string $mode = '';

    public string $branch = '';

    public string $source = '';

    public string $method = '';

    public array $selected = [];

    public array $amounts = [];

    #[Locked]
    public bool $confirming = false;

    #[Computed]
    public function role(): string
    {
        return (string) Auth::user()->role_name;
    }

    #[Computed]
    public function forTeams(): bool
    {
        return $this->role === Role::SALE_ADMIN;
    }

    protected function authorizeAccess(): void
    {
        abort_unless(in_array($this->role, [Role::SALE_ADMIN, Role::SALE_LEADER], true), 403);
    }

    public function mount(bool $autoOpen = false): void
    {
        if ($autoOpen && in_array($this->role, [Role::SALE_ADMIN, Role::SALE_LEADER], true)) {
            $this->show();
        }
    }

    #[On('open-distribute')]
    public function show(): void
    {
        $this->authorizeAccess();

        $this->reset('mode', 'branch', 'source', 'method', 'selected', 'amounts', 'confirming');
        $this->resetValidation();
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->confirming = false;
        $this->resetValidation();
    }

    public function cancelConfirm(): void
    {
        $this->confirming = false;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['branch', 'mode'], true)) {
            $this->reset('selected', 'amounts');
        }
        $this->confirming = false;
        $this->resetValidation();
    }

    #[Computed]
    public function selectableIds(): array
    {
        $open = $this->targets->filter(fn ($row) => $row['capacity'] !== 0)->pluck('id')->all();

        return $open ?: $this->targets->pluck('id')->all();
    }

    public function toggleAll(): void
    {
        $ids = $this->selectableIds;

        $this->selected = array_diff($ids, $this->selected) ? $ids : [];
        $this->confirming = false;
        $this->resetValidation();
    }

    public function adjust(string $id, int $delta): void
    {
        if (! $this->targets->contains('id', $id)) {
            return;
        }

        $value = max(0, (int) ($this->amounts[$id] ?? 0) + $delta);

        $this->amounts[$id] = $value ?: '';
        $this->confirming = false;
        $this->resetValidation();
    }

    #[Computed]
    public function incoming(): array
    {
        return $this->mode === 'auto' ? $this->preview : $this->manualPlan();
    }

    #[Computed]
    public function overLimit(): array
    {
        if ($this->forTeams || ! $this->mode) {
            return [];
        }

        $names = $this->targets->pluck('name', 'id')->all();
        $rows = app(LeadDistributionService::class)->overLimit($this->incoming, $this->load);

        foreach ($rows as $id => $row) {
            $rows[$id]['name'] = $names[$id] ?? $id;
        }

        return $rows;
    }

    #[Computed]
    public function branches(): Collection
    {
        return $this->forTeams ? Branch::query()->orderBy('branch_id')->get(['branch_id', 'branch_name']) : collect();
    }

    #[Computed]
    public function team(): ?SalesTeam
    {
        return Auth::user()->employee?->ledTeam;
    }

    #[Computed]
    public function leaderId(): ?string
    {
        return $this->team?->team_leader_id;
    }

    #[Computed]
    public function targets(): Collection
    {
        $service = app(LeadDistributionService::class);

        if ($this->forTeams) {
            if (! $this->branches->contains('branch_id', $this->branch)) {
                return collect();
            }

            $teams = $service->branchTeams($this->branch);
            $perf = $service->performance('team');

            return $teams->map(fn ($team) => [
                'id'       => $team->team_id,
                'name'     => $team->team_name,
                'sub'      => $team->leader->full_name,
                'owner'    => $team->team_leader_id,
                'load'     => null,
                'capacity' => null,
                'perf'     => $perf[$team->team_id] ?? null,
            ]);
        }

        if (! $this->team) {
            return collect();
        }

        $people = $service->teamSalespersons($this->team->team_id);
        $load = $service->currentLoad($people->pluck('employee_id')->all());
        $perf = $service->performance('employee', $this->team->team_id);

        return $people->map(fn ($person) => [
            'id'       => $person->employee_id,
            'name'     => $person->full_name,
            'sub'      => $person->employee_id,
            'owner'    => $person->employee_id,
            'load'     => $load[$person->employee_id] ?? 0,
            'capacity' => $service->capacity($person->employee_id, $load),
            'perf'     => $perf[$person->employee_id] ?? null,
        ]);
    }

    #[Computed]
    public function load(): array
    {
        return $this->targets->pluck('load', 'id')->filter(fn ($n) => $n !== null)->all();
    }

    protected function rankingIds(): array
    {
        $ids = $this->targets->pluck('id')->all();

        return $this->mode === 'auto'
            ? array_values(array_intersect($ids, $this->selected))
            : $ids;
    }

    #[Computed]
    public function scoreboard(): array
    {
        return app(LeadDistributionService::class)->scoreboard($this->rankingIds(), $this->targets->pluck('perf', 'id')->all());
    }

    #[Computed]
    public function ranks(): array
    {
        $ids = app(LeadDistributionService::class)->rank(
            $this->rankingIds(),
            $this->targets->pluck('perf', 'id')->all(),
            $this->load,
        );

        return $ids ? array_combine($ids, range(1, count($ids))) : [];
    }

    protected function source(): ?string
    {
        return in_array($this->source, Lead::SOURCES, true) ? $this->source : null;
    }

    protected function method(): ?string
    {
        return in_array($this->method, Lead::CONTACT_METHODS, true) ? $this->method : null;
    }

    #[Computed]
    public function available(): int
    {
        $service = app(LeadDistributionService::class);

        if ($this->forTeams) {
            return $this->branches->contains('branch_id', $this->branch)
                ? $service->availableLeads($this->branch, $this->source(), $this->method())->count()
                : 0;
        }

        return $this->leaderId
            ? $service->pendingOpportunities($this->leaderId, $this->source(), $this->method())->count()
            : 0;
    }

    #[Computed]
    public function returning(): array
    {
        $service = app(LeadDistributionService::class);

        if ($this->forTeams) {
            $leadIds = $this->branches->contains('branch_id', $this->branch)
                ? $service->availableLeads($this->branch, $this->source(), $this->method())->pluck('lead_id')->all()
                : [];
        } else {
            $leadIds = $this->leaderId
                ? $service->pendingOpportunities($this->leaderId, $this->source(), $this->method())->pluck('lead_id')->all()
                : [];
        }

        $related = $service->relatedLeads($leadIds);
        $owners = $service->suggestedOwners($leadIds, $related);
        $key = $this->forTeams ? 'leader_id' : 'employee_id';

        $targetIds = $this->targets->pluck('owner')->all();
        $owners = array_filter($owners, fn ($owner) => in_array($owner[$key], $targetIds, true));

        return [
            'returning' => count(array_filter($owners, fn ($o) => $o['reason'] === 'returning')),
            'family'    => count(array_filter($owners, fn ($o) => $o['reason'] === 'family')),

            'batch'     => count($service->familiesIn($leadIds, $related)),
            'by'        => array_count_values(array_column($owners, $key)),
        ];
    }

    protected function selectedRanked(): array
    {
        $valid = array_values(array_intersect($this->targets->pluck('id')->all(), $this->selected));

        return app(LeadDistributionService::class)->rank(
            $valid,
            $this->targets->pluck('perf', 'id')->all(),
            $this->load,
        );
    }

    #[Computed]
    public function leftover(): array
    {
        $count = $this->mode === 'auto' ? count($this->selectedRanked()) : 0;
        if ($count < 2 || $this->available === 0) {
            return ['remainder' => 0, 'over' => 0];
        }

        $service = app(LeadDistributionService::class);
        $over = $this->forTeams
            ? 0
            : $service->excess($service->splitEvenly($this->available, $this->selectedRanked()), $this->load)[1];

        return ['remainder' => $this->available % $count, 'over' => $over];
    }

    #[Computed]
    public function needsRanking(): bool
    {
        return array_sum($this->leftover) > 0;
    }

    #[Computed]
    public function preview(): array
    {
        if ($this->mode !== 'auto') {
            return [];
        }

        $service = app(LeadDistributionService::class);
        $ranked = $this->selectedRanked();
        $plan = $service->splitEvenly($this->available, $ranked);

        return $this->forTeams ? $plan : $service->capAuto($plan, $this->load, $ranked);
    }

    protected function manualPlan(): array
    {
        $plan = [];
        foreach ($this->targets->pluck('id') as $id) {
            $plan[$id] = max(0, (int) ($this->amounts[$id] ?? 0));
        }

        return $plan;
    }

    #[Computed]
    public function manualTotal(): int
    {
        return array_sum($this->manualPlan());
    }

    public function submit(LeadDistributionService $service): void
    {
        $this->process($service, false);
    }

    public function confirmAssign(LeadDistributionService $service): void
    {
        $this->process($service, true);
    }

    protected function process(LeadDistributionService $service, bool $confirmed): void
    {
        $this->authorizeAccess();
        $this->resetValidation();

        if (! in_array($this->mode, self::MODES, true)) {
            $this->addError('mode', __('leads.distribute.mode_required'));

            return;
        }

        if ($this->forTeams && ! $this->branches->contains('branch_id', $this->branch)) {
            $this->addError('branch', __('leads.distribute.branch_required'));

            return;
        }

        if ($this->mode === 'auto') {
            if (! $this->selectedRanked()) {
                $this->addError('targets', __($this->forTeams ? 'leads.distribute.choose_team' : 'leads.distribute.choose_person'));

                return;
            }
            if ($this->available === 0) {
                $this->addError('targets', __('leads.distribute.none_available'));

                return;
            }

            $plan = $this->preview;
        } else {
            foreach ($this->amounts as $value) {
                if ($value !== '' && $value !== null && (! is_numeric($value) || (int) $value != $value || (int) $value < 0)) {
                    $this->addError('targets', __('leads.distribute.amount_invalid'));

                    return;
                }
            }

            $plan = $this->manualPlan();
            $total = array_sum($plan);

            if ($total === 0) {
                $this->addError('targets', __('leads.distribute.amount_required'));

                return;
            }
            if ($total > $this->available) {
                $this->addError('targets', __('leads.distribute.exceed'));

                return;
            }
        }

        if (! $confirmed && $this->overLimit) {
            $this->confirming = true;

            return;
        }

        $this->commit($service, $plan);
    }

    protected function commit(LeadDistributionService $service, array $plan): void
    {
        $owners = $this->targets->pluck('owner', 'id')->all();
        $byOwner = [];
        foreach (array_filter($plan) as $id => $count) {
            $byOwner[$owners[$id]] = $count;
        }

        if (! $byOwner) {
            $this->confirming = false;
            $this->addError('targets', __('leads.distribute.nothing'));

            return;
        }

        try {
            $count = $this->forTeams
                ? $service->assignToTeams($byOwner, $this->branch, $this->source(), $this->method())
                : $service->assignToSalespersons($this->leaderId, $byOwner, $this->source(), $this->method());
        } catch (RuntimeException $e) {
            $this->confirming = false;
            $this->addError('targets', $e->getMessage());

            return;
        }

        $text = __($this->forTeams ? 'leads.distribute.done_team' : 'leads.distribute.done_person', [
            'count' => $count, 'receivers' => count($byOwner),
        ]);

        $this->open = false;
        $this->confirming = false;
        $this->dispatch('leads-distributed', notice: ['type' => 'success', 'text' => $text]);
    }

    public function render()
    {
        return view('livewire.leads.distribute', [
            'month' => app(LeadDistributionService::class)->previousMonth()[2],
        ]);
    }
}
