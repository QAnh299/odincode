<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LeadAssignmentNotices
{
    public const LIMIT = 10;

    public static function for(?Employee $employee): Collection
    {
        $team = $employee?->role_name === Role::SALE_LEADER ? $employee->ledTeam : null;
        if (! $team) {
            return collect();
        }

        return app(LeadDistributionService::class)
            ->pendingOpportunities($employee->employee_id)
            ->reorder()
            ->groupBy('conversion_date')
            ->selectRaw('conversion_date, COUNT(*) AS total')
            ->orderByDesc('conversion_date')
            ->limit(self::LIMIT)
            ->toBase()
            ->get()
            ->map(function ($row) use ($team) {
                $at = Carbon::parse($row->conversion_date);

                return [
                    'title' => __('leads.notice.title', ['count' => (int) $row->total, 'team' => $team->team_name]),
                    'time'  => $at->format('d/m/Y H:i').' · '.$at->diffForHumans(),
                    'url'   => route('sale_leader.leads', ['distribute' => 1]),
                    'at'    => $at,
                    'count' => (int) $row->total,
                ];
            });
    }
}
