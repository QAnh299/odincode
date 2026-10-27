<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Quotation;
use App\Models\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Nhắc báo giá Nháp cho Salesperson lập báo giá (chuông thông báo trên topbar): hết hạn hôm nay hoặc ngày mai,
 * và báo giá đã quá hạn – nhắc mỗi ngày cho tới khi SP xử lý (gia hạn bằng Chỉnh sửa, xác nhận hoặc từ chối).
 * Báo giá quá hạn không tự đổi trạng thái (chốt 25/10/2026).
 * Không có bảng thông báo trong odin.sql nên suy ra trực tiếp từ báo giá (giống AppointmentReminders).
 */
class QuotationReminders
{
    // Nhắc khi còn 0 - REMIND_DAYS ngày tới hạn
    const REMIND_DAYS = 1;

    /**
     * @return Collection<int, array{title: string, time: string, url: string, at: Carbon}>
     */
    public static function for(?Employee $employee): Collection
    {
        if ($employee?->role_name !== Role::SALESPERSON) {
            return collect();
        }

        return Quotation::query()
            ->where('employee_id', $employee->employee_id)
            ->where('status', Quotation::STATUS_DRAFT)
            ->whereDate('expiry_date', '<=', today()->addDays(self::REMIND_DAYS)->toDateString())
            ->with('opportunity.lead:lead_id,full_name')
            ->orderBy('expiry_date')
            ->orderBy('quotation_id')
            ->get()
            ->map(function (Quotation $quotation) {
                $days = $quotation->daysUntilExpiry();
                $name = $quotation->opportunity?->lead?->full_name ?? '—';

                $key = match (true) {
                    $days < 0   => 'quotations.reminder.overdue',
                    $days === 0 => 'quotations.reminder.today',
                    default     => 'quotations.reminder.tomorrow',
                };

                return [
                    'title' => __($key, ['code' => $quotation->quotation_id, 'name' => $name, 'days' => abs($days)]),
                    'time'  => __('quotations.reminder.time', ['date' => $quotation->expiry_date->format('d/m/Y')]),
                    'url'   => route('salesperson.quotations.show', $quotation),
                    'at'    => $quotation->expiry_date->copy()->endOfDay(),
                ];
            });
    }
}
