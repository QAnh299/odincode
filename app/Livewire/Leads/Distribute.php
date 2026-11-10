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

/**
 * Popup Phân bổ Lead (nhúng trong trang Quản lý Lead).
 *   - Sale Admin: phân Lead mới của 1 chi nhánh cho các Sales Team (đội cùng chi nhánh, có trưởng nhóm).
 *   - Sale Leader: phân Lead đội đang giữ cho Salesperson trong đội (ngưỡng 50 Lead đang xử lý/người).
 * Chọn phương thức trước (dropdown) rồi mới hiện chi nhánh / nguồn / kênh liên hệ / danh sách nhận Lead.
 * Chia tự động: chia đều, chỉ khi có Lead dư mới hiện chỉ số hiệu suất (tháng trước) để chọn Top 2.
 * Chia thủ công: bảng đội / salesperson - số lượng nhập tay.
 * Ngưỡng 50 (chỉ áp cho Salesperson) chỉ là cảnh báo: thanh tải đổi đỏ + ghi "vượt X" ngay khi nhập / xem trước;
 * chia tự động ưu tiên người còn chỗ, hết chỗ thì phần còn lại vẫn giao cho Top 2 hiệu suất.
 * Bấm Phân công mà có người vượt 50 → hộp xác nhận liệt kê ai vượt bao nhiêu Lead, "Vẫn phân" thì ghi luôn.
 * Khách cũ quay lại / người thân dùng chung SĐT, email: gợi ý đội / người đang hoặc từng phụ trách; khi phân được
 * ưu tiên giao cho họ nếu họ nhận Lead, người thân cùng lần phân được giao chung (LeadDistributionService::matchPreferred).
 */
class Distribute extends Component
{
    const MODES = ['auto', 'manual'];

    public bool $open = false;

    // '' = chưa chọn → chỉ hiện ô chọn phương thức
    public string $mode = '';

    // Chi nhánh của Lead (chỉ Sale Admin)
    public string $branch = '';

    // '' = Tất cả
    public string $source = '';

    public string $method = '';

    // Chia tự động: các id được chọn (team_id hoặc employee_id)
    public array $selected = [];

    // Chia thủ công: [id => số Lead]
    public array $amounts = [];

    // Đang hỏi xác nhận phân dù có Salesperson vượt ngưỡng 50 (đổi bất kỳ lựa chọn nào thì bỏ)
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

    /**
     * Mở sẵn popup khi vào trang từ thông báo (?distribute=1).
     */
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

    /**
     * Hộp xác nhận vượt ngưỡng → "Quay lại" để sửa số lượng.
     */
    public function cancelConfirm(): void
    {
        $this->confirming = false;
    }

    public function updated(string $property): void
    {
        // Đổi tiêu chí lọc thì danh sách đội / Lead khả dụng thay đổi → bỏ lựa chọn không còn hợp lệ
        if (in_array($property, ['branch', 'mode'], true)) {
            $this->reset('selected', 'amounts');
        }
        $this->confirming = false;
        $this->resetValidation();
    }

    /**
     * Id được "Chọn tất cả": bỏ qua salesperson đã đủ 50 Lead, trừ khi cả đội đều đã đủ.
     *
     * @return string[]
     */
    #[Computed]
    public function selectableIds(): array
    {
        $open = $this->targets->filter(fn ($row) => $row['capacity'] !== 0)->pluck('id')->all();

        return $open ?: $this->targets->pluck('id')->all();
    }

    /**
     * Chia tự động: chọn / bỏ chọn tất cả.
     */
    public function toggleAll(): void
    {
        $ids = $this->selectableIds;

        $this->selected = array_diff($ids, $this->selected) ? $ids : [];
        $this->confirming = false;
        $this->resetValidation();
    }

    /**
     * Nút − / + của ô số lượng (chia thủ công).
     */
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

    /**
     * Số Lead sắp nhận của từng đối tượng theo phương thức hiện tại (để vẽ thanh tải + tóm tắt).
     *
     * @return array<string, int>
     */
    #[Computed]
    public function incoming(): array
    {
        return $this->mode === 'auto' ? $this->preview : $this->manualPlan();
    }

    /**
     * Salesperson sẽ vượt ngưỡng 50 theo kế hoạch hiện tại (đội không áp ngưỡng):
     * [employee_id => ['name', 'load', 'add', 'after', 'over']].
     */
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

    /* ── Dữ liệu ──────────────────────────────────────────────────────── */

    #[Computed]
    public function branches(): Collection
    {
        return $this->forTeams ? Branch::query()->orderBy('branch_id')->get(['branch_id', 'branch_name']) : collect();
    }

    /**
     * Đội của Sale Leader đang đăng nhập.
     */
    #[Computed]
    public function team(): ?SalesTeam
    {
        $employee = Auth::user()->employee;

        return $employee?->ledTeam ?? $employee?->team;
    }

    #[Computed]
    public function leaderId(): ?string
    {
        return $this->team?->team_leader_id;
    }

    /**
     * Đối tượng nhận Lead: [['id', 'name', 'sub', 'owner' (người giữ Lead), 'load', 'capacity', 'perf'], ...].
     */
    #[Computed]
    public function targets(): Collection
    {
        $service = app(LeadDistributionService::class);

        if ($this->forTeams) {
            if (! $this->branches->contains('branch_id', $this->branch)) {
                return collect();
            }

            // Đội có trưởng nhóm đang làm việc thuộc chi nhánh đã chọn (Lead của đội do trưởng nhóm giữ)
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

    /**
     * Nhóm được xếp hạng: chia tự động chỉ xếp hạng trong các đối tượng được chọn
     * (Lead dư chỉ chia cho Top 2 của nhóm này), chia thủ công thì xếp hạng cả đội.
     *
     * @return string[]
     */
    protected function rankingIds(): array
    {
        $ids = $this->targets->pluck('id')->all();

        return $this->mode === 'auto'
            ? array_values(array_intersect($ids, $this->selected))
            : $ids;
    }

    /**
     * Hạng theo từng tiêu chí + tổng hạng của nhóm được xếp hạng (xem LeadDistributionService::scoreboard).
     */
    #[Computed]
    public function scoreboard(): array
    {
        return app(LeadDistributionService::class)->scoreboard($this->rankingIds(), $this->targets->pluck('perf', 'id')->all());
    }

    /**
     * Thứ hạng hiệu suất chung (theo tổng hạng): [id => hạng].
     */
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

    /**
     * Số Lead khả dụng theo tiêu chí đã chọn.
     */
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

    /**
     * Gợi ý trong số Lead khả dụng: khách cũ, Lead có người thân (dùng chung SĐT / email) đã có người phụ trách,
     * Lead có người thân cùng chờ phân: ['returning', 'family', 'batch' => số Lead,
     * 'by' => [owner (trưởng nhóm | salesperson) => số Lead được ưu tiên giao cho người đó]].
     */
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
        // Chỉ tính gợi ý trỏ tới đối tượng đang có trong danh sách nhận (VD người thân đang chờ ở trưởng nhóm thì bỏ qua)
        $targetIds = $this->targets->pluck('owner')->all();
        $owners = array_filter($owners, fn ($owner) => in_array($owner[$key], $targetIds, true));

        return [
            'returning' => count(array_filter($owners, fn ($o) => $o['reason'] === 'returning')),
            'family'    => count(array_filter($owners, fn ($o) => $o['reason'] === 'family')),
            // Lead có người thân cũng nằm trong danh sách chờ phân → sẽ được giao chung
            'batch'     => count($service->familiesIn($leadIds, $related)),
            'by'        => array_count_values(array_column($owners, $key)),
        ];
    }

    /**
     * Id được chọn hợp lệ (chia tự động), theo thứ hạng hiệu suất.
     */
    protected function selectedRanked(): array
    {
        $valid = array_values(array_intersect($this->targets->pluck('id')->all(), $this->selected));

        return app(LeadDistributionService::class)->rank(
            $valid,
            $this->targets->pluck('perf', 'id')->all(),
            $this->load,
        );
    }

    /**
     * Lead dư của chia tự động: remainder = phần dư khi chia đều, over = phần vượt ngưỡng 50 (chỉ Salesperson).
     */
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

    /**
     * Cần xếp hạng hiệu suất: chia tự động có Lead dư (phần dư chia đều / phần vượt ngưỡng → Top 2).
     */
    #[Computed]
    public function needsRanking(): bool
    {
        return array_sum($this->leftover) > 0;
    }

    /**
     * Kết quả dự kiến của chia tự động: [id => n].
     */
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

    /**
     * Chia thủ công: [id => số Lead] chỉ gồm đối tượng hợp lệ, số nguyên >= 0.
     */
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

    /* ── Phân công ────────────────────────────────────────────────────── */

    public function submit(LeadDistributionService $service): void
    {
        $this->process($service, false);
    }

    /**
     * "Vẫn phân" trong hộp xác nhận vượt ngưỡng.
     */
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

            // Chia đều, phần dư / phần vượt ngưỡng → Top 2 hiệu suất (xem capAuto)
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

        // Có Salesperson vượt ngưỡng 50 → hỏi xác nhận trước (ngưỡng chỉ là cảnh báo, không áp cho đội)
        if (! $confirmed && $this->overLimit) {
            $this->confirming = true;

            return;
        }

        $this->commit($service, $plan);
    }

    /**
     * Ghi kết quả, đóng popup và báo về trang danh sách.
     *
     * @param  array  $plan  [team_id | employee_id => số Lead]
     */
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
