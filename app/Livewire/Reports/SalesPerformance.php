<?php

namespace App\Livewire\Reports;

use App\Models\Employee;
use App\Models\Role;
use App\Models\SalesTeam;
use App\Services\SalesPerformanceReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard & Báo cáo → Phân tích hiệu quả Sales Team / Salesperson (Giám đốc, Sale Admin, Sale Leader).
 *
 * Phạm vi: Giám đốc / Sale Admin xem tất cả, lọc theo đội rồi theo salesperson;
 *          Sale Leader chỉ xem đội của mình, lọc theo salesperson trong đội.
 * Gồm: thẻ KPI, biểu đồ theo salesperson (xử lý Lead, tỷ lệ, doanh thu, liên hệ), phễu chuyển đổi
 *      theo stage, xu hướng theo tháng, bảng xếp hạng salesperson, bảng tỷ lệ chuyển stage, bảng so sánh đội.
 * Định nghĩa từng chỉ số: xem App\Services\SalesPerformanceReport.
 */
#[Layout('layouts.app')]
class SalesPerformance extends Component
{
    // Bảng màu phân loại (giống báo cáo nguồn Lead); màu đi theo chỉ số, không theo thứ hạng
    const PALETTE = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];

    const METRIC_COLORS = [
        'assigned'    => '#2a78d6',
        'processed'   => '#eda100',
        'moved'       => '#6250d6',
        'won'         => '#1baf7a',
        'revenue'     => '#2a78d6',
        'paid'        => '#1baf7a',
        'contacts'    => '#2a78d6',
        'unreachable' => '#eb6834',
        'backward'    => '#e34948',
        'processed_rate' => '#eda100',
        'win_rate'       => '#1baf7a',
        'close_rate'     => '#6250d6',
    ];

    /* ── Bộ lọc ───────────────────────────────────────────────────────── */

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    // Đội (Giám đốc, Sale Admin) – '' = Tất cả đội
    #[Url(except: '')]
    public string $team = '';

    // Salesperson – '' = Tất cả; chỉ áp dụng khi có trong employeeOptions
    #[Url(except: '')]
    public string $employee = '';

    const FILTERS = ['from', 'to', 'team', 'employee'];

    public function updatedTeam(): void
    {
        $this->employee = '';
    }

    public function resetFilters(): void
    {
        $this->reset(...self::FILTERS);
    }

    public function hasFilters(): bool
    {
        foreach (self::FILTERS as $filter) {
            if ($this->$filter !== '') {
                return true;
            }
        }

        return false;
    }

    /* ── Phạm vi theo vai trò ─────────────────────────────────────────── */

    #[Computed]
    public function me(): ?Employee
    {
        return Auth::user()->employee;
    }

    #[Computed]
    public function canFilterTeam(): bool
    {
        return in_array(Auth::user()->role_name, [Role::DIRECTOR, Role::SALE_ADMIN], true);
    }

    #[Computed]
    public function teams(): Collection
    {
        return SalesTeam::query()->orderBy('team_name')->get(['team_id', 'team_name']);
    }

    /**
     * Đội đang xem: Giám đốc / Sale Admin = đội đã chọn ('' = tất cả); Sale Leader = đội của mình
     * (không thuộc đội nào → null và không thấy dữ liệu).
     */
    #[Computed]
    public function teamScope(): ?string
    {
        if ($this->canFilterTeam) {
            return $this->teams->contains('team_id', $this->team) ? $this->team : '';
        }

        return $this->me?->team_id;
    }

    /**
     * Salesperson trong phạm vi đang xem (cho bộ lọc và để hiện cả người chưa được giao Lead).
     */
    #[Computed]
    public function employeeOptions(): Collection
    {
        if ($this->teamScope === null) {
            return collect();
        }

        return Employee::query()
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALESPERSON))
            ->when($this->teamScope !== '', fn ($q) => $q->where('team_id', $this->teamScope))
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'team_id', 'status']);
    }

    /* ── Số liệu ──────────────────────────────────────────────────────── */

    #[Computed]
    public function totals(): array
    {
        return $this->report()->totals($this->filterValues());
    }

    /**
     * Chỉ số theo salesperson; người trong phạm vi chưa được giao Lead vẫn hiện (toàn số 0).
     * Sắp theo số Lead chốt, rồi doanh thu, rồi số Lead được giao.
     */
    #[Computed]
    public function byEmployee(): array
    {
        $filters = $this->filterValues();
        $rows = collect($this->report()->aggregate($filters, 'employee'))->keyBy('dim');

        if (! $filters['employee']) {
            foreach ($this->employeeOptions as $option) {
                if (! $rows->has($option->employee_id) && $option->isActive()) {
                    $rows->put($option->employee_id, $this->report()->derive(['dim' => $option->employee_id]));
                }
            }
        }

        $names = Employee::query()->whereIn('employee_id', $rows->keys())
            ->get(['employee_id', 'full_name', 'team_id'])->keyBy('employee_id');
        $teams = $this->teams->pluck('team_name', 'team_id');

        return $rows
            ->map(fn ($row, $id) => $row + [
                'name' => $names[$id]->full_name ?? $id,
                'team' => $teams[$names[$id]->team_id ?? ''] ?? null,
            ])
            ->sort(fn ($a, $b) => [$b['won'], $b['revenue'], $b['assigned']] <=> [$a['won'], $a['revenue'], $a['assigned']])
            ->values()
            ->all();
    }

    /**
     * So sánh giữa các đội – chỉ khi Giám đốc / Sale Admin xem tất cả đội.
     */
    #[Computed]
    public function byTeam(): array
    {
        if (! $this->canFilterTeam || $this->teamScope !== '' || $this->employee !== '') {
            return [];
        }

        $teams = $this->teams->pluck('team_name', 'team_id');
        $rows = $this->report()->aggregate($this->filterValues(), 'team');

        foreach ($rows as &$row) {
            $row['name'] = $teams[$row['dim']] ?? __('reports.sales_performance.no_team');
        }
        usort($rows, fn ($a, $b) => $b['revenue'] <=> $a['revenue'] ?: $b['won'] <=> $a['won']);

        return $rows;
    }

    #[Computed]
    public function stages(): array
    {
        return $this->report()->funnelStages();
    }

    #[Computed]
    public function charts(): array
    {
        $rows = array_values(array_filter($this->byEmployee, fn ($r) => $r['assigned'] > 0));
        $names = array_column($rows, 'name');
        $series = fn (array $metrics) => array_map(fn ($m) => [
            'label' => __('reports.sales_performance.metric.'.$m),
            'color' => self::METRIC_COLORS[$m],
            'data'  => array_column($rows, $m),
        ], $metrics);

        // 1. Xử lý Lead theo salesperson: được giao → đã xử lý → chuyển stage → chốt
        $pipeline = ['type' => 'bar', 'unit' => 'count', 'labels' => $names,
            'series' => $series(['assigned', 'processed', 'moved', 'won'])];

        // 2. Tỷ lệ theo salesperson (%)
        $rates = ['type' => 'bar', 'unit' => 'percent', 'labels' => $names,
            'series' => $series(['processed_rate', 'win_rate', 'close_rate'])];

        // 3. Doanh thu thực tế theo salesperson
        $revenue = ['type' => 'bar', 'unit' => 'money', 'labels' => $names,
            'series' => $series(['revenue', 'paid'])];

        // 4. Hoạt động liên hệ & chất lượng xử lý
        $contacts = ['type' => 'bar', 'unit' => 'count', 'labels' => $names,
            'series' => $series(['contacts', 'unreachable', 'backward'])];

        // 5. Phễu chuyển đổi theo stage (toàn phạm vi lọc)
        $t = $this->totals;
        $funnel = [
            'type'   => 'bar_h',
            'unit'   => 'count',
            'labels' => array_column($this->stages, 'name'),
            'series' => [[
                'label' => __('reports.sales_performance.metric.reached'),
                'color' => self::PALETTE[0],
                'data'  => array_map(fn ($s) => $t['stages'][$s['order']]['reached'] ?? 0, $this->stages),
            ]],
        ];

        // 6. Xu hướng theo tháng giao Lead
        $months = $this->report()->aggregate($this->filterValues(), 'month');
        usort($months, fn ($a, $b) => strcmp($a['dim'], $b['dim']));
        $trend = [
            'type'   => 'line',
            'unit'   => 'count',
            'labels' => array_map(fn ($m) => $this->monthLabel($m['dim']), $months),
            'series' => array_map(fn ($m) => [
                'label' => __('reports.sales_performance.metric.'.$m),
                'color' => self::METRIC_COLORS[$m],
                'data'  => array_column($months, $m),
            ], ['assigned', 'processed', 'won']),
        ];

        return compact('pipeline', 'rates', 'revenue', 'contacts', 'funnel', 'trend');
    }

    /* ── Hỗ trợ ───────────────────────────────────────────────────────── */

    protected function report(): SalesPerformanceReport
    {
        return app(SalesPerformanceReport::class);
    }

    /**
     * Bộ lọc đã kiểm tra (giá trị không hợp lệ bị bỏ qua). Sale Leader không có đội → không thấy gì.
     */
    protected function filterValues(): array
    {
        return [
            'from'     => $this->validDate($this->from) ? $this->from : null,
            'to'       => $this->validDate($this->to) ? $this->to : null,
            'team'     => $this->teamScope === null ? '__none__' : ($this->teamScope ?: null),
            'employee' => $this->employeeOptions->contains('employee_id', $this->employee) ? $this->employee : null,
        ];
    }

    protected function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }

    protected function monthLabel(string $value): string
    {
        return preg_match('/^(\d{4})-(\d{2})$/', $value, $m) ? "$m[2]/$m[1]" : $value;
    }

    public function render()
    {
        return view('livewire.reports.sales-performance')->title(__('reports.sales_performance.title'));
    }
}
