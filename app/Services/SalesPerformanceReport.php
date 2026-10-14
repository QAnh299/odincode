<?php

namespace App\Services;

use App\Models\Stage;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Số liệu cho báo cáo Phân tích hiệu quả Sales Team / Salesperson theo tháng.
 *
 * Bảng Leads không có người phụ trách: Lead được giao cho salesperson khi chuyển thành Cơ hội
 * (Opportunities.employee_id, ngày giao = conversion_date). Mỗi Cơ hội = 1 Lead được giao.
 *
 * Mỗi chỉ số được tính theo đúng thời điểm phát sinh của nó trong kỳ:
 *
 *   A. Theo ngày giao Lead (nhóm Lead được giao trong kỳ – đo quy trình xử lý):
 *      Lead được giao, Lead đã xử lý (đã liên hệ hoặc đã rời stage đầu), Lead chuyển Stage,
 *      Stage đã đạt tới (phễu), thời gian phản hồi đầu tiên, số lần liên hệ,
 *      Lead không liên hệ được (đã liên hệ nhưng chưa lần nào thành công), Lead bị chuyển ngược Stage.
 *   B. Theo ngày chốt (Cơ hội Won / Lost có ngày đóng trong kỳ – đo kết quả):
 *      Lead chốt, Lead thất bại, tỷ lệ thắng = Won / (Won + Lost), thời gian chốt.
 *      Ngày đóng = lúc vào stage Chốt / Lưu trữ (StagesHistory); thiếu lịch sử thì lấy báo giá đầu tiên (Won),
 *      lần liên hệ cuối, cuối cùng là ngày giao.
 *   C. Theo ngày tạo đơn hàng: Doanh thu = SalesOrders.total_amount, Đã thu = paid_amount
 *      (SalesOrders → Quotations → Opportunities, tính cho người phụ trách Cơ hội).
 *   D. Theo ngày thành học viên: Học viên mới (Students.conversion_date).
 *
 * Mọi truy vấn đều có cột event_at (thời điểm phát sinh) nên lọc / nhóm theo tháng dùng chung được.
 */
class SalesPerformanceReport
{
    // Chiều nhóm → biểu thức SQL (o = dòng dữ liệu đã có employee_id + event_at, e = Employees)
    const DIMENSIONS = [
        'all'      => "'all'",
        'employee' => 'o.employee_id',
        'team'     => 'e.team_id',
        'month'    => "DATE_FORMAT(o.event_at, '%Y-%m')",
    ];

    // Loại chỉ tiêu đội (SalesTargets.target_type) → chỉ số so sánh
    const TARGETS = [
        'Revenue'     => 'revenue',
        'NewStudents' => 'students',
        'NewLeads'    => 'assigned',
    ];

    // Chỉ số gốc (cộng dồn được); FLOATS là số thực (tiền, giờ, ngày)
    const SUMMABLE = [
        'assigned', 'processed', 'moved', 'contacts', 'contacts_ok', 'unreachable', 'backward',
        'response_hours', 'response_n', 'won', 'lost', 'close_days', 'close_n', 'revenue', 'paid', 'students',
    ];

    const FLOATS = ['response_hours', 'close_days', 'revenue', 'paid'];

    protected ?array $funnel = null;

    /**
     * Các stage của phễu (từ stage đầu đến Chốt), theo sort_order: [['id', 'name', 'order'], ...].
     */
    public function funnelStages(): array
    {
        if ($this->funnel === null) {
            $closed = (int) DB::table('Stages')->where('stage_id', Stage::CLOSED)->value('sort_order');

            $this->funnel = DB::table('Stages')
                ->when($closed, fn ($q) => $q->where('sort_order', '<=', $closed))
                ->orderBy('sort_order')
                ->get(['stage_id', 'stage_name', 'sort_order'])
                ->map(fn ($s) => ['id' => $s->stage_id, 'name' => $s->stage_name, 'order' => (int) $s->sort_order])
                ->all();
        }

        return $this->funnel;
    }

    /**
     * Tổng hợp đủ chỉ số theo 1 chiều.
     *
     * @param  array  $filters  from, to (Y-m-d), team, employee
     * @return array<string, array> dim → dòng chỉ số
     */
    public function aggregate(array $filters, string $dimension): array
    {
        $dim = self::DIMENSIONS[$dimension];
        $stages = $this->funnelStages();

        $parts = [];

        // A. Theo ngày giao Lead
        $cohort = $this->scoped($this->facts(), $filters)->selectRaw("$dim AS dim")->groupBy('dim')
            ->selectRaw('COUNT(*) AS assigned')
            ->selectRaw('SUM(o.contacts > 0 OR o.left_first OR o.moves > 0) AS processed')
            ->selectRaw('SUM(o.left_first OR o.moves > 0) AS moved')
            ->selectRaw('SUM(o.contacts) AS contacts')
            ->selectRaw('SUM(o.contacts_ok) AS contacts_ok')
            ->selectRaw('SUM(o.contacts > 0 AND o.contacts_ok = 0) AS unreachable')
            ->selectRaw('SUM(o.backward) AS backward')
            ->selectRaw('SUM(o.response_hours) AS response_hours')
            ->selectRaw('COUNT(o.response_hours) AS response_n');
        foreach ($stages as $stage) {
            $cohort->selectRaw("SUM(o.reached >= {$stage['order']}) AS reached_{$stage['order']}");
        }
        $parts[] = $cohort->get();

        // B. Theo ngày chốt
        $parts[] = $this->scoped($this->facts('closed_at')->whereIn('f.status', ['Won', 'Lost']), $filters)
            ->selectRaw("$dim AS dim")->groupBy('dim')
            ->selectRaw("SUM(o.status = 'Won') AS won")
            ->selectRaw("SUM(o.status = 'Lost') AS lost")
            ->selectRaw("SUM(IF(o.status = 'Won', o.close_days, NULL)) AS close_days")
            ->selectRaw("COUNT(IF(o.status = 'Won', o.close_days, NULL)) AS close_n")
            ->get();

        // C. Theo ngày tạo đơn hàng
        $orders = DB::table('SalesOrders as so')
            ->join('Quotations as q', 'q.quotation_id', '=', 'so.quotation_id')
            ->join('Opportunities as op', 'op.opportunity_id', '=', 'q.opportunity_id')
            ->select('op.employee_id', 'so.created_at as event_at', 'so.total_amount', 'so.paid_amount');
        $parts[] = $this->scoped($orders, $filters)->selectRaw("$dim AS dim")->groupBy('dim')
            ->selectRaw('SUM(o.total_amount) AS revenue')
            ->selectRaw('SUM(o.paid_amount) AS paid')
            ->get();

        // D. Theo ngày thành học viên
        $students = DB::table('Students as st')
            ->join('Opportunities as op', 'op.opportunity_id', '=', 'st.opportunity_id')
            ->select('op.employee_id', 'st.conversion_date as event_at');
        $parts[] = $this->scoped($students, $filters)->selectRaw("$dim AS dim")->groupBy('dim')
            ->selectRaw('COUNT(*) AS students')
            ->get();

        // Gộp các phần theo dim
        $rows = [];
        foreach ($parts as $part) {
            foreach ($part as $row) {
                $row = (array) $row;
                $key = (string) $row['dim'];
                $rows[$key] = ($rows[$key] ?? []) + $row;
            }
        }

        return array_map(fn ($row) => $this->derive($row), $rows);
    }

    /**
     * Tổng chung của phạm vi lọc.
     */
    public function totals(array $filters): array
    {
        return $this->aggregate($filters, 'all')['all'] ?? $this->derive([]);
    }

    /**
     * Chỉ tiêu của các đội trong 1 tháng: [team_id => ['revenue' => ..., 'students' => ..., 'assigned' => ...]].
     */
    public function targets(string $month, ?array $teamIds = null): array
    {
        $rows = DB::table('TeamSalesTargets as t')
            ->join('SalesTargets as s', 's.target_id', '=', 't.target_id')
            ->whereIn('s.target_type', array_keys(self::TARGETS))
            ->where('t.target_month', $month.'-01')
            ->where('t.status', '<>', 'Cancelled')
            ->when($teamIds !== null, fn ($q) => $q->whereIn('t.team_id', $teamIds))
            ->get(['t.team_id', 's.target_type', 't.target_value']);

        $targets = [];
        foreach ($rows as $row) {
            $targets[$row->team_id][self::TARGETS[$row->target_type]] = (float) $row->target_value;
        }

        return $targets;
    }

    /**
     * Gắn Employees (e) + bộ lọc chung lên 1 truy vấn có employee_id, event_at.
     */
    protected function scoped(Builder $source, array $filters): Builder
    {
        return DB::query()
            ->fromSub($source, 'o')
            ->join('Employees as e', 'e.employee_id', '=', 'o.employee_id')
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('o.event_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('o.event_at', '<=', $to))
            ->when($filters['team'] ?? null, fn ($q, $team) => $q->where('e.team_id', $team))
            ->when($filters['employee'] ?? null, fn ($q, $employee) => $q->where('o.employee_id', $employee));
    }

    /**
     * Mỗi Cơ hội 1 dòng với các chỉ số đã gộp; event_at = ngày giao hoặc ngày đóng.
     */
    protected function facts(string $event = 'conversion_date'): Builder
    {
        $stages = $this->funnelStages();
        $first = $stages[0]['order'] ?? 1;
        $last = $stages ? end($stages)['order'] : 1;

        // Stage trong phễu thì lấy sort_order, ngoài phễu (Lưu trữ) thì 0
        $inFunnel = fn (string $col) => "IF($col <= $last, $col, 0)";

        $care = DB::table('CareResults')
            ->select('opportunity_id')
            ->selectRaw('COUNT(*) AS contacts')
            ->selectRaw("SUM(result = 'Success') AS contacts_ok")
            ->selectRaw('MIN(performed_at) AS first_contact')
            ->selectRaw('MAX(performed_at) AS last_contact')
            ->groupBy('opportunity_id');

        $history = DB::table('StagesHistory as h')
            ->join('Stages as fs', 'fs.stage_id', '=', 'h.from_stage_id')
            ->join('Stages as ts', 'ts.stage_id', '=', 'h.to_stage_id')
            ->select('h.opportunity_id')
            ->selectRaw('COUNT(*) AS moves')
            ->selectRaw("MAX(ts.sort_order < fs.sort_order AND fs.sort_order <= $last) AS backward")
            ->selectRaw('MAX(GREATEST('.$inFunnel('fs.sort_order').', '.$inFunnel('ts.sort_order').')) AS reached')
            ->selectRaw('MIN(IF(h.to_stage_id = ?, h.changed_at, NULL)) AS won_at', [Stage::CLOSED])
            ->selectRaw("MIN(IF(ts.sort_order > $last, h.changed_at, NULL)) AS archived_at")
            ->groupBy('h.opportunity_id');

        $quotes = DB::table('Quotations')
            ->select('opportunity_id')
            ->selectRaw('MIN(created_at) AS first_quote')
            ->groupBy('opportunity_id');

        $facts = DB::table('Opportunities as o')
            ->join('Stages as s', 's.stage_id', '=', 'o.stage_id')
            ->leftJoinSub($care, 'c', 'c.opportunity_id', '=', 'o.opportunity_id')
            ->leftJoinSub($history, 'hs', 'hs.opportunity_id', '=', 'o.opportunity_id')
            ->leftJoinSub($quotes, 'qt', 'qt.opportunity_id', '=', 'o.opportunity_id')
            ->select('o.opportunity_id', 'o.employee_id', 'o.conversion_date', 'o.status')
            ->selectRaw('COALESCE(c.contacts, 0) AS contacts')
            ->selectRaw('COALESCE(c.contacts_ok, 0) AS contacts_ok')
            ->selectRaw('COALESCE(hs.moves, 0) AS moves')
            ->selectRaw('COALESCE(hs.backward, 0) AS backward')
            ->selectRaw('(o.stage_id <> ?) AS left_first', [$stages[0]['id'] ?? Stage::UNTOUCHED])
            ->selectRaw("GREATEST($first, ".$inFunnel('s.sort_order').", COALESCE(hs.reached, 0), IF(o.status = 'Won', $last, 0)) AS reached")
            ->selectRaw('GREATEST(0, TIMESTAMPDIFF(MINUTE, o.conversion_date, c.first_contact)) / 60 AS response_hours')
            ->selectRaw("CASE o.status
                WHEN 'Won' THEN COALESCE(hs.won_at, qt.first_quote, c.last_contact, o.conversion_date)
                WHEN 'Lost' THEN COALESCE(hs.archived_at, c.last_contact, o.conversion_date)
                END AS closed_at");

        // Bọc thêm 1 lớp để tính thời gian chốt và đặt event_at
        return DB::query()->fromSub($facts, 'f')
            ->select('f.*')
            ->selectRaw("f.$event AS event_at")
            ->selectRaw('GREATEST(0, TIMESTAMPDIFF(MINUTE, f.conversion_date, f.closed_at)) / 1440 AS close_days');
    }

    /**
     * Ép kiểu số + tính các chỉ số dẫn xuất (dùng lại được sau khi cộng gộp nhiều dòng):
     *   processed_rate = Lead đã xử lý / Lead được giao      moved_rate = Lead chuyển Stage / Lead được giao
     *   close_rate     = Won / (Won + Lost) đóng trong kỳ     contact_success_rate = liên hệ thành công / tổng liên hệ
     *   avg_response_hours, avg_close_days
     *   stages: [order => ['reached', 'rate' (so với stage trước), 'overall' (so với Lead được giao)]]
     */
    public function derive(array $row): array
    {
        foreach (self::SUMMABLE as $key) {
            $row[$key] = in_array($key, self::FLOATS, true) ? (float) ($row[$key] ?? 0) : (int) ($row[$key] ?? 0);
        }

        $pct = fn ($a, $b) => $b ? round($a * 100 / $b, 1) : null;

        $row['processed_rate'] = $pct($row['processed'], $row['assigned']);
        $row['moved_rate'] = $pct($row['moved'], $row['assigned']);
        $row['close_rate'] = $pct($row['won'], $row['won'] + $row['lost']);
        $row['contact_success_rate'] = $pct($row['contacts_ok'], $row['contacts']);
        $row['avg_response_hours'] = $row['response_n'] ? round($row['response_hours'] / $row['response_n'], 1) : null;
        $row['avg_close_days'] = $row['close_n'] ? round($row['close_days'] / $row['close_n'], 1) : null;

        $row['stages'] = [];
        $previous = null;
        foreach ($this->funnelStages() as $stage) {
            $reached = (int) ($row['reached_'.$stage['order']] ?? 0);
            $row['reached_'.$stage['order']] = $reached;
            $row['stages'][$stage['order']] = [
                'reached' => $reached,
                'rate'    => $previous === null ? null : $pct($reached, $previous),
                'overall' => $pct($reached, $row['assigned']),
            ];
            $previous = $reached;
        }

        return $row;
    }

    /**
     * Cộng các chỉ số gốc của nhiều dòng rồi tính lại chỉ số dẫn xuất.
     */
    public function combine(array $rows): array
    {
        $keys = array_merge(self::SUMMABLE, array_map(fn ($s) => 'reached_'.$s['order'], $this->funnelStages()));
        $sum = [];
        foreach ($keys as $key) {
            $sum[$key] = array_sum(array_column($rows, $key));
        }

        return $this->derive($sum);
    }
}
