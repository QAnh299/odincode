<?php

namespace App\Livewire\Opportunities;

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
 */
#[Layout('layouts.app')]
class Kanban extends Component
{
    const PRESETS = ['today', 'yesterday', 'last7', 'month'];

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
        $this->reset('from', 'to', 'team', 'employee');
    }

    public function hasFilters(): bool
    {
        return $this->from !== '' || $this->to !== '' || $this->team !== '' || $this->employee !== '';
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
        // Opportunity đã thanh toán đủ và trở thành học viên không còn trên Kanban - xem ở Chi tiết học viên
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
            // Stage Chốt: cơ hội chưa có báo giá cần salesperson chú ý
            ->withCount('quotations')
            ->orderByDesc('conversion_date')
            ->get();

        // Stage Chốt: cơ hội chưa có báo giá lên đầu cột (giữ thứ tự ngày chuyển đổi trong từng nhóm)
        $columns = $opportunities->groupBy('stage_id')->map(fn ($cards, $stageId) => $stageId === Stage::CLOSED
            ? $cards->sortBy(fn (Opportunity $opp) => $opp->needsQuotation() ? 0 : 1)->values()
            : $cards);

        return view('livewire.opportunities.kanban', [
            'columns' => $columns,
            'total'   => $opportunities->count(),
        ])->title(__('opportunities.title'));
    }
}
