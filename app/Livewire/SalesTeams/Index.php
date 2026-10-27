<?php

namespace App\Livewire\SalesTeams;

use App\Livewire\SalesTeams\Concerns\ManagesTeamKpi;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\SalesTeam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Danh sách đội kinh doanh + tra cứu (tìm theo mã / tên đội / trưởng nhóm, sắp xếp).
 * Giám đốc: chỉ xem. Sale Admin: thêm Tạo đội và Chỉnh sửa đội (popup) – trong Chỉnh sửa đội
 * đổi được trưởng nhóm và gắn Salesperson vào đội. Mỗi nhân viên chỉ thuộc một đội (Employees.team_id),
 * và một đội chỉ thuộc một chi nhánh: mọi nhân viên trong đội phải cùng chi nhánh (SalesTeam::branchId).
 * Sale Admin còn đặt KPI hằng tháng cho từng đội (popup KPI ở cột Thao tác - ManagesTeamKpi).
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use ManagesTeamKpi, WithPagination;

    const PER_PAGE = 10;

    const SORTS = ['name', 'newest', 'oldest', 'members'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'name')]
    public string $sort = 'name';

    // Popup đang mở: '' | 'create' | 'edit' | 'kpi'
    public string $modal = '';

    // Đội đang sửa (popup edit, kpi)
    #[Locked]
    public ?string $teamId = null;

    // Dữ liệu popup: team_name, established_date, team_leader_id, members (mã Salesperson của đội – chỉ popup edit)
    public array $form = [];

    // Thông báo sau khi lưu: ['type' => success|danger, 'text' => ...]
    public ?array $notice = null;

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
     * Tạo / sửa đội (kèm trưởng nhóm, thành viên) - chỉ Sale Admin.
     */
    #[Computed]
    public function canManage(): bool
    {
        return $this->routePrefix === Role::SALE_ADMIN;
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

    /* ── Popup ────────────────────────────────────────────────────────── */

    /**
     * Đội đang sửa (popup edit); null khi tạo mới.
     */
    #[Computed]
    public function editingTeam(): ?SalesTeam
    {
        return $this->teamId ? SalesTeam::with('leader')->find($this->teamId) : null;
    }

    /**
     * Mã các Salesperson đang được đánh dấu trong popup (chỉ popup edit).
     */
    private function selectedMemberIds(): array
    {
        return $this->modal === 'edit' ? array_values(array_filter((array) ($this->form['members'] ?? []), 'is_string')) : [];
    }

    /**
     * Chi nhánh của các thành viên đang được đánh dấu (null nếu chưa chọn ai).
     */
    #[Computed]
    public function membersBranchId(): ?string
    {
        return $this->memberOptions->whereIn('employee_id', $this->selectedMemberIds())->first()?->branch_id;
    }

    /**
     * Chi nhánh của đội theo lựa chọn hiện tại trong popup: chi nhánh của trưởng nhóm đang chọn,
     * chưa chọn trưởng nhóm thì theo thành viên đang chọn; null = chưa xác định (chọn chi nhánh nào cũng được).
     */
    #[Computed]
    public function anchorBranchId(): ?string
    {
        $leaderId = (string) ($this->form['team_leader_id'] ?? '');

        return $this->leaderOptions->firstWhere('employee_id', $leaderId)?->branch_id ?? $this->membersBranchId;
    }

    #[Computed]
    public function anchorBranch(): ?Branch
    {
        return $this->anchorBranchId ? Branch::find($this->anchorBranchId) : null;
    }

    /**
     * Ứng viên trưởng nhóm: Sale Leader đang làm việc, chưa làm trưởng nhóm đội khác
     * (trưởng nhóm hiện tại của đội đang sửa vẫn được giữ trong danh sách)
     * và cùng chi nhánh với các thành viên đang được chọn (nếu có).
     */
    #[Computed]
    public function leaderOptions(): Collection
    {
        return Employee::query()
            ->with(['team', 'branch'])
            ->state(Employee::STATE_WORKING)
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALE_LEADER))
            ->whereDoesntHave('ledTeam', fn ($q) => $q->when($this->teamId, fn ($q) => $q->whereKeyNot($this->teamId)))
            ->when($this->membersBranchId, fn ($q, $branchId) => $q->where('branch_id', $branchId))
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Salesperson đang làm việc cho mục Thành viên (popup Chỉnh sửa đội):
     * thành viên của đội trước, rồi người chưa có đội, cuối cùng người đang ở đội khác (không chọn được).
     * Người khác chi nhánh với đội cũng không chọn được (xem canPickMember).
     */
    #[Computed]
    public function memberOptions(): Collection
    {
        if ($this->modal !== 'edit') {
            return collect();
        }

        return Employee::query()
            ->with(['team', 'branch'])
            ->state(Employee::STATE_WORKING)
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALESPERSON))
            ->get()
            ->sortBy(fn (Employee $e) => [
                match (true) {
                    $e->team_id === $this->teamId => 0,
                    $e->team_id === null          => 1,
                    default                       => 2,
                },
                $e->full_name,
            ])
            ->values();
    }

    /**
     * Salesperson có được chọn làm thành viên không: đang thuộc đội này hoặc chưa có đội,
     * và cùng chi nhánh với đội (nếu chi nhánh của đội đã xác định).
     */
    public function canPickMember(Employee $employee): bool
    {
        return ($employee->team_id === null || $employee->team_id === $this->teamId)
            && ($this->anchorBranchId === null || $employee->branch_id === $this->anchorBranchId);
    }

    private function assignableMemberIds(): array
    {
        return $this->memberOptions
            ->filter(fn (Employee $e) => $this->canPickMember($e))
            ->pluck('employee_id')
            ->all();
    }

    public function openCreate(): void
    {
        $this->authorizeManage();

        $this->teamId = null;
        $this->form = [
            'team_name'        => '',
            'established_date' => today()->toDateString(),
            'team_leader_id'   => '',
        ];
        $this->openModal('create');
    }

    public function openEdit(string $teamId): void
    {
        $this->authorizeManage();
        $team = SalesTeam::findOrFail($teamId);

        $this->teamId = $team->team_id;
        $this->form = [
            'team_name'        => $team->team_name,
            'established_date' => $team->established_date?->toDateString() ?? '',
            'team_leader_id'   => (string) $team->team_leader_id,
            'members'          => [],
        ];
        $this->openModal('edit');
        $this->form['members'] = $this->memberOptions
            ->where('team_id', $team->team_id)->pluck('employee_id')->all();
        $this->forgetOptions();
    }

    public function closeModal(): void
    {
        $this->modal = '';
        $this->teamId = null;
        $this->resetValidation();
    }

    public function dismissNotice(): void
    {
        $this->notice = null;
    }

    /**
     * Lưu popup đang mở: Tạo đội, Chỉnh sửa đội (tên, ngày thành lập, trưởng nhóm, thành viên) hoặc KPI đội.
     */
    public function save(): void
    {
        $this->authorizeManage();

        if ($this->modal === 'kpi') {
            $this->saveKpi();

            return;
        }

        abort_unless(in_array($this->modal, ['create', 'edit'], true), 400);
        abort_if($this->modal === 'edit' && ! $this->editingTeam, 404);

        $data = $this->validateForm();

        $team = DB::transaction(function () use ($data) {
            $team = $this->editingTeam ?? new SalesTeam;

            $team->fill([
                'team_name'        => $data['team_name'],
                'established_date' => $data['established_date'],
            ])->save();

            $this->assignLeader($team, $data['team_leader_id'] ?: null);

            if ($this->modal === 'edit') {
                $this->syncMembers($team, $data['members'] ?? []);
            }

            return $team;
        });

        $this->notice = [
            'type' => 'success',
            'text' => __('employees.teams.form.saved_'.$this->modal, ['name' => $team->team_name, 'id' => $team->team_id]),
        ];
        $this->closeModal();
        unset($this->summary);
        $this->forgetOptions();
    }

    public function render()
    {
        $search = trim($this->search);

        $teams = SalesTeam::query()
            ->with([
                'leader.branch',
                'employees' => fn ($q) => $q->with('branch')->state(Employee::STATE_WORKING)->orderBy('full_name'),
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

    /* ── Nội bộ ───────────────────────────────────────────────────────── */

    private function authorizeManage(): void
    {
        abort_unless($this->canManage, 403);
    }

    private function openModal(string $modal): void
    {
        $this->modal = $modal;
        unset($this->editingTeam);
        $this->forgetOptions();
        $this->resetValidation();
        $this->notice = null;
    }

    /**
     * Bỏ cache các danh sách chọn (phụ thuộc loại popup và lựa chọn trong form).
     */
    private function forgetOptions(): void
    {
        unset($this->memberOptions, $this->membersBranchId, $this->leaderOptions, $this->anchorBranchId, $this->anchorBranch);
    }

    /**
     * Kiểm tra dữ liệu popup (luôn kiểm tra lại phía server).
     */
    private function validateForm(): array
    {
        $data = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $this->form);

        $rules = [
            'team_name' => [
                'required', 'string', 'max:150',
                Rule::unique('SalesTeams', 'team_name')->ignore($this->teamId, 'team_id'),
            ],
            'established_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'team_leader_id'   => ['nullable', Rule::in($this->leaderOptions->pluck('employee_id')->all())],
        ];

        if ($this->modal === 'edit') {
            $rules['members'] = ['array'];
            $rules['members.*'] = ['string', 'distinct', Rule::in($this->assignableMemberIds())];
        }

        return Validator::make($data, $rules, [
            'team_name.unique'                 => __('employees.teams.form.name_taken'),
            'established_date.before_or_equal' => __('employees.teams.form.date_future'),
            'team_leader_id.in'                => __('employees.teams.form.leader_invalid'),
            'members.*.in'                     => __('employees.teams.form.member_invalid'),
            'members.*.distinct'               => __('employees.teams.form.member_invalid'),
        ], [
            'team_name'        => __('employees.teams.form.team_name'),
            'established_date' => __('employees.established_date'),
            'team_leader_id'   => __('employees.team_leader'),
        ])->after(function ($validator) use ($data) {
            // Trưởng nhóm + thành viên được chọn phải cùng một chi nhánh
            if ($this->modal !== 'edit' || $validator->errors()->isNotEmpty()) {
                return;
            }

            $branches = $this->memberOptions
                ->whereIn('employee_id', $data['members'] ?? [])
                ->pluck('branch_id')
                ->push($this->anchorBranchId)
                ->filter()
                ->unique();

            if ($branches->count() > 1) {
                $validator->errors()->add('members', __('employees.teams.form.member_mixed_branch'));
            }
        })->validate();
    }

    /**
     * Thành viên Salesperson của đội = đúng danh sách đã chọn: người bỏ chọn được gỡ khỏi đội,
     * người mới (chưa có đội) được gắn vào đội. Trưởng nhóm và người đã nghỉ việc không bị đụng tới.
     */
    private function syncMembers(SalesTeam $team, array $memberIds): void
    {
        $salespeople = fn () => Employee::query()
            ->state(Employee::STATE_WORKING)
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALESPERSON));

        $salespeople()->where('team_id', $team->team_id)
            ->whereNotIn('employee_id', $memberIds)
            ->update(['team_id' => null]);

        if ($memberIds) {
            // Chỉ nhận người chưa có đội (kiểm tra lại lúc ghi, tránh giành nhân viên của đội khác)
            $salespeople()->whereIn('employee_id', $memberIds)
                ->where(fn ($q) => $q->whereNull('team_id')->orWhere('team_id', $team->team_id))
                ->update(['team_id' => $team->team_id]);
        }
    }

    /**
     * Gắn trưởng nhóm cho đội: trưởng nhóm mới chuyển vào đội này;
     * trưởng nhóm cũ (nếu đổi người) được gỡ khỏi đội để có thể nhận đội khác.
     */
    private function assignLeader(SalesTeam $team, ?string $leaderId): void
    {
        $oldLeaderId = $team->team_leader_id;

        if ($oldLeaderId === $leaderId) {
            return;
        }

        if ($oldLeaderId) {
            Employee::query()->whereKey($oldLeaderId)->where('team_id', $team->team_id)->update(['team_id' => null]);
        }

        if ($leaderId) {
            Employee::query()->whereKey($leaderId)->update(['team_id' => $team->team_id]);
        }

        $team->update(['team_leader_id' => $leaderId]);
    }
}
