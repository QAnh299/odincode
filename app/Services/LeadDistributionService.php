<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\SalesTeam;
use App\Models\Stage;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LeadDistributionService
{
    public const MAX_PER_SALESPERSON = 50;

    public const CRITERIA = ['revenue', 'close_rate', 'avg_close_days'];

    public const LOWER_IS_BETTER = ['avg_close_days'];

    public const MIN_SAMPLE = ['close_rate' => 15, 'avg_close_days' => 4];

    public const TOP = 2;

    public function __construct(protected SalesPerformanceReport $report)
    {
    }

    public function previousMonth(): array
    {
        $start = Carbon::now()->subMonthNoOverflow()->startOfMonth();

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString(), $start->format('m/Y')];
    }

    public function performance(string $dimension, ?string $teamId = null): array
    {
        [$from, $to] = $this->previousMonth();

        $rows = $this->report->aggregate(array_filter(['from' => $from, 'to' => $to, 'team' => $teamId]), $dimension);

        return array_map(fn ($row) => [
            'revenue'        => $row['revenue'],
            'close_rate'     => $row['close_rate'],
            'avg_close_days' => $row['avg_close_days'],
            'closed'         => $row['won'] + $row['lost'],
            'won'            => $row['close_n'],
        ], $rows);
    }

    public function sample(?array $performance, string $criterion): ?array
    {
        $min = self::MIN_SAMPLE[$criterion] ?? null;
        if ($min === null) {
            return null;
        }

        $count = (int) ($performance[$criterion === 'close_rate' ? 'closed' : 'won'] ?? 0);

        return [$count, $min];
    }

    public function score(?array $performance, string $criterion): ?float
    {
        $value = $performance[$criterion] ?? null;
        $sample = $this->sample($performance, $criterion);

        return $value === null || ($sample && $sample[0] < $sample[1]) ? null : (float) $value;
    }

    public function scoreboard(array $ids, array $performance): array
    {
        $board = array_fill_keys($ids, ['total' => 0]);

        foreach (self::CRITERIA as $criterion) {
            $lower = in_array($criterion, self::LOWER_IS_BETTER, true);
            $values = [];
            foreach ($ids as $id) {
                $value = $this->score($performance[$id] ?? null, $criterion);
                $values[$id] = $value === null ? -INF : ($lower ? -$value : $value);
            }

            foreach ($values as $id => $value) {
                $rank = 1 + count(array_filter($values, fn ($other) => $other > $value));
                $board[$id][$criterion] = $rank;
                $board[$id]['total'] += $rank;
            }
        }

        return $board;
    }

    public function rank(array $ids, array $performance, array $load = []): array
    {
        $board = $this->scoreboard($ids, $performance);

        usort($ids, fn ($a, $b) => [$board[$a]['total'], $load[$a] ?? 0, $a] <=> [$board[$b]['total'], $load[$b] ?? 0, $b]);

        return $ids;
    }

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

    public function capacity(string $employeeId, array $load): int
    {
        return max(0, self::MAX_PER_SALESPERSON - ($load[$employeeId] ?? 0));
    }

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

    public function capAuto(array $plan, array $load, array $rankedIds): array
    {
        [$plan, $over] = $this->excess($plan, $load);

        $turn = 0;
        while ($over > 0) {
            $open = array_values(array_filter(
                $rankedIds,
                fn ($id) => ($plan[$id] ?? 0) < $this->capacity($id, $load),
            ));

            $top = array_slice($open ?: $rankedIds, 0, self::TOP);
            if (! $top) {
                break;
            }

            $plan[$top[$turn++ % count($top)]]++;
            $over--;
        }

        return $plan;
    }

    public function overLimit(array $plan, array $load): array
    {
        $result = [];
        foreach ($plan as $id => $add) {
            $after = ($load[$id] ?? 0) + $add;
            if ($add > 0 && $after > self::MAX_PER_SALESPERSON) {
                $result[$id] = [
                    'load'  => $load[$id] ?? 0,
                    'add'   => $add,
                    'after' => $after,
                    'over'  => $after - self::MAX_PER_SALESPERSON,
                ];
            }
        }

        return $result;
    }

    public function availableLeads(string $branchId, ?string $source = null, ?string $method = null): Builder
    {
        return Lead::query()
            ->where('status', Lead::STATUS_NEW)
            ->where('branch_id', $branchId)
            ->whereDoesntHave('opportunities', fn ($q) => $q->where('status', Opportunity::STATUS_IN_PROGRESS))
            ->when($source, fn ($q) => $q->where('source_name', $source))
            ->when($method, fn ($q) => $q->where('contact_method', $method))
            ->orderBy('created_at')
            ->orderBy('lead_id');
    }

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

    public function previousOwners(array $leadIds): array
    {
        if (! $leadIds) {
            return [];
        }

        return Opportunity::query()
            ->whereIn('lead_id', $leadIds)
            ->where('status', '!=', Opportunity::STATUS_IN_PROGRESS)
            ->with(['employee:employee_id,team_id', 'employee.team:team_id,team_leader_id', 'employee.ledTeam:team_id,team_leader_id'])
            ->orderByDesc('conversion_date')
            ->orderByDesc('opportunity_id')
            ->get(['opportunity_id', 'lead_id', 'employee_id', 'conversion_date'])
            ->unique('lead_id')
            ->mapWithKeys(fn (Opportunity $o) => [$o->lead_id => [
                'employee_id' => $o->employee_id,
                'leader_id'   => $o->employee?->ledTeam?->team_leader_id ?? $o->employee?->team?->team_leader_id,
            ]])
            ->all();
    }

    public function matchPreferred(array $preferred, array $plan, array $families = []): array
    {
        $remaining = $plan;
        $groups = array_fill_keys(array_keys($plan), []);
        $familyOwner = [];
        $rest = [];

        $take = function (string $key, string $owner) use (&$groups, &$remaining, &$familyOwner, $families) {
            $groups[$owner][] = $key;
            $remaining[$owner]--;
            if (isset($families[$key])) {
                $familyOwner[$families[$key]] ??= $owner;
            }
        };

        foreach ($preferred as $key => $owner) {
            if ($owner !== null && ($remaining[$owner] ?? 0) > 0) {
                $take((string) $key, $owner);
            } else {
                $rest[] = (string) $key;
            }
        }

        foreach ($rest as $key) {
            $owner = $familyOwner[$families[$key] ?? ''] ?? null;

            if ($owner === null || $remaining[$owner] <= 0) {
                $owner = array_key_first(array_filter($remaining, fn ($n) => $n > 0));
            }

            $take($key, $owner);
        }

        return $groups;
    }

    public function relatedLeads(array $leadIds): array
    {
        $leads = Lead::query()->whereIn('lead_id', $leadIds)->get(['lead_id', 'phone', 'email']);
        $phones = $leads->pluck('phone')->filter()->unique()->values()->all();
        $emails = $leads->pluck('email')->filter()->map(fn ($e) => Str::lower($e))->unique()->values()->all();

        if (! $phones && ! $emails) {
            return [];
        }

        $byContact = [];
        $add = function (?string $type, ?string $value, string $leadId) use (&$byContact) {
            if ($value) {
                $byContact[$type.':'.Str::lower($value)][$leadId] = true;
            }
        };

        $contactFilter = fn ($q) => $q->whereIn('phone', $phones)->orWhereIn('email', $emails);

        Lead::query()->where($contactFilter)->get(['lead_id', 'phone', 'email'])
            ->each(function (Lead $lead) use ($add) {
                $add('p', $lead->phone, $lead->lead_id);
                $add('e', $lead->email, $lead->lead_id);
            });

        Student::query()->where($contactFilter)->get(['student_id', 'phone', 'email', 'lead_id'])
            ->each(function (Student $student) use ($add) {
                if ($leadId = $student->lead_id) {
                    $add('p', $student->phone, $leadId);
                    $add('e', $student->email, $leadId);
                }
            });

        $result = [];
        foreach ($leads as $lead) {
            $related = array_keys(($byContact['p:'.$lead->phone] ?? []) + ($byContact['e:'.Str::lower((string) $lead->email)] ?? []));
            $related = array_values(array_diff($related, [$lead->lead_id]));
            if ($related) {
                $result[$lead->lead_id] = $related;
            }
        }

        return $result;
    }

    public function familiesIn(array $leadIds, ?array $related = null): array
    {
        $related ??= $this->relatedLeads($leadIds);
        $families = [];

        foreach ($related as $leadId => $others) {
            $inBatch = array_intersect($others, $leadIds);
            if ($inBatch) {
                $families[$leadId] = min([$leadId, ...$inBatch]);
            }
        }

        return $families;
    }

    public function suggestedOwners(array $leadIds, ?array $related = null): array
    {
        $suggested = array_map(fn ($owner) => $owner + ['reason' => 'returning', 'related' => null], $this->previousOwners($leadIds));

        $related ??= $this->relatedLeads($leadIds);
        $relatedIds = array_unique(array_merge([], ...array_values($related)));

        if (! $relatedIds) {
            return $suggested;
        }

        $latest = Opportunity::query()
            ->whereIn('lead_id', $relatedIds)
            ->with(['employee:employee_id,team_id', 'employee.team:team_id,team_leader_id', 'employee.ledTeam:team_id,team_leader_id'])
            ->orderByDesc('conversion_date')
            ->orderByDesc('opportunity_id')
            ->get(['opportunity_id', 'lead_id', 'employee_id', 'conversion_date'])
            ->unique('lead_id')
            ->keyBy('lead_id');

        foreach ($related as $leadId => $others) {
            if (isset($suggested[$leadId])) {
                continue;
            }

            $opportunity = collect($others)->map(fn ($id) => $latest[$id] ?? null)->filter()
                ->sortByDesc(fn (Opportunity $o) => $o->conversion_date.'|'.$o->opportunity_id)
                ->first();

            if ($opportunity) {
                $suggested[$leadId] = [
                    'employee_id' => $opportunity->employee_id,
                    'leader_id'   => $opportunity->employee?->ledTeam?->team_leader_id ?? $opportunity->employee?->team?->team_leader_id,
                    'reason'      => 'family',
                    'related'     => $opportunity->lead_id,
                ];
            }
        }

        return $suggested;
    }

    public function branchTeams(string $branchId): Collection
    {
        return SalesTeam::query()
            ->whereHas('leader', fn ($q) => $q->where('branch_id', $branchId)->where('status', Employee::STATUS_WORKING))
            ->with('leader:employee_id,full_name')
            ->orderBy('team_id')
            ->get();
    }

    public function teamSalespersons(string $teamId): Collection
    {
        return Employee::query()
            ->where('team_id', $teamId)
            ->where('status', Employee::STATUS_WORKING)
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALESPERSON))
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'job_title']);
    }

    public function assignLeadToTeam(string $leadId, string $leaderId): void
    {
        DB::transaction(function () use ($leadId, $leaderId) {
            $lead = Lead::query()->whereKey($leadId)->unassigned()->lockForUpdate()->first();

            if (! $lead) {
                throw new RuntimeException(__('leads.assign_one.unavailable'));
            }
            if (! $this->branchTeams($lead->branch_id)->contains('team_leader_id', $leaderId)) {
                throw new RuntimeException(__('leads.assign_one.invalid_team'));
            }

            Opportunity::insert([
                'opportunity_id'  => Opportunity::nextIds(1)[0],
                'conversion_date' => Carbon::now(),
                'status'          => Opportunity::STATUS_IN_PROGRESS,
                'lead_id'         => $lead->lead_id,
                'stage_id'        => Stage::UNTOUCHED,
                'employee_id'     => $leaderId,
            ]);
        });

        Lead::touchStats();
    }

    public function assignLeadToSalesperson(string $leaderId, string $leadId, string $employeeId): void
    {
        DB::transaction(function () use ($leaderId, $leadId, $employeeId) {
            $opportunity = $this->pendingOpportunities($leaderId)->where('lead_id', $leadId)->lockForUpdate()->first();

            if (! $opportunity) {
                throw new RuntimeException(__('leads.assign_one.unavailable'));
            }

            $team = SalesTeam::query()->where('team_leader_id', $leaderId)->first();
            if (! $team || ! $this->teamSalespersons($team->team_id)->contains('employee_id', $employeeId)) {
                throw new RuntimeException(__('leads.assign_one.invalid_person'));
            }

            $opportunity->update(['employee_id' => $employeeId, 'conversion_date' => Carbon::now()]);
            Lead::whereKey($leadId)->update(['status' => Lead::STATUS_CONVERTED]);
        });
    }

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

            $related = $this->relatedLeads($leadIds);
            $owners = $this->suggestedOwners($leadIds, $related);
            $groups = $this->matchPreferred(
                array_combine($leadIds, array_map(fn ($id) => $owners[$id]['leader_id'] ?? null, $leadIds)),
                $plan,
                $this->familiesIn($leadIds, $related),
            );

            $ids = Opportunity::nextIds($total);
            $now = Carbon::now();
            $rows = [];
            $i = 0;
            foreach ($groups as $leaderId => $groupLeadIds) {
                foreach ($groupLeadIds as $leadId) {
                    $rows[] = [
                        'opportunity_id'  => $ids[$i++],
                        'conversion_date' => $now,
                        'status'          => 'InProgress',
                        'lead_id'         => $leadId,
                        'stage_id'        => Stage::UNTOUCHED,
                        'employee_id'     => $leaderId,
                    ];
                }
            }

            Opportunity::insert($rows);
            Lead::touchStats();

            return $total;
        });
    }

    public function assignToSalespersons(string $leaderId, array $plan, ?string $source, ?string $method): int
    {
        $plan = array_filter($plan);
        $total = array_sum($plan);

        return DB::transaction(function () use ($leaderId, $plan, $total, $source, $method) {
            $oppLeads = $this->pendingOpportunities($leaderId, $source, $method)
                ->lockForUpdate()
                ->limit($total)
                ->pluck('lead_id', 'opportunity_id')
                ->all();

            if (count($oppLeads) < $total) {
                throw new RuntimeException(__('leads.distribute.exceed'));
            }

            $leadIds = array_values($oppLeads);
            $related = $this->relatedLeads($leadIds);
            $owners = $this->suggestedOwners($leadIds, $related);
            $families = $this->familiesIn($leadIds, $related);
            $groups = $this->matchPreferred(
                array_map(fn ($leadId) => $owners[$leadId]['employee_id'] ?? null, $oppLeads),
                $plan,
                array_filter(array_map(fn ($leadId) => $families[$leadId] ?? null, $oppLeads)),
            );

            $now = Carbon::now();
            foreach ($groups as $employeeId => $oppIds) {
                if ($oppIds) {
                    Opportunity::whereIn('opportunity_id', $oppIds)
                        ->update(['employee_id' => $employeeId, 'conversion_date' => $now]);
                }
            }

            Lead::whereIn('lead_id', array_values($oppLeads))
                ->update(['status' => Lead::STATUS_CONVERTED]);
            Lead::touchStats();

            return $total;
        });
    }
}
