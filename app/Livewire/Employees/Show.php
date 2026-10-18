<?php

namespace App\Livewire\Employees;

use App\Models\Employee;
use App\Models\Role;
use App\Models\SalesTeam;
use App\Services\SalesPerformanceReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Chi tiết một nhân viên (chỉ xem): thông tin cá nhân, công việc; riêng Sale Leader / Salesperson
 * có thêm đội kinh doanh và kết quả kinh doanh (Giám đốc, Sale Admin, Kế toán không thuộc đội nào).
 * Sale Leader chỉ xem được nhân viên trong đội của mình.
 *
 * Kết quả kinh doanh dùng chung cách tính với báo cáo Phân tích hiệu quả Sales (SalesPerformanceReport):
 *   - Sale Leader: kết quả của cả đội do người này phụ trách, so với chỉ tiêu tháng của đội.
 *   - Salesperson: kết quả của riêng người đó.
 * Chỉ hiện 4 chỉ số tổng quan; xem chi tiết ở trang báo cáo (reportUrl).
 */
#[Layout('layouts.app')]
class Show extends Component
{
    // Kỳ xem: 'all' = toàn bộ thời gian; không chọn / giá trị lạ = tháng hiện tại
    const ALL_TIME = 'all';

    // Số tháng trong danh sách chọn kỳ (tính lùi từ tháng hiện tại)
    const MONTHS = 12;

    public Employee $employee;

    // Kỳ của khối Kết quả kinh doanh: 'Y-m' hoặc 'all'
    #[Url(except: '')]
    public string $month = '';

    public function mount(Employee $employee): void
    {
        abort_unless(
            Employee::query()->visibleTo(Auth::user()->employee)->whereKey($employee->getKey())->exists(),
            403
        );

        $this->employee = $employee->load(['role', 'team.leader', 'branch', 'ledTeam']);
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * Có trang Đội kinh doanh (Giám đốc, Sale Admin).
     */
    #[Computed]
    public function canViewTeams(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN], true);
    }

    /**
     * Nhân viên kinh doanh (Sale Leader, Salesperson) thì hiện khối kết quả kinh doanh.
     */
    #[Computed]
    public function isSales(): bool
    {
        return $this->employee->hasRole(Role::SALE_LEADER, Role::SALESPERSON);
    }

    /**
     * Thành viên cùng đội (đang làm việc, trừ chính nhân viên này).
     */
    #[Computed]
    public function teammates()
    {
        if (! $this->employee->team_id) {
            return collect();
        }

        return Employee::query()
            ->with('role')
            ->where('team_id', $this->employee->team_id)
            ->whereKeyNot($this->employee->getKey())
            ->state(Employee::STATE_WORKING)
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Mã các thành viên cùng đội mà người đang đăng nhập được mở chi tiết
     * (VD: Sale Leader không mở được trang của chính mình) → chỉ những người này mới có link.
     */
    #[Computed]
    public function linkableIds(): array
    {
        return Employee::query()
            ->visibleTo(Auth::user()->employee)
            ->whereKey($this->teammates->modelKeys())
            ->pluck('employee_id')
            ->all();
    }

    /* ── Kết quả kinh doanh ───────────────────────────────────────────── */

    /**
     * Đội do Sale Leader này phụ trách (SalesTeams.team_leader_id); null với Salesperson
     * hoặc Sale Leader chưa được gắn đội.
     */
    #[Computed]
    public function ledTeam(): ?SalesTeam
    {
        return $this->employee->hasRole(Role::SALE_LEADER) ? $this->employee->ledTeam : null;
    }

    /**
     * Danh sách kỳ: 12 tháng gần nhất (mới nhất trước) + Toàn bộ thời gian.
     */
    #[Computed]
    public function months(): array
    {
        $current = CarbonImmutable::today()->startOfMonth();

        return array_map(fn ($i) => $current->subMonths($i)->format('Y-m'), range(0, self::MONTHS - 1));
    }

    /**
     * Kỳ đang xem sau khi kiểm tra (giá trị lạ từ URL coi như tháng hiện tại).
     */
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

    /**
     * Có số liệu để tính không: Salesperson luôn có; Sale Leader cần đang phụ trách một đội.
     */
    #[Computed]
    public function hasPerformance(): bool
    {
        return $this->employee->hasRole(Role::SALESPERSON) || $this->ledTeam !== null;
    }

    /**
     * Tổng kết quả trong kỳ: của cả đội (Sale Leader) hoặc của riêng nhân viên (Salesperson).
     */
    #[Computed]
    public function performance(): array
    {
        return $this->report()->totals($this->filters($this->period));
    }

    /**
     * Tổng kết quả tháng trước (để so sánh); null khi xem Toàn bộ thời gian.
     */
    #[Computed]
    public function previousPerformance(): ?array
    {
        if ($this->period === self::ALL_TIME) {
            return null;
        }

        $previous = CarbonImmutable::parse($this->period.'-01')->subMonth()->format('Y-m');

        return $this->report()->totals($this->filters($previous));
    }

    /**
     * Chỉ tiêu tháng của đội (doanh thu, học viên mới, Lead mới) – chỉ với Sale Leader, khi xem theo tháng.
     */
    #[Computed]
    public function target(): array
    {
        if (! $this->ledTeam || $this->period === self::ALL_TIME) {
            return [];
        }

        return $this->report()->targets($this->period, [$this->ledTeam->team_id])[$this->ledTeam->team_id] ?? [];
    }

    /**
     * Link sang báo cáo Phân tích hiệu quả Sales cùng đội / nhân viên + tháng (nếu vai trò có trang báo cáo).
     */
    #[Computed]
    public function reportUrl(): ?string
    {
        $route = $this->routePrefix.'.reports.sales-performance';

        if (! Route::has($route) || ! $this->hasPerformance) {
            return null;
        }

        return route($route, array_filter([
            'month'    => $this->period === self::ALL_TIME ? null : $this->period,
            'team'     => $this->ledTeam?->team_id ?? $this->employee->team_id,
            'employee' => $this->ledTeam ? null : $this->employee->employee_id,
        ]));
    }

    public function render()
    {
        return view('livewire.employees.show')
            ->title(__('employees.detail_title', ['name' => $this->employee->full_name]));
    }

    /**
     * Bộ lọc cho SalesPerformanceReport: theo đội (Sale Leader) hoặc theo nhân viên (Salesperson) + kỳ.
     */
    private function filters(string $period): array
    {
        $range = $period === self::ALL_TIME ? [] : [
            'from' => ($start = CarbonImmutable::parse($period.'-01'))->toDateString(),
            'to'   => $start->endOfMonth()->toDateString(),
        ];

        return $range + ($this->ledTeam
            ? ['team' => $this->ledTeam->team_id]
            : ['employee' => $this->employee->employee_id]);
    }

    private function report(): SalesPerformanceReport
    {
        return app(SalesPerformanceReport::class);
    }
}
