<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Stage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Phân bổ Lead (quy trình PhanLead.docx):
 *
 *   1. Sale Admin → Sales Team: Lead 'New' của 1 chi nhánh được chuyển thành Cơ hội ở stage đầu,
 *      người phụ trách = trưởng nhóm của đội (odin.sql không có cột lưu đội của Lead).
 *      Lead vẫn 'New' (đội đang giữ, chưa phân cho salesperson).
 *   2. Sale Leader → Salesperson: Cơ hội trưởng nhóm đang giữ (stage đầu, InProgress, chưa chăm sóc)
 *      được đổi người phụ trách sang salesperson, ngày giao (conversion_date) = lúc phân.
 *      Lúc này Lead mới chuyển sang 'Converted'.
 *
 * Chia tự động: chia đều phần nguyên, Lead dư lần lượt cho Top 2 theo hiệu suất tháng trước.
 * Mỗi salesperson quản lý tối đa MAX_PER_SALESPERSON Cơ hội đang xử lý (InProgress).
 */
class LeadDistributionService
{
    const MAX_PER_SALESPERSON = 50;

    // Tiêu chí xếp hạng hiệu suất (tính theo tháng trước) → khoá chỉ số trong SalesPerformanceReport
    const CRITERIA = ['revenue', 'close_rate', 'students'];

    // Số đội / salesperson nhận Lead dư
    const TOP = 2;

    public function __construct(protected SalesPerformanceReport $report) {}

    /**
     * Tháng trước (tháng hiện tại chưa kết thúc nên chưa đủ số liệu): [from, to, nhãn m/Y].
     */
    public function previousMonth(): array
    {
        $start = Carbon::now()->subMonthNoOverflow()->startOfMonth();

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString(), $start->format('m/Y')];
    }

    /**
     * Hiệu suất tháng trước theo đội ('team') hoặc nhân viên ('employee'):
     * [id => ['revenue' => float, 'close_rate' => ?float, 'students' => int]].
     */
    public function performance(string $dimension, ?string $teamId = null): array
    {
        [$from, $to] = $this->previousMonth();

        $rows = $this->report->aggregate(array_filter(['from' => $from, 'to' => $to, 'team' => $teamId]), $dimension);

        return array_map(fn ($row) => [
            'revenue'    => $row['revenue'],
            'close_rate' => $row['close_rate'],
            'students'   => $row['students'],
        ], $rows);
    }

    /**
     * Xếp hạng theo tiêu chí giảm dần; hoà thì ai đang giữ ít Lead hơn trước, cuối cùng theo mã.
     *
     * @param  string[]  $ids
     * @return string[]
     */
    public function rank(array $ids, array $performance, string $criterion, array $load = []): array
    {
        $criterion = in_array($criterion, self::CRITERIA, true) ? $criterion : self::CRITERIA[0];
        $score = fn ($id) => $performance[$id][$criterion] ?? -1;

        usort($ids, fn ($a, $b) => [$score($b), $load[$a] ?? 0, $a] <=> [$score($a), $load[$b] ?? 0, $b]);

        return $ids;
    }

    /**
     * Chia đều $total cho các id (đã xếp hạng); phần dư lần lượt cho Top 2.
     *
     * @param  string[]  $rankedIds
     * @return array<string, int>
     */
    public function splitEvenly(int $total, array $rankedIds): array
    {
        $count = count($rankedIds);
        if ($count === 0) {
            return [];
        }

        $plan = array_fill_keys($rankedIds, intdiv($total, $count));
        $top = array_slice($rankedIds, 0, min(self::TOP, $count));

        for ($i = 0; $i < $total % $count; $i++) {
            $plan[$top[$i % count($top)]]++;
        }

        return $plan;
    }

    /**
     * Số Cơ hội đang xử lý (InProgress) của từng nhân viên.
     *
     * @return array<string, int>
     */
    public function currentLoad(array $employeeIds): array
    {
        return Opportunity::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'InProgress')
            ->groupBy('employee_id')
            ->selectRaw('employee_id, COUNT(*) AS total')
            ->pluck('total', 'employee_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Số Lead còn nhận thêm được của salesperson.
     */
    public function capacity(string $employeeId, array $load): int
    {
        return max(0, self::MAX_PER_SALESPERSON - ($load[$employeeId] ?? 0));
    }

    /**
     * Cắt phần vượt ngưỡng 50 Lead/người: [kế hoạch đã cắt, tổng phần vượt].
     */
    public function excess(array $plan, array $load): array
    {
        $over = 0;
        foreach ($plan as $id => $count) {
            $allowed = min($count, $this->capacity($id, $load));
            $over += $count - $allowed;
            $plan[$id] = $allowed;
        }

        return [$plan, $over];
    }

    /**
     * Chia tự động có ngưỡng: phần vượt được phân lại lần lượt cho Top 2 salesperson còn chỗ
     * (theo thứ hạng hiệu suất). Trả về [kế hoạch, số Lead không còn ai nhận được].
     */
    public function capAuto(array $plan, array $load, array $rankedIds): array
    {
        [$plan, $over] = $this->excess($plan, $load);

        $turn = 0;
        while ($over > 0) {
            $open = array_values(array_filter(
                $rankedIds,
                fn ($id) => ($plan[$id] ?? 0) < $this->capacity($id, $load),
            ));
            if (! $open) {
                break;
            }

            $top = array_slice($open, 0, self::TOP);
            $plan[$top[$turn++ % count($top)]]++;
            $over--;
        }

        return [$plan, $over];
    }

    /* ── Lead khả dụng ────────────────────────────────────────────────── */

    /**
     * Lead chưa phân cho đội (Sale Admin), cũ trước.
     */
    public function availableLeads(string $branchId, ?string $source = null, ?string $method = null): Builder
    {
        return Lead::query()
            ->where('status', Lead::STATUS_NEW)
            ->where('branch_id', $branchId)
            ->whereDoesntHave('opportunities')
            ->when($source, fn ($q) => $q->where('source_name', $source))
            ->when($method, fn ($q) => $q->where('contact_method', $method))
            ->orderBy('created_at')
            ->orderBy('lead_id');
    }

    /**
     * Lead đội đang chờ phân cho Salesperson (Cơ hội trưởng nhóm giữ ở stage đầu, chưa chăm sóc), cũ trước.
     */
    public function pendingOpportunities(string $leaderId, ?string $source = null, ?string $method = null): Builder
    {
        return Opportunity::query()
            ->where('employee_id', $leaderId)
            ->where('stage_id', Stage::UNTOUCHED)
            ->where('status', 'InProgress')
            ->whereDoesntHave('careResults')
            ->whereHas('lead', fn ($q) => $q
                ->when($source, fn ($q) => $q->where('source_name', $source))
                ->when($method, fn ($q) => $q->where('contact_method', $method)))
            ->orderBy('conversion_date')
            ->orderBy('opportunity_id');
    }

    /* ── Ghi kết quả ──────────────────────────────────────────────────── */

    /**
     * Phân Lead cho các đội: $plan = [trưởng nhóm employee_id => số Lead]. Trả về số Lead đã phân.
     */
    public function assignToTeams(array $plan, string $branchId, ?string $source, ?string $method): int
    {
        $plan = array_filter($plan);
        $total = array_sum($plan);

        return DB::transaction(function () use ($plan, $total, $branchId, $source, $method) {
            $leadIds = $this->availableLeads($branchId, $source, $method)
                ->lockForUpdate()
                ->limit($total)
                ->pluck('lead_id')
                ->all();

            if (count($leadIds) < $total) {
                throw new RuntimeException(__('leads.distribute.exceed'));
            }

            $ids = Opportunity::nextIds($total);
            $now = Carbon::now();
            $rows = [];
            $i = 0;
            foreach ($plan as $leaderId => $count) {
                for ($n = 0; $n < $count; $n++, $i++) {
                    $rows[] = [
                        'opportunity_id'  => $ids[$i],
                        'conversion_date' => $now,
                        'status'          => 'InProgress',
                        'lead_id'         => $leadIds[$i],
                        'stage_id'        => Stage::UNTOUCHED,
                        'employee_id'     => $leaderId,
                    ];
                }
            }

            Opportunity::insert($rows);

            return $total;
        });
    }

    /**
     * Phân Lead của đội cho salesperson: $plan = [employee_id => số Lead]. Trả về số Lead đã phân.
     */
    public function assignToSalespersons(string $leaderId, array $plan, ?string $source, ?string $method): int
    {
        $plan = array_filter($plan);
        $total = array_sum($plan);

        return DB::transaction(function () use ($leaderId, $plan, $total, $source, $method) {
            $oppIds = $this->pendingOpportunities($leaderId, $source, $method)
                ->lockForUpdate()
                ->limit($total)
                ->pluck('opportunity_id')
                ->all();

            if (count($oppIds) < $total) {
                throw new RuntimeException(__('leads.distribute.exceed'));
            }

            $now = Carbon::now();
            $offset = 0;
            foreach ($plan as $employeeId => $count) {
                Opportunity::whereIn('opportunity_id', array_slice($oppIds, $offset, $count))
                    ->update(['employee_id' => $employeeId, 'conversion_date' => $now]);
                $offset += $count;
            }

            Lead::whereIn('lead_id', Opportunity::whereIn('opportunity_id', $oppIds)->select('lead_id'))
                ->update(['status' => Lead::STATUS_CONVERTED]);

            return $total;
        });
    }
}
