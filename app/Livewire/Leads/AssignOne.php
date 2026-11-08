<?php

namespace App\Livewire\Leads;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\SalesTeam;
use App\Services\LeadDistributionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

/**
 * Popup "Phân Lead" cho một Lead cụ thể (bổ sung cho phân theo số lượng ở Leads\Distribute):
 *   - Sale Admin: Lead chưa phân cho đội → chọn đội của chi nhánh Lead.
 *   - Sale Leader: Lead đội đang giữ, chờ phân → chọn Salesperson trong đội (ngưỡng 50 chỉ cảnh báo).
 * Khách cũ quay lại / người thân dùng chung SĐT, email: hiện người thân và chọn sẵn đội / người đang hoặc từng
 * phụ trách (nếu còn trong danh sách) để người thân được chăm sóc chung. Người thân cũng đang chờ phân có ô
 * "Phân chung" (tick sẵn) để phân cùng lúc cho cùng đội / người.
 * Mở bằng sự kiện 'open-assign-one' (leadId), xong thì phát 'leads-distributed' như popup phân chia.
 */
class AssignOne extends Component
{
    public bool $open = false;

    #[Locked]
    public string $leadId = '';

    // team_leader_id (Sale Admin) hoặc employee_id (Sale Leader)
    public string $target = '';

    // Người thân (lead_id) được phân chung cùng đội / người với Lead này
    public array $withFamily = [];

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

    /**
     * Đội do Sale Leader đang đăng nhập làm trưởng nhóm.
     */
    #[Computed]
    public function team(): ?SalesTeam
    {
        return Auth::user()->employee?->ledTeam;
    }

    #[Computed]
    public function lead(): ?Lead
    {
        return $this->leadId !== '' ? Lead::with('branch:branch_id,branch_name')->find($this->leadId) : null;
    }

    /**
     * Lead còn phân được bởi người dùng hiện tại (cùng điều kiện với LeadDistributionService::assignLead*).
     */
    public static function assignable(Lead $lead): bool
    {
        $employee = Auth::user()->employee;

        return match ($employee?->role_name) {
            Role::SALE_ADMIN  => Lead::query()->whereKey($lead->lead_id)->unassigned()->exists(),
            // Chỉ trưởng nhóm phân Lead của đội mình đang giữ
            Role::SALE_LEADER => $employee->ledTeam !== null
                && app(LeadDistributionService::class)->pendingOpportunities($employee->employee_id)->where('lead_id', $lead->lead_id)->exists(),
            default           => false,
        };
    }

    #[On('open-assign-one')]
    public function show(string $leadId): void
    {
        $lead = Lead::find($leadId);
        abort_unless($lead && self::assignable($lead), 403);

        $this->leadId = $lead->lead_id;
        unset($this->lead, $this->targets, $this->previous, $this->family);
        $this->resetValidation();

        // Chọn sẵn đội / người từng phụ trách khách cũ / đang phụ trách người thân (nếu còn nhận Lead)
        $suggested = $this->previous['owner'] ?? null;
        $this->target = $suggested && $this->targets->contains('id', $suggested) ? $suggested : '';
        // Tick sẵn "Phân chung" cho người thân cũng đang chờ người dùng này phân
        $this->withFamily = $this->family->where('assignable', true)->pluck('lead_id')->values()->all();
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetValidation();
    }

    /**
     * Đội / người nên nhận Lead (LeadDistributionService::suggestedOwners):
     * ['owner' => id đối tượng nhận, 'name' => tên người phụ trách · đội, 'reason' => 'returning'|'family'];
     * null nếu không có gợi ý.
     */
    #[Computed]
    public function previous(): ?array
    {
        if (! $this->lead) {
            return null;
        }

        $owner = app(LeadDistributionService::class)->suggestedOwners([$this->lead->lead_id])[$this->lead->lead_id] ?? null;
        if (! $owner) {
            return null;
        }

        $employee = Employee::with('team:team_id,team_name')->find($owner['employee_id']);

        return [
            'owner'  => $this->forTeams ? $owner['leader_id'] : $owner['employee_id'],
            'name'   => trim(($employee?->full_name ?? '-').($employee?->team ? ' · '.$employee->team->team_name : '')),
            'reason' => $owner['reason'],
        ];
    }

    /**
     * Người thân dùng chung SĐT / email (anh chị em, phụ huynh đăng ký cho các con…) và tình trạng của họ:
     * [['lead_id', 'name', 'state' => mô tả ai đang / từng phụ trách], ...].
     */
    #[Computed]
    public function family(): Collection
    {
        if (! $this->lead) {
            return collect();
        }

        $ids = app(LeadDistributionService::class)->relatedLeads([$this->lead->lead_id])[$this->lead->lead_id] ?? [];

        return Lead::query()
            ->whereIn('lead_id', $ids)
            ->with(['latestOpportunity.employee:employee_id,full_name', 'latestOpportunity.employee.ledTeam:team_id,team_name,team_leader_id'])
            ->orderBy('created_at')
            ->get()
            ->map(function (Lead $relative) {
                $opportunity = $relative->latestOpportunity;
                $owner = $opportunity?->employee;

                $state = match (true) {
                    ! $opportunity => __('leads.assign_one.family_state.waiting'),
                    $relative->status === Lead::STATUS_NEW && $opportunity->status === Opportunity::STATUS_IN_PROGRESS
                        => __('leads.assign_one.family_state.team', ['team' => $owner?->ledTeam?->team_name ?? $owner?->full_name ?? '-']),
                    $relative->status === Lead::STATUS_NEW => __('leads.assign_one.family_state.waiting'),
                    default => __('leads.assign_one.family_state.'.$opportunity->status, ['name' => $owner?->full_name ?? '-']),
                };

                // Màu nhãn trạng thái: chờ phân = cam, đang tư vấn / học viên = xanh, đã kết thúc = xám
                $tone = match (true) {
                    ! $opportunity || $relative->status === Lead::STATUS_NEW => 'wait',
                    in_array($opportunity->status, [Opportunity::STATUS_IN_PROGRESS, Opportunity::STATUS_WON], true) => 'active',
                    default => 'done',
                };

                return [
                    'lead_id'    => $relative->lead_id,
                    'name'       => $relative->full_name,
                    'state'      => $state,
                    'tone'       => $tone,
                    // Phân chung được: người thân cũng đang chờ người dùng này phân; Sale Admin thêm điều kiện cùng
                    // chi nhánh (đội nhận Lead theo chi nhánh)
                    'assignable' => ($this->forTeams ? $relative->branch_id === $this->lead->branch_id : true)
                        && self::assignable($relative),
                ];
            });
    }

    /**
     * Đối tượng nhận: [['id', 'name', 'sub', 'load' (null với đội), 'full' (đủ ngưỡng 50)], ...].
     */
    #[Computed]
    public function targets(): Collection
    {
        $service = app(LeadDistributionService::class);

        if (! $this->lead) {
            return collect();
        }

        if ($this->forTeams) {
            return $service->branchTeams($this->lead->branch_id)->map(fn ($team) => [
                'id'   => $team->team_leader_id,
                'name' => $team->team_name,
                'sub'  => __('leads.assign_one.leader', ['name' => $team->leader->full_name]),
                'load' => null,
                'full' => false,
            ]);
        }

        if (! $this->team) {
            return collect();
        }

        $people = $service->teamSalespersons($this->team->team_id);
        $load = $service->currentLoad($people->pluck('employee_id')->all());

        return $people->map(fn ($person) => [
            'id'   => $person->employee_id,
            'name' => $person->full_name,
            'sub'  => $person->employee_id,
            'load' => $load[$person->employee_id] ?? 0,
            'full' => $service->capacity($person->employee_id, $load) === 0,
        ]);
    }

    public function submit(LeadDistributionService $service): void
    {
        $lead = $this->lead;
        abort_unless($lead && self::assignable($lead), 403);

        $target = $this->targets->firstWhere('id', $this->target);
        if (! $target) {
            $this->addError('target', __($this->forTeams ? 'leads.assign_one.choose_team' : 'leads.assign_one.choose_person'));

            return;
        }

        // Lead này + người thân được tick "Phân chung" (chỉ nhận người thân còn phân được)
        $relatives = $this->family->where('assignable', true)->whereIn('lead_id', $this->withFamily);
        $leadIds = [$lead->lead_id, ...$relatives->pluck('lead_id')->all()];

        try {
            // Phân chung: tất cả thành công hoặc không Lead nào được phân
            DB::transaction(function () use ($service, $leadIds, $target) {
                foreach ($leadIds as $leadId) {
                    $this->forTeams
                        ? $service->assignLeadToTeam($leadId, $target['id'])
                        : $service->assignLeadToSalesperson(Auth::user()->employee->employee_id, $leadId, $target['id']);
                }
            });
        } catch (RuntimeException $e) {
            unset($this->family);
            $this->addError('target', $e->getMessage());

            return;
        }

        $this->open = false;
        $this->dispatch('leads-distributed', notice: ['type' => 'success', 'text' => count($leadIds) > 1
            ? __('leads.assign_one.done_many', ['count' => count($leadIds), 'ids' => implode(', ', $leadIds), 'target' => $target['name']])
            : __('leads.assign_one.done', ['id' => $lead->lead_id, 'name' => $lead->full_name, 'target' => $target['name']]),
        ]);
    }

    public function render()
    {
        return view('livewire.leads.assign-one', ['max' => LeadDistributionService::MAX_PER_SALESPERSON]);
    }
}
