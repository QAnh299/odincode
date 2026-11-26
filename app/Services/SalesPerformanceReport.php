<?php

namespace App\Services;

use App\Models\CareActivity;
use App\Models\Opportunity;
use App\Models\Stage;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesPerformanceReport
{
    public const DIMENSIONS = [
        'all'        => "'all'",
        'employee'   => 'o.employee_id',
        'team'       => 'e.team_id',
        'month'      => "DATE_FORMAT(o.event_at, '%Y-%m')",
        'team_month' => "CONCAT(e.team_id, '|', DATE_FORMAT(o.event_at, '%Y-%m'))",

        'week'       => 'FLOOR((DAY(o.event_at) - 1) / 7) + 1',
        'team_week'  => "CONCAT(e.team_id, '|', FLOOR((DAY(o.event_at) - 1) / 7) + 1)",
    ];

    public const RESPONSE_SLA_HOURS = 4;

    public const UNCONTACTED_HOURS = 24;

    public const STALE_DAYS = 7;

    public const DECISION_WARN_DAYS = 3;

    public const ALERTS = ['overdue_appointment', 'needs_quotation', 'decision_due', 'stale'];

    public const TARGETS = [
        'Revenue'     => 'revenue',
        'NewStudents' => 'students',
    ];

    public const SUMMABLE = [
        'assigned', 'processed', 'moved', 'contacts', 'contacts_ok', 'unreachable', 'backward',
        'response_hours', 'response_n', 'won', 'lost', 'close_days', 'close_n', 'revenue', 'paid', 'students',
        'orders', 'care', 'care_ok', 'appts', 'appts_ok', 'transfers_out',
        'contacted', 'contacted_ok', 'status_won', 'status_closed', 'status_new', 'status_unreached',
    ];

    public const FLOATS = ['response_hours', 'close_days', 'revenue', 'paid'];

    protected ?array $funnel = null;

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

    public function aggregate(array $filters, string $dimension): array
    {
        $dim = self::DIMENSIONS[$dimension];
        $stages = $this->funnelStages();

        $parts = [];

        $cohort = $this->scoped($this->facts(), $filters)->selectRaw("$dim AS dim")->groupBy('dim')
            ->selectRaw('COUNT(*) AS assigned')
            ->selectRaw('SUM(o.contacts > 0 OR o.left_first OR o.moves > 0) AS processed')
            ->selectRaw('SUM(o.left_first OR o.moves > 0) AS moved')
            ->selectRaw('SUM(o.contacts) AS contacts')
            ->selectRaw('SUM(o.contacts_ok) AS contacts_ok')
            ->selectRaw('SUM(o.contacts > 0 AND o.contacts_ok = 0) AS unreachable')
            ->selectRaw('SUM(o.backward) AS backward')
            ->selectRaw('SUM(o.response_hours) AS response_hours')
            ->selectRaw('COUNT(o.response_hours) AS response_n')
            ->selectRaw('SUM(o.contacts > 0) AS contacted')
            ->selectRaw('SUM(o.contacts_ok > 0) AS contacted_ok')

            ->selectRaw("SUM(o.status = 'Won') AS status_won")
            ->selectRaw("SUM(o.status IN ('Lost', 'Archived')) AS status_closed")
            ->selectRaw("SUM(o.status <> 'Won' AND o.contacts = 0) AS status_new")
            ->selectRaw("SUM(o.status <> 'Won' AND o.contacts > 0 AND o.contacts_ok = 0) AS status_unreached");
        foreach ($stages as $stage) {
            $cohort->selectRaw("SUM(o.reached >= {$stage['order']}) AS reached_{$stage['order']}");
        }
        $parts[] = $cohort->get();

        $parts[] = $this->scoped($this->facts('closed_at')->whereIn('f.status', ['Won', 'Lost']), $filters)
            ->selectRaw("$dim AS dim")->groupBy('dim')
            ->selectRaw("SUM(o.status = 'Won') AS won")
            ->selectRaw("SUM(o.status = 'Lost') AS lost")
            ->selectRaw("SUM(IF(o.status = 'Won', o.close_days, NULL)) AS close_days")
            ->selectRaw("COUNT(IF(o.status = 'Won', o.close_days, NULL)) AS close_n")
            ->get();

        $orders = DB::table('SalesOrders as so')
            ->join('Quotations as q', 'q.quotation_id', '=', 'so.quotation_id')
            ->join('Opportunities as op', 'op.opportunity_id', '=', 'q.opportunity_id')
            ->select('op.employee_id', 'so.created_at as event_at', 'so.total_amount', 'so.paid_amount');
        $parts[] = $this->scoped($orders, $filters)->selectRaw("$dim AS dim")->groupBy('dim')
            ->selectRaw('SUM(o.total_amount) AS revenue')
            ->selectRaw('SUM(o.paid_amount) AS paid')
            ->selectRaw('COUNT(*) AS orders')
            ->get();

        $students = DB::table('Students as st')
            ->join('Opportunities as op', 'op.opportunity_id', '=', 'st.opportunity_id')
            ->select('op.employee_id', 'st.conversion_date as event_at');
        $parts[] = $this->scoped($students, $filters)->selectRaw("$dim AS dim")->groupBy('dim')
            ->selectRaw('COUNT(*) AS students')
            ->get();

        $care = DB::table('CareResults')->select('employee_id', 'performed_at as event_at', 'result');
        $parts[] = $this->scoped($care, $filters)->selectRaw("$dim AS dim")->groupBy('dim')
            ->selectRaw('COUNT(*) AS care')
            ->selectRaw("SUM(o.result = 'Success') AS care_ok")
            ->get();

        $appointments = DB::table('Appointments')
            ->whereIn('status', ['Success', 'Failed'])
            ->select('employee_id', 'scheduled_time as event_at', 'status');
        $parts[] = $this->scoped($appointments, $filters)->selectRaw("$dim AS dim")->groupBy('dim')
            ->selectRaw('COUNT(*) AS appts')
            ->selectRaw("SUM(o.status = 'Success') AS appts_ok")
            ->get();

        $transfers = DB::table('OpportunityTransfers')->select('from_employee_id as employee_id', 'transferred_at as event_at');
        $parts[] = $this->scoped($transfers, $filters)->selectRaw("$dim AS dim")->groupBy('dim')
            ->selectRaw('COUNT(*) AS transfers_out')
            ->get();

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

    public function totals(array $filters): array
    {
        return $this->aggregate($filters, 'all')['all'] ?? $this->derive([]);
    }

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

    public function pipeline(array $filters, string $dimension): array
    {
        $dim = self::DIMENSIONS[$dimension];

        return $this->current($filters)
            ->selectRaw("$dim AS dim")->groupBy('dim')
            ->selectRaw('COUNT(*) AS open')
            ->selectRaw('COALESCE(SUM(o.expected_value), 0) AS open_value')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->dim => ['open' => (int) $r->open, 'open_value' => (float) $r->open_value]])
            ->all();
    }

    public function uncontacted(array $filters, int $hours = self::UNCONTACTED_HOURS): int
    {
        return $this->uncontactedQuery($filters, $hours)->count();
    }

    protected function uncontactedQuery(array $filters, int $hours = self::UNCONTACTED_HOURS): Builder
    {
        return $this->current($filters)
            ->where('o.conversion_date', '<=', now()->subHours($hours))
            ->whereNotExists(fn ($q) => $q->from('CareResults as cr')->whereColumn('cr.opportunity_id', 'o.opportunity_id'));
    }

    public function alerts(array $filters): array
    {
        $query = DB::query()->fromSub($this->alertFlags($filters), 'f')->select('f.employee_id')->groupBy('f.employee_id')
            ->selectRaw('SUM(f.'.implode(' OR f.', self::ALERTS).') AS flagged');
        foreach (self::ALERTS as $alert) {
            $query->selectRaw("SUM(f.$alert) AS $alert");
        }

        return $query->get()
            ->mapWithKeys(fn ($r) => [(string) $r->employee_id => array_map('intval', array_diff_key((array) $r, ['employee_id' => 1]))])
            ->all();
    }

    protected function alertFlags(array $filters): Builder
    {
        $now = now();

        $care = DB::table('CareResults')
            ->select('opportunity_id')
            ->selectRaw('MAX(performed_at) AS last_care')
            ->groupBy('opportunity_id');

        $history = DB::table('StagesHistory')
            ->select('opportunity_id')
            ->selectRaw('MAX(changed_at) AS last_move')
            ->selectRaw('MAX(IF(to_stage_id = ?, changed_at, NULL)) AS processed_at', [Stage::PROCESSED])
            ->groupBy('opportunity_id');

        $appointments = DB::table('Appointments')
            ->select('opportunity_id')
            ->selectRaw("SUM(status = 'Scheduled' AND scheduled_time < ?) AS overdue", [$now])
            ->selectRaw("SUM(status = 'Scheduled' AND scheduled_time >= ?) AS upcoming", [$now])
            ->selectRaw('MAX(IF(scheduled_time <= ?, scheduled_time, NULL)) AS last_appt', [$now])
            ->groupBy('opportunity_id');

        $quotes = DB::table('Quotations')
            ->select('opportunity_id')
            ->selectRaw('COUNT(*) AS quotes')
            ->groupBy('opportunity_id');

        return $this->current($filters)
            ->leftJoinSub($care, 'c', 'c.opportunity_id', '=', 'o.opportunity_id')
            ->leftJoinSub($history, 'h', 'h.opportunity_id', '=', 'o.opportunity_id')
            ->leftJoinSub($appointments, 'a', 'a.opportunity_id', '=', 'o.opportunity_id')
            ->leftJoinSub($quotes, 'q', 'q.opportunity_id', '=', 'o.opportunity_id')
            ->select('o.employee_id', 'o.opportunity_id')
            ->selectRaw('COALESCE(a.overdue, 0) > 0 AS overdue_appointment')
            ->selectRaw('(o.stage_id = ? AND COALESCE(q.quotes, 0) = 0) AS needs_quotation', [Stage::CLOSED])
            ->selectRaw(
                '(o.stage_id = ? AND DATE_ADD((
                    SELECT MIN(cr.performed_at) FROM CareResults cr
                    WHERE cr.opportunity_id = o.opportunity_id AND cr.activity_id = ?
                      AND cr.performed_at >= COALESCE(h.processed_at, o.conversion_date)
                ), INTERVAL ? DAY) <= ?) AS decision_due',
                [Stage::PROCESSED, CareActivity::DECISION, Opportunity::DECISION_DAYS, $now->copy()->addDays(self::DECISION_WARN_DAYS)]
            )
            ->selectRaw(
                '(o.stage_id <> ? AND COALESCE(a.upcoming, 0) = 0 AND GREATEST(o.conversion_date,
                    COALESCE(c.last_care, o.conversion_date), COALESCE(h.last_move, o.conversion_date),
                    COALESCE(a.last_appt, o.conversion_date)) < ?) AS stale',
                [Stage::CLOSED, $now->copy()->subDays(self::STALE_DAYS)]
            );
    }

    public const RECORDS_ASSIGNED = [
        'assigned'         => '1 = 1',
        'status_won'       => "o.status = 'Won'",
        'status_progress'  => "o.status <> 'Won' AND o.contacts > 0 AND o.contacts_ok > 0",
        'status_unreached' => "o.status <> 'Won' AND o.contacts > 0 AND o.contacts_ok = 0",
        'status_new'       => "o.status <> 'Won' AND o.contacts = 0",
        'contacted'        => 'o.contacts > 0',
        'response'         => 'o.response_hours IS NOT NULL',
    ];

    public const RECORDS_CLOSED = ['closed' => ['Won', 'Lost'], 'won' => ['Won'], 'lost' => ['Lost']];

    public function records(string $kind, array $filters): array
    {
        $rows = match (true) {
            isset(self::RECORDS_ASSIGNED[$kind]) => $this->withCustomer(
                $this->scoped($this->facts(), $filters)->whereRaw('('.self::RECORDS_ASSIGNED[$kind].')')
            )->addSelect('o.conversion_date as at', 'x.status', 'o.contacts', 'o.response_hours')->get(),
            isset(self::RECORDS_CLOSED[$kind]) => $this->withCustomer(
                $this->scoped($this->facts('closed_at')->whereIn('f.status', self::RECORDS_CLOSED[$kind]), $filters)
            )->addSelect('o.event_at as at', 'o.conversion_date as assigned_at', 'x.status', 'o.close_days')->get(),
            $kind === 'revenue' => $this->withCustomer($this->scoped(
                DB::table('SalesOrders as so')
                    ->join('Quotations as q', 'q.quotation_id', '=', 'so.quotation_id')
                    ->join('Opportunities as op', 'op.opportunity_id', '=', 'q.opportunity_id')
                    ->select('op.employee_id', 'op.opportunity_id', 'so.order_id', 'so.created_at as event_at', 'so.total_amount', 'so.paid_amount'),
                $filters
            ))->addSelect('o.event_at as at', 'o.order_id', 'o.total_amount', 'o.paid_amount')->get(),
            $kind === 'students' => $this->withCustomer($this->scoped(
                DB::table('Students as st')
                    ->join('Opportunities as op', 'op.opportunity_id', '=', 'st.opportunity_id')
                    ->select('op.employee_id', 'op.opportunity_id', 'st.student_id', 'st.full_name as student_name', 'st.conversion_date as event_at'),
                $filters
            ))->addSelect('o.event_at as at', 'o.student_id', 'o.student_name')->get(),
            $kind === 'care' => $this->careRecords($filters),
            $kind === 'appts' => $this->appointmentRecords($filters),
            $kind === 'activity' => $this->careRecords($filters)->concat($this->appointmentRecords($filters)),
            $kind === 'open' => $this->openRecords($this->current($filters)),
            $kind === 'uncontacted' => $this->openRecords($this->uncontactedQuery($filters)),
            $kind === 'flagged' || in_array($kind, self::ALERTS, true) => $this->openRecords($this->current($filters)
                ->joinSub($this->alertFlags($filters), 'f', 'f.opportunity_id', '=', 'o.opportunity_id')
                ->whereRaw($kind === 'flagged' ? '(f.'.implode(' OR f.', self::ALERTS).')' : "f.$kind = 1")),
            default => collect(),
        };

        return $rows->map(fn ($r) => (array) $r)->sortByDesc('at')->values()->all();
    }

    protected function careRecords(array $filters): Collection
    {
        $care = DB::table('CareResults')->select('employee_id', 'opportunity_id', 'performed_at as event_at', 'result');

        return $this->withCustomer($this->scoped($care, $filters))
            ->selectRaw("o.event_at AS at, 'care' AS activity, o.result AS result")->get();
    }

    protected function appointmentRecords(array $filters): Collection
    {
        $appointments = DB::table('Appointments')->whereIn('status', ['Success', 'Failed'])
            ->select('employee_id', 'opportunity_id', 'scheduled_time as event_at', 'status', 'appointment_type');

        return $this->withCustomer($this->scoped($appointments, $filters))
            ->selectRaw('o.event_at AS at, o.appointment_type AS activity, o.status AS result')->get();
    }

    protected function openRecords(Builder $query): Collection
    {
        return $this->withCustomer($query)->addSelect('o.conversion_date as at', 'x.status', 'x.expected_value')->get();
    }

    protected function withCustomer(Builder $query): Builder
    {
        return $query
            ->join('Opportunities as x', 'x.opportunity_id', '=', 'o.opportunity_id')
            ->join('Leads as l', 'l.lead_id', '=', 'x.lead_id')
            ->join('Stages as sg', 'sg.stage_id', '=', 'x.stage_id')
            ->leftJoin('SalesTeams as t', 't.team_id', '=', 'e.team_id')
            ->select('x.opportunity_id', 'l.lead_id', 'l.full_name as customer', 'l.source_name as source',
                'e.full_name as employee', 't.team_name as team', 'sg.stage_name as stage');
    }

    protected function current(array $filters): Builder
    {
        return DB::table('Opportunities as o')
            ->join('Employees as e', 'e.employee_id', '=', 'o.employee_id')
            ->where('o.status', Opportunity::STATUS_IN_PROGRESS)
            ->when($filters['branch'] ?? null, fn ($q, $branch) => $q->where('e.branch_id', $branch))
            ->when($filters['team'] ?? null, fn ($q, $team) => $q->where('e.team_id', $team))
            ->when($filters['employee'] ?? null, fn ($q, $employee) => $q->where('o.employee_id', $employee))

            ->when($filters['only_team'] ?? null, fn ($q, $team) => $q->where('e.team_id', $team))
            ->when($filters['only_employees'] ?? null, fn ($q, $ids) => $q->whereIn('o.employee_id', $ids));
    }

    protected function scoped(Builder $source, array $filters): Builder
    {
        return DB::query()
            ->fromSub($source, 'o')
            ->join('Employees as e', 'e.employee_id', '=', 'o.employee_id')
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('o.event_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('o.event_at', '<=', $to))
            ->when($filters['branch'] ?? null, fn ($q, $branch) => $q->where('e.branch_id', $branch))
            ->when($filters['team'] ?? null, fn ($q, $team) => $q->where('e.team_id', $team))
            ->when($filters['employee'] ?? null, fn ($q, $employee) => $q->where('o.employee_id', $employee))

            ->when($filters['only_team'] ?? null, fn ($q, $team) => $q->where('e.team_id', $team))
            ->when($filters['only_employees'] ?? null, fn ($q, $ids) => $q->whereIn('o.employee_id', $ids));
    }

    protected function facts(string $event = 'conversion_date'): Builder
    {
        $stages = $this->funnelStages();
        $first = $stages[0]['order'] ?? 1;
        $last = $stages ? end($stages)['order'] : 1;

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

        return DB::query()->fromSub($facts, 'f')
            ->select('f.*')
            ->selectRaw("f.$event AS event_at")
            ->selectRaw('GREATEST(0, TIMESTAMPDIFF(MINUTE, f.conversion_date, f.closed_at)) / 1440 AS close_days');
    }

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
        $row['avg_order'] = $row['orders'] ? round($row['revenue'] / $row['orders']) : null;
        $row['activity'] = $row['care'] + $row['appts'];
        $row['reach_rate'] = $pct($row['care_ok'], $row['care']);
        $row['show_rate'] = $pct($row['appts_ok'], $row['appts']);
        $row['contact_rate'] = $pct($row['contacted_ok'], $row['contacted']);
        $row['status_progress'] = max(0, $row['assigned'] - $row['status_won'] - $row['status_new'] - $row['status_unreached']);

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
