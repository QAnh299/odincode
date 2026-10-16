<?php

namespace App\Livewire\Leads;

use App\Models\Branch;
use App\Models\Employee;
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
 *   - Sale Leader: phân Lead đội đang giữ cho Salesperson trong đội (tối đa 50 Lead đang xử lý/người).
 * Chọn phương thức trước (dropdown) rồi mới hiện chi nhánh / nguồn / kênh liên hệ / danh sách nhận Lead.
 * Chia tự động: chia đều, chỉ khi có Lead dư mới hiện tiêu chí hiệu suất (tháng trước) để chọn Top 2.
 * Chia thủ công: bảng đội / salesperson - số lượng nhập tay.
 * Chia thủ công cho Salesperson mà có người vượt 50 → bước 'overflow' để phân lại phần vượt.
 */
class Distribute extends Component
{
    const MODES = ['auto', 'manual'];

    public bool $open = false;

    // '' = chưa chọn → chỉ hiện ô chọn phương thức
    public string $mode = '';

    // Tiêu chí xếp hạng hiệu suất (tháng trước)
    public string $criterion = 'revenue';

    // Chi nhánh của Lead (chỉ Sale Admin)
    public string $branch = '';

    // '' = Tất cả
    public string $source = '';

    public string $method = '';

    // Chia tự động: các id được chọn (team_id hoặc employee_id)
    public array $selected = [];

    // Chia thủ công: [id => số Lead]
    public array $amounts = [];

    // 'form' | 'overflow'
    #[Locked]
    public string $step = 'form';

    // Bước overflow: kế hoạch đã cắt về ngưỡng 50 và số Lead cần phân lại
    #[Locked]
    public array $cappedPlan = [];

    #[Locked]
    public int $overflow = 0;

    // Bước overflow: [employee_id => số Lead vượt nhận thêm]
    public array $overflowAmounts = [];

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

        $this->reset('mode', 'criterion', 'branch', 'source', 'method', 'selected', 'amounts');
        $this->backToForm();
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetValidation();
    }

    public function backToForm(): void
    {
        $this->reset('step', 'cappedPlan', 'overflow', 'overflowAmounts');
        $this->resetValidation();
    }

    public function updated(string $property): void
    {
        // Đổi tiêu chí lọc thì danh sách đội / Lead khả dụng thay đổi → bỏ lựa chọn không còn hợp lệ
        if (in_array($property, ['branch', 'mode'], true)) {
            $this->reset('selected', 'amounts');
        }
        if ($property === 'mode') {
            $this->backToForm();
        }
        $this->resetValidation();
    }

    /**
     * Chia tự động: chọn / bỏ chọn tất cả (bỏ qua salesperson đã đủ 50 Lead).
     */
    public function toggleAll(): void
    {
        $ids = $this->targets->filter(fn ($row) => $row['capacity'] !== 0)->pluck('id')->all();

        $this->selected = array_diff($ids, $this->selected) ? $ids : [];
        $this->resetValidation();
    }

    /**
     * Nút − / + của ô số lượng (chia thủ công, phân lại phần vượt).
     */
    public function adjust(string $field, string $id, int $delta): void
    {
        abort_unless(in_array($field, ['amounts', 'overflowAmounts'], true), 400);
        if (! $this->targets->contains('id', $id)) {
            return;
        }

        $value = max(0, (int) ($this->{$field}[$id] ?? 0) + $delta);
        if ($field === 'overflowAmounts') {
            $value = min($value, $this->overflowRoom[$id] ?? 0);
        }

        $this->{$field}[$id] = $value ?: '';
        $this->resetValidation();
    }

    /**
     * Số Lead sắp nhận của từng đối tượng theo bước / phương thức hiện tại (để vẽ thanh tải + tóm tắt).
     *
     * @return array<string, int>
     */
    #[Computed]
    public function incoming(): array
    {
        if ($this->step === 'overflow') {
            $plan = $this->cappedPlan;
            foreach ($this->overflowAmounts as $id => $n) {
                $plan[$id] = ($plan[$id] ?? 0) + max(0, (int) $n);
            }

            return $plan;
        }

        return $this->mode === 'auto' ? $this->preview['plan'] : $this->manualPlan();
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
            $teams = SalesTeam::query()
                ->whereHas('leader', fn ($q) => $q->where('branch_id', $this->branch)->where('status', Employee::STATUS_WORKING))
                ->with('leader:employee_id,full_name')
                ->orderBy('team_id')
                ->get();
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

        $people = Employee::query()
            ->where('team_id', $this->team->team_id)
            ->where('status', Employee::STATUS_WORKING)
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALESPERSON))
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'job_title']);
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
     * Thứ hạng hiệu suất: [id => hạng]. Chia tự động chỉ xếp hạng trong các đối tượng được chọn
     * (Lead dư chỉ chia cho Top 2 của nhóm này), bước phân lại thì xếp hạng cả đội.
     */
    #[Computed]
    public function ranks(): array
    {
        $ids = $this->step === 'form' && $this->mode === 'auto'
            ? $this->selectedRanked()
            : app(LeadDistributionService::class)->rank(
                $this->targets->pluck('id')->all(),
                $this->targets->pluck('perf', 'id')->all(),
                $this->criterion,
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
     * Id được chọn hợp lệ (chia tự động), theo thứ hạng hiệu suất.
     */
    protected function selectedRanked(): array
    {
        $valid = array_values(array_intersect($this->targets->pluck('id')->all(), $this->selected));

        return app(LeadDistributionService::class)->rank(
            $valid,
            $this->targets->pluck('perf', 'id')->all(),
            $this->criterion,
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
     * Cần xếp hạng hiệu suất: chia tự động có Lead dư, hoặc bước phân lại phần vượt ngưỡng.
     */
    #[Computed]
    public function needsRanking(): bool
    {
        return $this->step === 'overflow' || array_sum($this->leftover) > 0;
    }

    /**
     * Kết quả dự kiến của chia tự động: ['plan' => [id => n], 'unplaced' => n].
     */
    #[Computed]
    public function preview(): array
    {
        if ($this->mode !== 'auto') {
            return ['plan' => [], 'unplaced' => 0];
        }

        $service = app(LeadDistributionService::class);
        $ranked = $this->selectedRanked();
        $plan = $service->splitEvenly($this->available, $ranked);

        if ($this->forTeams) {
            return ['plan' => $plan, 'unplaced' => 0];
        }

        [$plan, $unplaced] = $service->capAuto($plan, $this->load, $ranked);

        return ['plan' => $plan, 'unplaced' => $unplaced];
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

            $preview = $this->preview;
            $this->commit($service, $preview['plan'], $preview['unplaced']);

            return;
        }

        // Chia thủ công
        foreach ($this->amounts as $id => $value) {
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

        if (! $this->forTeams) {
            [$capped, $over] = $service->excess($plan, $this->load);

            if ($over > 0) {
                $this->cappedPlan = $capped;
                $this->overflow = $over;
                $this->overflowAmounts = [];
                $this->step = 'overflow';

                return;
            }
        }

        $this->commit($service, $plan);
    }

    /**
     * Còn nhận được thêm bao nhiêu ở bước phân lại phần vượt: [employee_id => n].
     */
    #[Computed]
    public function overflowRoom(): array
    {
        $service = app(LeadDistributionService::class);
        $room = [];
        foreach ($this->targets->pluck('id') as $id) {
            $room[$id] = max(0, $service->capacity($id, $this->load) - ($this->cappedPlan[$id] ?? 0));
        }

        return $room;
    }

    /**
     * Số Lead vượt phải phân lại (không thể vượt quá tổng chỗ còn trống của đội).
     */
    #[Computed]
    public function overflowRequired(): int
    {
        return min($this->overflow, array_sum($this->overflowRoom));
    }

    public function confirmOverflow(LeadDistributionService $service): void
    {
        $this->authorizeAccess();
        abort_unless($this->step === 'overflow' && ! $this->forTeams, 403);
        $this->resetValidation();

        $plan = $this->cappedPlan;
        $room = $this->overflowRoom;
        $extra = 0;

        foreach ($room as $id => $max) {
            $value = $this->overflowAmounts[$id] ?? '';
            if ($value === '' || $value === null) {
                continue;
            }
            if (! is_numeric($value) || (int) $value != $value || (int) $value < 0) {
                $this->addError('overflow', __('leads.distribute.amount_invalid'));

                return;
            }
            if ((int) $value > $max) {
                $this->addError('overflow', __('leads.distribute.over_capacity', ['max' => $service::MAX_PER_SALESPERSON]));

                return;
            }
            $plan[$id] = ($plan[$id] ?? 0) + (int) $value;
            $extra += (int) $value;
        }

        if ($extra !== $this->overflowRequired) {
            $this->addError('overflow', __('leads.distribute.overflow_mismatch', ['count' => $this->overflowRequired]));

            return;
        }

        $this->commit($service, $plan, $this->overflow - $extra);
    }

    /**
     * Ghi kết quả, đóng popup và báo về trang danh sách.
     *
     * @param  array  $plan  [team_id | employee_id => số Lead]
     */
    protected function commit(LeadDistributionService $service, array $plan, int $unplaced = 0): void
    {
        $owners = $this->targets->pluck('owner', 'id')->all();
        $byOwner = [];
        foreach (array_filter($plan) as $id => $count) {
            $byOwner[$owners[$id]] = $count;
        }

        if (! $byOwner) {
            $this->addError($this->step === 'overflow' ? 'overflow' : 'targets', __('leads.distribute.nothing'));

            return;
        }

        try {
            $count = $this->forTeams
                ? $service->assignToTeams($byOwner, $this->branch, $this->source(), $this->method())
                : $service->assignToSalespersons($this->leaderId, $byOwner, $this->source(), $this->method());
        } catch (RuntimeException $e) {
            $this->backToForm();
            $this->addError('targets', $e->getMessage());

            return;
        }

        $receivers = count($byOwner);
        $text = __($this->forTeams ? 'leads.distribute.done_team' : 'leads.distribute.done_person', [
            'count' => $count, 'receivers' => $receivers,
        ]);
        if ($unplaced > 0) {
            $text .= ' '.__('leads.distribute.unplaced', ['count' => $unplaced, 'max' => $service::MAX_PER_SALESPERSON]);
        }

        $this->open = false;
        $this->backToForm();
        $this->dispatch('leads-distributed', notice: ['type' => $unplaced > 0 ? 'warning' : 'success', 'text' => $text]);
    }

    public function render()
    {
        return view('livewire.leads.distribute', [
            'month' => app(LeadDistributionService::class)->previousMonth()[2],
        ]);
    }
}
