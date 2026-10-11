<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Số liệu cho báo cáo Phân tích chất lượng nguồn Lead.
 *
 * Mỗi Lead được gộp sẵn các chỉ số của nó (đã có Cơ hội chưa, có Cơ hội Won/Lost không,
 * doanh thu đơn hàng, giá trị kỳ vọng) rồi mới nhóm theo chiều phân tích,
 * nên một Lead có nhiều Cơ hội / đơn hàng không bị đếm trùng.
 *
 * Doanh thu = tổng SalesOrders.total_amount, đã thu = tổng SalesOrders.paid_amount
 * (SalesOrders → Quotations → Opportunities → Leads).
 */
class LeadSourceReport
{
    // Chiều phân tích (trục X / nhóm) → biểu thức SQL trên bảng Leads (alias l)
    const DIMENSIONS = [
        'source' => 'l.source_name',
        'method' => 'l.contact_method',
        'branch' => 'l.branch_id',
        'status' => 'l.status',
        'month'  => "DATE_FORMAT(l.created_at, '%Y-%m')",
        'week'   => "DATE_FORMAT(l.created_at, '%x-%v')",
    ];

    // Chiều thời gian: luôn sắp theo thời gian, không cho chia nhóm theo chiều này
    const TIME_DIMENSIONS = ['month', 'week'];

    /**
     * Chỉ số → đơn vị. count/money cộng dồn được (dùng được cho tròn, chồng);
     * percent là tỷ lệ, chỉ dùng cho cột / đường.
     */
    const METRICS = [
        'leads'            => 'count',
        'converted'        => 'count',
        'won'              => 'count',
        'lost'             => 'count',
        'conversion_rate'  => 'percent',
        'win_rate'         => 'percent',
        'close_rate'       => 'percent',
        'revenue'          => 'money',
        'paid'             => 'money',
        'expected'         => 'money',
        'revenue_per_lead' => 'money',
    ];

    // Chỉ số tính từ các chỉ số gốc (không cộng dồn được)
    const DERIVED = ['conversion_rate', 'win_rate', 'close_rate', 'revenue_per_lead'];

    /**
     * Tổng hợp chỉ số theo 1 chiều (và chiều chia nhóm, nếu có).
     *
     * @param  array  $filters  from, to (Y-m-d), branch, method, sources (mảng tên nguồn)
     * @return array<int, array> mỗi dòng: dim, group (null nếu không chia nhóm) + đủ các chỉ số
     */
    public function aggregate(array $filters, string $dimension, ?string $breakdown = null): array
    {
        $query = $this->base($filters)
            ->selectRaw(self::DIMENSIONS[$dimension].' AS dim')
            ->groupBy('dim');

        if ($breakdown) {
            $query->selectRaw(self::DIMENSIONS[$breakdown].' AS grp')->groupBy('grp');
        }

        $this->selectMetrics($query);

        return $query->get()
            ->map(fn ($row) => $this->derive([
                'dim'   => (string) $row->dim,
                'group' => $breakdown ? (string) $row->grp : null,
            ] + (array) $row))
            ->all();
    }

    /**
     * Tổng chung (cho các thẻ KPI).
     */
    public function totals(array $filters): array
    {
        $query = $this->base($filters)->selectRaw('COUNT(DISTINCT l.source_name) AS sources');
        $this->selectMetrics($query);

        $row = (array) $query->first();

        return $this->derive($row) + ['sources' => (int) $row['sources']];
    }

    /**
     * Leads (alias l) + chỉ số đã gộp theo từng Lead + bộ lọc chung.
     */
    protected function base(array $filters): Builder
    {
        $opportunities = DB::table('Opportunities')
            ->select('lead_id')
            ->selectRaw("MAX(status = 'Won') AS won")
            ->selectRaw("MAX(status = 'Lost') AS lost")
            ->selectRaw('SUM(expected_value) AS expected')
            ->groupBy('lead_id');

        $orders = DB::table('SalesOrders as so')
            ->join('Quotations as q', 'q.quotation_id', '=', 'so.quotation_id')
            ->join('Opportunities as o', 'o.opportunity_id', '=', 'q.opportunity_id')
            ->select('o.lead_id')
            ->selectRaw('SUM(so.total_amount) AS revenue')
            ->selectRaw('SUM(so.paid_amount) AS paid')
            ->groupBy('o.lead_id');

        return DB::table('Leads as l')
            ->leftJoinSub($opportunities, 'op', 'op.lead_id', '=', 'l.lead_id')
            ->leftJoinSub($orders, 'rv', 'rv.lead_id', '=', 'l.lead_id')
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('l.created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('l.created_at', '<=', $to))
            ->when($filters['branch'] ?? null, fn ($q, $branch) => $q->where('l.branch_id', $branch))
            ->when($filters['method'] ?? null, fn ($q, $method) => $q->where('l.contact_method', $method))
            ->when($filters['sources'] ?? null, fn ($q, $sources) => $q->whereIn('l.source_name', $sources));
    }

    protected function selectMetrics(Builder $query): void
    {
        $query->selectRaw('COUNT(*) AS leads')
            ->selectRaw("SUM(l.status = 'Converted') AS converted")
            ->selectRaw('SUM(COALESCE(op.won, 0)) AS won')
            ->selectRaw('SUM(COALESCE(op.lost, 0)) AS lost')
            ->selectRaw('SUM(COALESCE(rv.revenue, 0)) AS revenue')
            ->selectRaw('SUM(COALESCE(rv.paid, 0)) AS paid')
            ->selectRaw('SUM(COALESCE(op.expected, 0)) AS expected');
    }

    /**
     * Ép kiểu số + tính các chỉ số dẫn xuất:
     *   conversion_rate = Lead đã chuyển thành Cơ hội / Lead
     *   win_rate        = Lead chốt thành công (Won) / Lead
     *   close_rate      = Won / (Won + Lost) – tỷ lệ thắng trên các Cơ hội đã có kết quả
     *   revenue_per_lead = Doanh thu / Lead
     */
    public function derive(array $row): array
    {
        foreach (['leads', 'converted', 'won', 'lost'] as $key) {
            $row[$key] = (int) ($row[$key] ?? 0);
        }
        foreach (['revenue', 'paid', 'expected'] as $key) {
            $row[$key] = (float) ($row[$key] ?? 0);
        }

        $leads = $row['leads'];
        $closed = $row['won'] + $row['lost'];

        $row['conversion_rate'] = $leads ? round($row['converted'] * 100 / $leads, 1) : 0.0;
        $row['win_rate'] = $leads ? round($row['won'] * 100 / $leads, 1) : 0.0;
        $row['close_rate'] = $closed ? round($row['won'] * 100 / $closed, 1) : 0.0;
        $row['revenue_per_lead'] = $leads ? round($row['revenue'] / $leads) : 0.0;

        return $row;
    }
}
