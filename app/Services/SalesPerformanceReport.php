<?php

namespace App\Services;

use App\Models\Stage;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Số liệu cho báo cáo Phân tích hiệu quả Sales Team / Salesperson.
 *
 * Bảng Leads không có người phụ trách: Lead được giao cho salesperson khi chuyển thành Cơ hội
 * (Opportunities.employee_id, ngày giao = conversion_date). Vì vậy mỗi Cơ hội = 1 Lead được giao.
 *
 * Mỗi Cơ hội được gộp sẵn các chỉ số của nó (lần liên hệ, lịch sử stage, đơn hàng, báo giá)
 * rồi mới nhóm theo nhân viên / đội / tháng, nên không bị đếm trùng.
 *
 *   Lead đã xử lý        = đã có ít nhất 1 lần liên hệ (CareResults) hoặc đã rời stage đầu tiên
 *   Lead chuyển Stage    = có lịch sử chuyển stage hoặc stage hiện tại khác stage đầu tiên
 *   Stage đã đạt tới     = stage xa nhất trong phễu (đến Chốt) theo stage hiện tại + StagesHistory;
 *                          Cơ hội Won coi như đã tới Chốt; stage Lưu trữ không tính vào phễu
 *   Lead chốt            = Cơ hội Won
 *   Doanh thu / Đã thu   = SalesOrders.total_amount / paid_amount (SalesOrders → Quotations → Opportunities)
 *   Thời gian phản hồi   = từ lúc giao đến lần liên hệ đầu tiên (giờ)
 *   Thời gian chốt       = từ lúc giao đến lúc vào stage Chốt (StagesHistory), không có thì đến báo giá đầu tiên (ngày)
 *   Lead không liên hệ được = đã liên hệ nhưng chưa lần nào thành công
 *   Lead bị chuyển ngược = có lần chuyển về stage đứng trước trong phễu
 *
 * Bộ lọc thời gian áp dụng theo ngày giao (conversion_date): mọi chỉ số tính trên nhóm Lead được giao trong kỳ.
 */
class SalesPerformanceReport
{
    // Chiều nhóm → biểu thức SQL (o = Opportunities, e = Employees)
    const DIMENSIONS = [
        'employee' => 'o.employee_id',
        'team'     => 'e.team_id',
        'month'    => "DATE_FORMAT(o.conversion_date, '%Y-%m')",
    ];

    // Chỉ số gốc (cộng dồn được) – ép kiểu trong derive()
    const SUMMABLE = [
        'assigned', 'processed', 'moved', 'won', 'lost', 'contacts', 'contacts_ok', 'unreachable', 'backward',
        'revenue', 'paid', 'expected', 'response_hours', 'response_n', 'close_days', 'close_n',
    ];

    // Chỉ số dạng số thực (tiền, giờ, ngày)
    const FLOATS = ['revenue', 'paid', 'expected', 'response_hours', 'close_days'];

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
     * Tổng hợp chỉ số theo 1 chiều.
     *
     * @param  array  $filters  from, to (Y-m-d), team, employee
     * @return array<int, array> mỗi dòng: dim + đủ các chỉ số
     */
    public function aggregate(array $filters, string $dimension): array
    {
        $query = $this->base($filters)
            ->selectRaw(self::DIMENSIONS[$dimension].' AS dim')
            ->groupBy('dim');

        $this->selectMetrics($query);

        return $query->get()
            ->map(fn ($row) => $this->derive(['dim' => (string) $row->dim] + (array) $row))
            ->all();
    }

    /**
     * Tổng chung (thẻ KPI, phễu chung).
     */
    public function totals(array $filters): array
    {
        $query = $this->base($filters);
        $this->selectMetrics($query);

        return $this->derive((array) $query->first());
    }

    /**
     * Opportunities (o) + Employees (e) + chỉ số đã gộp theo từng Cơ hội + bộ lọc chung.
     */
    protected function base(array $filters): Builder
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
            ->groupBy('opportunity_id');

        $history = DB::table('StagesHistory as h')
            ->join('Stages as fs', 'fs.stage_id', '=', 'h.from_stage_id')
            ->join('Stages as ts', 'ts.stage_id', '=', 'h.to_stage_id')
            ->select('h.opportunity_id')
            ->selectRaw('COUNT(*) AS moves')
            ->selectRaw("MAX(ts.sort_order < fs.sort_order AND fs.sort_order <= $last) AS backward")
            ->selectRaw('MAX(GREATEST('.$inFunnel('fs.sort_order').', '.$inFunnel('ts.sort_order').')) AS reached')
            ->selectRaw('MIN(IF(h.to_stage_id = ?, h.changed_at, NULL)) AS closed_at', [Stage::CLOSED])
            ->groupBy('h.opportunity_id');

        $quotes = DB::table('Quotations')
            ->select('opportunity_id')
            ->selectRaw('MIN(created_at) AS first_quote')
            ->groupBy('opportunity_id');

        $orders = DB::table('SalesOrders as so')
            ->join('Quotations as q', 'q.quotation_id', '=', 'so.quotation_id')
            ->select('q.opportunity_id')
            ->selectRaw('SUM(so.total_amount) AS revenue')
            ->selectRaw('SUM(so.paid_amount) AS paid')
            ->groupBy('q.opportunity_id');

        $perOpportunity = DB::table('Opportunities as o')
            ->join('Stages as s', 's.stage_id', '=', 'o.stage_id')
            ->leftJoinSub($care, 'c', 'c.opportunity_id', '=', 'o.opportunity_id')
            ->leftJoinSub($history, 'hs', 'hs.opportunity_id', '=', 'o.opportunity_id')
            ->leftJoinSub($quotes, 'qt', 'qt.opportunity_id', '=', 'o.opportunity_id')
            ->leftJoinSub($orders, 'rv', 'rv.opportunity_id', '=', 'o.opportunity_id')
            ->select('o.opportunity_id', 'o.employee_id', 'o.conversion_date', 'o.status')
            ->selectRaw('COALESCE(o.expected_value, 0) AS expected')
            ->selectRaw('COALESCE(c.contacts, 0) AS contacts')
            ->selectRaw('COALESCE(c.contacts_ok, 0) AS contacts_ok')
            ->selectRaw('COALESCE(hs.moves, 0) AS moves')
            ->selectRaw('COALESCE(hs.backward, 0) AS backward')
            ->selectRaw('COALESCE(rv.revenue, 0) AS revenue')
            ->selectRaw('COALESCE(rv.paid, 0) AS paid')
            ->selectRaw('(o.stage_id <> ?) AS left_first', [$stages[0]['id'] ?? Stage::UNTOUCHED])
            ->selectRaw("GREATEST($first, ".$inFunnel('s.sort_order').", COALESCE(hs.reached, 0), IF(o.status = 'Won', $last, 0)) AS reached")
            ->selectRaw('GREATEST(0, TIMESTAMPDIFF(MINUTE, o.conversion_date, c.first_contact)) / 60 AS response_hours')
            ->selectRaw("IF(o.status = 'Won', GREATEST(0, TIMESTAMPDIFF(MINUTE, o.conversion_date, COALESCE(hs.closed_at, qt.first_quote))) / 1440, NULL) AS close_days");

        return DB::query()
            ->fromSub($perOpportunity, 'o')
            ->join('Employees as e', 'e.employee_id', '=', 'o.employee_id')
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('o.conversion_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('o.conversion_date', '<=', $to))
            ->when($filters['team'] ?? null, fn ($q, $team) => $q->where('e.team_id', $team))
            ->when($filters['employee'] ?? null, fn ($q, $employee) => $q->where('o.employee_id', $employee));
    }

    protected function selectMetrics(Builder $query): void
    {
        $query->selectRaw('COUNT(*) AS assigned')
            ->selectRaw('SUM(o.contacts > 0 OR o.left_first OR o.moves > 0) AS processed')
            ->selectRaw('SUM(o.left_first OR o.moves > 0) AS moved')
            ->selectRaw("SUM(o.status = 'Won') AS won")
            ->selectRaw("SUM(o.status = 'Lost') AS lost")
            ->selectRaw('SUM(o.contacts) AS contacts')
            ->selectRaw('SUM(o.contacts_ok) AS contacts_ok')
            ->selectRaw('SUM(o.contacts > 0 AND o.contacts_ok = 0) AS unreachable')
            ->selectRaw('SUM(o.backward) AS backward')
            ->selectRaw('SUM(o.revenue) AS revenue')
            ->selectRaw('SUM(o.paid) AS paid')
            ->selectRaw('SUM(o.expected) AS expected')
            ->selectRaw('SUM(o.response_hours) AS response_hours')
            ->selectRaw('COUNT(o.response_hours) AS response_n')
            ->selectRaw('SUM(o.close_days) AS close_days')
            ->selectRaw('COUNT(o.close_days) AS close_n');

        // Số Lead đã đạt tới từng stage của phễu
        foreach ($this->funnelStages() as $stage) {
            $query->selectRaw("SUM(o.reached >= {$stage['order']}) AS reached_{$stage['order']}");
        }
    }

    /**
     * Ép kiểu số + tính các chỉ số dẫn xuất (dùng lại được sau khi cộng gộp nhiều dòng):
     *   processed_rate   = Lead đã xử lý / Lead được giao
     *   moved_rate       = Lead chuyển Stage / Lead được giao
     *   win_rate         = Lead chốt / Lead được giao
     *   close_rate       = Won / (Won + Lost)
     *   contacts_per_lead, contact_success_rate = lần liên hệ thành công / tổng lần liên hệ
     *   avg_response_hours, avg_close_days
     *   stages: [order => ['reached', 'rate' (so với stage trước), 'overall' (so với Lead được giao)]]
     */
    public function derive(array $row): array
    {
        foreach (self::SUMMABLE as $key) {
            $row[$key] = in_array($key, self::FLOATS, true) ? (float) ($row[$key] ?? 0) : (int) ($row[$key] ?? 0);
        }

        $assigned = $row['assigned'];
        $closed = $row['won'] + $row['lost'];
        $pct = fn ($a, $b) => $b ? round($a * 100 / $b, 1) : 0.0;

        $row['processed_rate'] = $pct($row['processed'], $assigned);
        $row['moved_rate'] = $pct($row['moved'], $assigned);
        $row['win_rate'] = $pct($row['won'], $assigned);
        $row['close_rate'] = $pct($row['won'], $closed);
        $row['contact_success_rate'] = $pct($row['contacts_ok'], $row['contacts']);
        $row['contacts_per_lead'] = $assigned ? round($row['contacts'] / $assigned, 1) : 0.0;
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
                'overall' => $pct($reached, $assigned),
            ];
            $previous = $reached;
        }

        return $row;
    }
}
