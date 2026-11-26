<?php

namespace App\Services;

use App\Models\Stage;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class LeadConversionReport
{
    public const OUTCOME_NEW = 'new';
    public const OUTCOME_OPEN = 'open';
    public const OUTCOME_WON = 'won';
    public const OUTCOME_LOST = 'lost';
    public const OUTCOME_ARCHIVED = 'archived';

    public const OUTCOMES = [self::OUTCOME_WON, self::OUTCOME_OPEN, self::OUTCOME_LOST, self::OUTCOME_ARCHIVED, self::OUTCOME_NEW];

    public const STUCK_DAYS = [
        Stage::UNTOUCHED => 2,
        Stage::APPOINTED => 7,
        Stage::PROCESSED => 14,
    ];

    public const STAGE_ESTIMATES = [
        Stage::APPOINTED => 'first_ok',
        Stage::PROCESSED => 'first_appt_ok',
        Stage::CLOSED    => 'won_at',
    ];

    public const CHUNK = 1000;

    protected ?array $stages = null;

    public function funnelStages(): array
    {
        return $this->stages ??= app(SalesPerformanceReport::class)->funnelStages();
    }

    public function leads(array $filters): array
    {
        if (($filters['team'] ?? null) === '__none__' || ($filters['employee'] ?? null) === '__none__') {
            return [];
        }

        $leads = $this->leadQuery($filters)
            ->select('l.lead_id', 'l.full_name', 'l.created_at', 'l.source_name', 'l.contact_method', 'l.branch_id')
            ->orderBy('l.created_at')
            ->get();

        $opportunities = $this->opportunityQuery($this->leadQuery($filters)->select('l.lead_id'))
            ->get()
            ->groupBy('lead_id');

        $facts = [];
        foreach ($leads as $lead) {
            $fact = $this->fact($lead, $opportunities[$lead->lead_id] ?? collect());

            if (($filters['employee'] ?? null) && $fact['employee'] !== $filters['employee']) {
                continue;
            }
            if (($filters['team'] ?? null) && $fact['team'] !== $filters['team']) {
                continue;
            }

            $facts[] = $fact;
        }

        return $facts;
    }

    public function contacts(array $opportunityIds): array
    {
        $rows = [];
        foreach (array_chunk($opportunityIds, self::CHUNK) as $chunk) {
            foreach (DB::table('CareResults')->whereIn('opportunity_id', $chunk)
                ->orderBy('performed_at')
                ->get(['result_id', 'opportunity_id', 'activity_id', 'result', 'performed_at', 'employee_id', 'notes']) as $row) {
                $rows[] = [
                    'id'          => $row->result_id,
                    'opportunity' => $row->opportunity_id,
                    'activity'    => $row->activity_id,
                    'result'      => $row->result,
                    'at'          => strtotime($row->performed_at),
                    'employee'    => $row->employee_id,
                    'notes'       => $row->notes,
                ];
            }
        }

        return $rows;
    }

    public function appointments(array $opportunityIds): array
    {
        $rows = [];
        foreach (array_chunk($opportunityIds, self::CHUNK) as $chunk) {
            foreach (DB::table('Appointments')->whereIn('opportunity_id', $chunk)
                ->orderBy('scheduled_time')
                ->get(['appointment_id', 'appointment_type', 'status', 'scheduled_time', 'opportunity_id', 'employee_id', 'notes']) as $row) {
                $rows[] = [
                    'id'          => $row->appointment_id,
                    'type'        => $row->appointment_type,
                    'status'      => $row->status,
                    'at'          => strtotime($row->scheduled_time),
                    'opportunity' => $row->opportunity_id,
                    'employee'    => $row->employee_id,
                    'notes'       => $row->notes,
                ];
            }
        }

        return $rows;
    }

    public function summarize(array $facts): array
    {
        $count = array_count_values(array_column($facts, 'outcome'));
        $leads = count($facts);
        $won = $count[self::OUTCOME_WON] ?? 0;
        $closed = $won + ($count[self::OUTCOME_LOST] ?? 0) + ($count[self::OUTCOME_ARCHIVED] ?? 0);
        $opportunities = $leads - ($count[self::OUTCOME_NEW] ?? 0);

        $reached = [];
        $processed = 0;
        foreach ($this->funnelStages() as $stage) {
            $reached[$stage['order']] = count(array_filter($facts, fn ($f) => $f['reached'] >= $stage['order']));
            if ($stage['id'] === Stage::PROCESSED) {
                $processed = $reached[$stage['order']];
            }
        }

        $response = [];
        $close = [];
        $sums = ['contacted' => 0, 'students' => 0, 'contacts' => 0, 'contacts_ok' => 0, 'appointments_ok' => 0, 'appointments_failed' => 0];
        foreach ($facts as $f) {
            if ($f['first_contact']) {
                $sums['contacted']++;
                $response[] = max(0, $f['first_contact'] - $f['created']) / 3600;
            }
            if ($f['won_at']) {
                $close[] = max(0, $f['won_at'] - $f['created']) / 86400;
            }
            $sums['students'] += (int) $f['student'];
            $sums['contacts'] += $f['contacts'];
            $sums['contacts_ok'] += $f['contacts_ok'];
            $sums['appointments_ok'] += $f['appointments_ok'];
            $sums['appointments_failed'] += $f['appointments_failed'];
        }

        $row = $sums + [
            'leads'         => $leads,
            'opportunities' => $opportunities,
            'closed'        => $closed,
            'reached'       => $reached,
        ];
        foreach (self::OUTCOMES as $outcome) {
            $row[$outcome] = $count[$outcome] ?? 0;
        }

        return $row + [
            'conversion_rate'  => self::pct($opportunities, $leads),
            'win_rate'         => self::pct($won, $leads),
            'close_rate'       => self::pct($won, $closed),
            'contact_rate'     => self::pct($sums['contacted'], $leads),
            'student_rate'     => self::pct($sums['students'], $won),
            'processed_rate'   => self::pct($processed, $leads),
            'appointment_rate' => self::pct($sums['appointments_ok'], $sums['appointments_ok'] + $sums['appointments_failed']),
            'response_avg'     => self::avg($response),
            'response_median'  => self::percentile($response, 50),
            'close_avg'        => self::avg($close),
            'close_median'     => self::percentile($close, 50),
        ];
    }

    public function stuck(array $facts, ?int $now = null): array
    {
        $now ??= time();
        $rows = [];
        foreach ($facts as $f) {
            $limit = self::STUCK_DAYS[$f['stage']] ?? null;
            if ($f['outcome'] !== self::OUTCOME_OPEN || $limit === null || ! $f['stage_since']) {
                continue;
            }
            $days = ($now - $f['stage_since']) / 86400;
            if ($days > $limit) {
                $rows[] = $f + ['days' => $days, 'limit' => $limit, 'overdue' => $days - $limit];
            }
        }
        usort($rows, fn ($a, $b) => $b['overdue'] <=> $a['overdue']);

        return $rows;
    }

    public static function pct(float|int $part, float|int $total): ?float
    {
        return $total ? round($part * 100 / $total, 1) : null;
    }

    public static function avg(array $values): ?float
    {
        return $values ? round(array_sum($values) / count($values), 1) : null;
    }

    public static function percentile(array $values, float $p): ?float
    {
        if (! $values) {
            return null;
        }
        sort($values);
        $index = ($p / 100) * (count($values) - 1);
        $low = (int) floor($index);
        $high = (int) ceil($index);

        return round($values[$low] + ($values[$high] - $values[$low]) * ($index - $low), 1);
    }

    protected function leadQuery(array $filters): Builder
    {
        $team = $filters['team'] ?? null;
        $employee = $filters['employee'] ?? null;

        return DB::table('Leads as l')
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('l.created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('l.created_at', '<=', $to))
            ->when($filters['branch'] ?? null, fn ($q, $branch) => $q->where('l.branch_id', $branch))
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('l.source_name', $source))
            ->when($team || $employee, fn ($q) => $q->whereExists(fn ($sub) => $sub
                ->from('Opportunities as so')
                ->join('Employees as se', 'se.employee_id', '=', 'so.employee_id')
                ->whereColumn('so.lead_id', 'l.lead_id')
                ->when($team, fn ($s) => $s->where('se.team_id', $team))
                ->when($employee, fn ($s) => $s->where('so.employee_id', $employee))));
    }

    protected function opportunityQuery(Builder $leadIds): Builder
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
            ->selectRaw("MIN(IF(result = 'Success', performed_at, NULL)) AS first_ok")
            ->groupBy('opportunity_id');

        $history = DB::table('StagesHistory as h')
            ->join('Stages as fs', 'fs.stage_id', '=', 'h.from_stage_id')
            ->join('Stages as ts', 'ts.stage_id', '=', 'h.to_stage_id')
            ->select('h.opportunity_id')
            ->selectRaw('MAX(GREATEST('.$inFunnel('fs.sort_order').', '.$inFunnel('ts.sort_order').')) AS reached')
            ->selectRaw("MIN(IF(ts.sort_order > $last, h.changed_at, NULL)) AS archived_at")
            ->selectRaw('MAX(h.changed_at) AS last_move')
            ->groupBy('h.opportunity_id');
        foreach ($stages as $stage) {
            $history->selectRaw("MIN(IF(ts.sort_order = {$stage['order']}, h.changed_at, NULL)) AS entered_{$stage['order']}");
        }

        $appointments = DB::table('Appointments')
            ->select('opportunity_id')
            ->selectRaw("SUM(status = 'Success') AS appointments_ok")
            ->selectRaw("SUM(status = 'Failed') AS appointments_failed")
            ->selectRaw("MIN(IF(status = 'Success', scheduled_time, NULL)) AS first_appt_ok")
            ->groupBy('opportunity_id');

        $quotes = DB::table('Quotations')
            ->select('opportunity_id')
            ->selectRaw('MIN(created_at) AS first_quote')
            ->groupBy('opportunity_id');

        $query = DB::table('Opportunities as o')
            ->joinSub($leadIds, 'fl', 'fl.lead_id', '=', 'o.lead_id')
            ->join('Stages as s', 's.stage_id', '=', 'o.stage_id')
            ->leftJoin('Employees as e', 'e.employee_id', '=', 'o.employee_id')
            ->leftJoin('Students as st', 'st.opportunity_id', '=', 'o.opportunity_id')
            ->leftJoinSub($care, 'c', 'c.opportunity_id', '=', 'o.opportunity_id')
            ->leftJoinSub($history, 'hs', 'hs.opportunity_id', '=', 'o.opportunity_id')
            ->leftJoinSub($appointments, 'ap', 'ap.opportunity_id', '=', 'o.opportunity_id')
            ->leftJoinSub($quotes, 'qt', 'qt.opportunity_id', '=', 'o.opportunity_id')
            ->select(
                'o.opportunity_id',
                'o.lead_id',
                'o.employee_id',
                'o.conversion_date',
                'o.status',
                'o.stage_id',
                's.stage_name',
                's.sort_order',
                'e.full_name as employee_name',
                'e.team_id',
                'c.first_contact',
                'c.last_contact',
                'c.first_ok',
                'ap.first_appt_ok',
                'hs.last_move',
                'hs.archived_at'
            )
            ->selectRaw('st.student_id IS NOT NULL AS student')
            ->selectRaw('COALESCE(c.contacts, 0) AS contacts')
            ->selectRaw('COALESCE(c.contacts_ok, 0) AS contacts_ok')
            ->selectRaw('COALESCE(ap.appointments_ok, 0) AS appointments_ok')
            ->selectRaw('COALESCE(ap.appointments_failed, 0) AS appointments_failed')
            ->selectRaw("GREATEST($first, ".$inFunnel('s.sort_order').", COALESCE(hs.reached, 0), IF(o.status = 'Won', $last, 0)) AS reached")
            ->selectRaw("IF(o.status = 'Won', COALESCE(hs.entered_$last, qt.first_quote, c.last_contact, o.conversion_date), NULL) AS won_at");
        foreach ($stages as $stage) {
            $query->addSelect("hs.entered_{$stage['order']}");
        }

        return $query;
    }

    protected function fact(object $lead, $opportunities): array
    {
        $ts = fn ($value) => $value ? strtotime($value) : null;
        $minOf = fn (array $values) => ($values = array_filter($values)) ? min($values) : null;

        $fact = [
            'id'          => $lead->lead_id,
            'name'        => $lead->full_name,
            'created'     => strtotime($lead->created_at),
            'source'      => $lead->source_name,
            'method'      => $lead->contact_method,
            'branch'      => $lead->branch_id,
            'outcome'     => self::OUTCOME_NEW,
            'opportunity' => null,
            'opportunities' => [],
            'employee'    => null,
            'employee_name' => null,
            'team'        => null,
            'converted_at' => null,
            'reached'     => 0,
            'won_at'      => null,
            'first_contact' => null,
            'last_contact' => null,
            'contacts'    => 0,
            'contacts_ok' => 0,
            'appointments_ok' => 0,
            'appointments_failed' => 0,
            'student'     => false,
            'stage'       => null,
            'stage_name'  => null,
            'stage_since' => null,
            'entries'     => [],
        ];

        if ($opportunities->isEmpty()) {
            return $fact;
        }

        $latest = $opportunities->sortByDesc(fn ($o) => $o->conversion_date.'|'.$o->opportunity_id)->first();
        $statuses = $opportunities->pluck('status')->all();
        $outcome = match (true) {
            in_array('Won', $statuses, true)        => self::OUTCOME_WON,
            in_array('InProgress', $statuses, true) => self::OUTCOME_OPEN,
            $latest->status === 'Lost'              => self::OUTCOME_LOST,
            default                                 => self::OUTCOME_ARCHIVED,
        };

        $main = $outcome === self::OUTCOME_WON
            ? $opportunities->where('status', 'Won')->sortBy('won_at')->first()
            : $latest;

        $entries = [];
        $previous = null;
        foreach ($this->funnelStages() as $i => $stage) {
            $order = $stage['order'];
            $estimate = self::STAGE_ESTIMATES[$stage['id']] ?? null;
            $at = match (true) {
                $i === 0                   => $ts($main->conversion_date),
                $main->reached < $order    => null,
                default                    => $ts($main->{'entered_'.$order} ?? null)
                    ?? ($estimate ? $ts($main->$estimate ?? null) : null),
            };
            if ($at !== null && $previous !== null) {
                $at = max($at, $previous);
            }
            $entries[$order] = $at;
            $previous = $at ?? $previous;
        }

        return array_merge($fact, [
            'outcome'       => $outcome,
            'opportunity'   => $main->opportunity_id,
            'opportunities' => $opportunities->pluck('opportunity_id')->all(),
            'employee'      => $latest->employee_id,
            'employee_name' => $latest->employee_name,
            'team'          => $latest->team_id,
            'converted_at'  => $minOf($opportunities->map(fn ($o) => $ts($o->conversion_date))->all()),
            'reached'       => (int) $opportunities->max('reached'),
            'won_at'        => $minOf($opportunities->map(fn ($o) => $ts($o->won_at))->all()),
            'first_contact' => $minOf($opportunities->map(fn ($o) => $ts($o->first_contact))->all()),
            'last_contact'  => $opportunities->map(fn ($o) => $ts($o->last_contact))->filter()->max(),
            'contacts'      => (int) $opportunities->sum('contacts'),
            'contacts_ok'   => (int) $opportunities->sum('contacts_ok'),
            'appointments_ok' => (int) $opportunities->sum('appointments_ok'),
            'appointments_failed' => (int) $opportunities->sum('appointments_failed'),
            'student'       => $opportunities->contains(fn ($o) => (bool) $o->student),
            'stage'         => $main->stage_id,
            'stage_name'    => $main->stage_name,
            'stage_since'   => $ts($main->last_move) ?? $entries[(int) $main->sort_order] ?? $ts($main->conversion_date),
            'entries'       => $entries,
        ]);
    }
}
