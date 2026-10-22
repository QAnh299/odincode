<?php

namespace App\Livewire\Opportunities;

use App\Models\Appointment;
use App\Models\Employee;
use App\Models\Opportunity;
use App\Models\Quotation;
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

/**
 * Quản lý Opportunity dạng Kanban (giống Odoo CRM): mỗi cột là một Stage (theo sort_order).
 * Dùng chung cho Salesperson, Sale Leader, Giám đốc - phạm vi theo Opportunity::visibleTo():
 *   - Salesperson: cơ hội do chính mình phụ trách.
 *   - Sale Leader: cơ hội của đội mình; lọc được theo salesperson trong đội.
 *   - Giám đốc: tất cả; lọc theo đội, chọn đội rồi mới lọc được theo salesperson của đội đó.
 * Leader / Giám đốc thấy tên người phụ trách trên thẻ.
 * Không hiển thị trạng thái vì mỗi stage đã tương ứng một trạng thái.
 *
 * Bộ lọc theo ngày chuyển đổi (ngày Lead được chia cho salesperson):
 * nút chọn nhanh (Hôm nay, Hôm qua, 7 ngày qua, Tháng này) chỉ điền sẵn Từ ngày - Đến ngày,
 * nút đang sáng được suy ra từ khoảng ngày nên không cần lưu thêm trạng thái.
 * Hiện chỉ xem; chưa kéo thả đổi stage.
 *
 * Gọn khi có nhiều cơ hội: việc cần xử lý lên đầu cột (biểu tượng góc thẻ, bộ lọc "Cần xử lý"),
 * mỗi cột hiện PAGE_SIZE thẻ rồi "Xem thêm", cột Lưu trữ mặc định thu gọn.
 */
#[Layout('layouts.app')]
class Kanban extends Component
{
    const PRESETS = ['today', 'yesterday', 'last7', 'month'];

    // Số thẻ hiện mỗi lần trong một cột; "Xem thêm" tải thêm chừng ấy
    const PAGE_SIZE = 20;

    // Data đã xử lý: còn không quá số ngày này đến hạn quyết định thì coi là cần xử lý
    const DECISION_WARN_DAYS = 3;

    // Việc cần xử lý, theo mức ưu tiên (nhỏ = gấp hơn): thẻ lên đầu cột, có biểu tượng góc phải trên
    const URGENCY = [
        'overdue_appointment' => 0, // lịch hẹn đã qua giờ, chưa cập nhật kết quả
        'needs_quotation'     => 1, // stage Chốt chưa có báo giá
        'decision_due'        => 2, // Data đã xử lý sắp hết hạn quyết định
        'not_contacted'       => 3, // Data chưa tương tác, chưa liên hệ lần nào
    ];

    // Chỉ hiện cơ hội cần xử lý
    #[Url(except: false)]
    public bool $urgent = false;

    // Cột Lưu trữ mặc định thu gọn
    public bool $showArchived = false;

    // Số thẻ đang hiện của từng cột: [stage_id => số thẻ]
    public array $shown = [];

    // Khoảng ngày chuyển đổi (Y-m-d) - có thể bị sửa từ client nên luôn kiểm tra qua validDate()
    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    // Đội (Giám đốc) - '' = Tất cả đội; luôn đọc qua activeTeam
    #[Url(except: '')]
    public string $team = '';

    // Salesperson (Sale Leader, Giám đốc) - '' = Tất cả; chỉ áp dụng khi có trong employeeOptions
    #[Url(except: '')]
    public string $employee = '';

    // Đổi đội thì bỏ chọn salesperson (danh sách salesperson phụ thuộc đội)
    public function updatedTeam(): void
    {
        $this->employee = '';
    }

    /**
     * Bấm nút chọn nhanh: điền Từ ngày - Đến ngày; '' = Tất cả.
     */
    public function applyPreset(string $preset): void
    {
        [$this->from, $this->to] = $this->presetRange($preset) ?? ['', ''];
    }

    public function resetFilters(): void
    {
        $this->reset('from', 'to', 'team', 'employee', 'urgent', 'shown');
    }

    public function hasFilters(): bool
    {
        return $this->from !== '' || $this->to !== '' || $this->team !== '' || $this->employee !== '' || $this->urgent;
    }

    // Đổi bộ lọc thì mỗi cột hiện lại từ đầu
    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'to', 'team', 'employee'], true)) {
            $this->shown = [];
        }
    }

    public function toggleUrgent(): void
    {
        $this->urgent = ! $this->urgent;
        $this->shown = [];
    }

    public function toggleArchived(): void
    {
        $this->showArchived = ! $this->showArchived;
    }

    public function showMore(string $stageId): void
    {
        $this->shown[$stageId] = $this->limitFor($stageId) + self::PAGE_SIZE;
    }

    /**
     * Số thẻ hiện trong một cột (ít nhất PAGE_SIZE).
     */
    public function limitFor(string $stageId): int
    {
        return max(self::PAGE_SIZE, (int) ($this->shown[$stageId] ?? 0));
    }

    // ── Phạm vi theo vai trò ──────────────────────────────────────────

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

    #[Computed]
    public function canFilterTeam(): bool
    {
        return $this->routePrefix === Role::DIRECTOR;
    }

    #[Computed]
    public function teams(): Collection
    {
        return $this->canFilterTeam
            ? SalesTeam::query()->orderBy('team_name')->get(['team_id', 'team_name'])
            : collect();
    }

    /**
     * Đội đang xem: Giám đốc = đội đã chọn; Sale Leader = đội của mình.
     */
    #[Computed]
    public function activeTeam(): ?SalesTeam
    {
        return match ($this->routePrefix) {
            Role::DIRECTOR    => $this->teams->firstWhere('team_id', $this->team),
            Role::SALE_LEADER => $this->me?->team_id ? SalesTeam::find($this->me->team_id) : null,
            default           => null,
        };
    }

    /**
     * Salesperson của đội đang xem (cho bộ lọc).
     */
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

    /**
     * Nút chọn nhanh đang khớp với khoảng ngày hiện tại ('' = Tất cả, null = khoảng tuỳ chọn).
     */
    public function activePreset(): ?string
    {
        if (! $this->hasFilters()) {
            return '';
        }

        foreach (self::PRESETS as $preset) {
            if ($this->presetRange($preset) === [$this->from, $this->to]) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * Khoảng ngày [từ, đến] (Y-m-d) của một nút chọn nhanh.
     */
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
        // Opportunity đã thanh toán (lần đầu) và trở thành học viên không còn trên Kanban - xem ở Chi tiết học viên
        $opportunities = Opportunity::query()
            ->visibleTo($this->me)
            ->assigned()
            ->whereDoesntHave('student')
            ->when($this->activeTeam && $this->canFilterTeam,
                fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('team_id', $this->activeTeam->team_id)))
            ->when($this->employeeOptions->contains('employee_id', $this->employee),
                fn ($q) => $q->where('employee_id', $this->employee))
            ->when($this->validDate($this->from), fn ($q) => $q->whereDate('conversion_date', '>=', $this->from))
            ->when($this->validDate($this->to), fn ($q) => $q->whereDate('conversion_date', '<=', $this->to))
            ->with(['lead:lead_id,full_name,phone,email,source_name,contact_method', 'employee:employee_id,full_name'])
            // Nhãn "Phân vân · còn N ngày" (HĐ11): hạn của báo giá Nháp còn hạn, nạp sẵn trong cùng truy vấn
            ->withMin(['quotations as draft_expiry' => fn ($q) => $q
                ->where('status', Quotation::STATUS_DRAFT)
                ->whereDate('expiry_date', '>=', today()->toDateString())], 'expiry_date')
            // Việc cần xử lý: số báo giá, lịch hẹn đã qua giờ chưa cập nhật, số lần liên hệ
            ->withCount([
                'quotations',
                'appointments as overdue_appointments' => fn ($q) => $q
                    ->where('status', Appointment::STATUS_SCHEDULED)
                    ->where('scheduled_time', '<=', now()),
                'careResults',
            ])
            ->orderByDesc('conversion_date')
            ->get();

        $this->markUrgency($opportunities);
        $urgentCount = $opportunities->whereNotNull('urgency')->count();

        if ($this->urgent) {
            $opportunities = $opportunities->whereNotNull('urgency')->values();
        }

        // Trong mỗi cột: việc gấp lên đầu (cùng mức thì sắp hạn gần hơn trước), còn lại giữ thứ tự ngày chuyển đổi
        $columns = $opportunities->groupBy('stage_id')->map(fn (Collection $cards) => $cards
            ->sortBy(fn (Opportunity $opp) => [self::URGENCY[$opp->urgency] ?? 99, $opp->urgency_days ?? 0])
            ->values());

        return view('livewire.opportunities.kanban', [
            'columns'     => $columns,
            'total'       => $opportunities->count(),
            'urgentCount' => $urgentCount,
        ])->title(__('opportunities.title'));
    }

    /**
     * Gắn việc cần xử lý gấp nhất cho từng cơ hội đang xử lý: urgency (khóa của URGENCY, null nếu không có)
     * và urgency_days (số ngày còn lại của hạn quyết định).
     */
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
                $opp->stage_id === Stage::UNTOUCHED && $opp->care_results_count === 0             => 'not_contacted',
                default                                                                           => null,
            };
            $opp->urgency_days = $opp->urgency === 'decision_due' ? $days : null;
        }
    }
}
