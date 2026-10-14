<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Thông báo Lead mới cho Sale Leader (hiển thị ở chuông thông báo trên topbar):
 * mỗi lần Sale Admin phân Lead cho đội = 1 thông báo (các Cơ hội tạo cùng lúc có chung conversion_date),
 * kèm số Lead của lần đó còn chờ phân cho Salesperson. Phân hết thì thông báo tự biến mất.
 * Không có bảng thông báo trong odin.sql nên suy ra trực tiếp từ Cơ hội trưởng nhóm đang giữ.
 */
class LeadAssignmentNotices
{
    // Số lần phân gần nhất hiển thị
    const LIMIT = 10;

    /**
     * @return Collection<int, array{title: string, time: string, url: string, at: Carbon, count: int}>
     */
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
