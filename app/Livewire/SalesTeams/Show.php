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

/**
 * Chi tiết một đội kinh doanh (chỉ xem): thông tin đội, kết quả kinh doanh của đội và thành viên.
 * Dùng cho Giám đốc và Sale Admin (route chỉ có ở 2 vai trò này).
 *
 * Kết quả kinh doanh dùng chung cách tính với báo cáo Phân tích hiệu quả Sales (SalesPerformanceReport),
 * so với chỉ tiêu tháng của đội; chỉ hiện 4 chỉ số tổng quan, xem chi tiết ở trang báo cáo (reportUrl).
 */
#[Layout('layouts.app')]
class Show extends Component
{
    // Kỳ xem: 'all' = toàn bộ thời gian; không chọn / giá trị lạ = tháng hiện tại
    const ALL_TIME = 'all';

    // Số tháng trong danh sách chọn kỳ (tính lùi từ tháng hiện tại)
    const MONTHS = 12;

    public SalesTeam $team;

    // Kỳ của khối Kết quả kinh doanh: 'Y-m' hoặc 'all'
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

    /**
     * Thành viên đang làm việc: trưởng nhóm trước, rồi theo tên.
     */
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

    /**
     * Chi nhánh của đội (suy ra từ trưởng nhóm / thành viên – SalesTeam::branchId).
     */
    #[Computed]
    public function branch(): ?Branch
    {
        return ($id = $this->team->branchId()) ? Branch::find($id) : null;
    }

    /* ── Kết quả kinh doanh ───────────────────────────────────────────── */

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
     * Tổng kết quả của đội trong kỳ.
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
     * Chỉ tiêu tháng của đội (doanh thu, học viên mới, Lead mới) – chỉ khi xem theo tháng.
     */
    #[Computed]
    public function target(): array
    {
        if ($this->period === self::ALL_TIME) {
            return [];
        }

        return $this->report()->targets($this->period, [$this->team->team_id])[$this->team->team_id] ?? [];
    }

    /**
     * Link sang báo cáo Phân tích hiệu quả Sales đã lọc sẵn đội + tháng (nếu vai trò có trang báo cáo).
     */
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

    /**
     * Bộ lọc cho SalesPerformanceReport: đội này + kỳ.
     */
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
