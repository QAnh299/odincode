<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Thông báo hóa đơn ở chuông trên topbar, suy ra từ dữ liệu (odin.sql không có bảng thông báo):
 *   - Kế toán: hóa đơn TIỀN MẶT đang Chờ thanh toán = hàng đợi cần đối chiếu và duyệt (HĐ30 – HĐ32);
 *     và hóa đơn vừa thanh toán thành công (HĐ33: "thông báo SP và kế toán").
 *   - Salesperson: hóa đơn mình lập vừa thanh toán thành công (chuyển khoản do hệ thống xác nhận / tiền mặt do kế toán duyệt).
 * "Vừa thanh toán" = ngày thanh toán từ đầu hôm qua tới nay (RECENT_DAYS); hóa đơn chuyển khoản không cần kế toán duyệt.
 */
class InvoiceNotices
{
    const RECENT_DAYS = 1;

    const LIMIT = 10;

    /**
     * @return Collection<int, array{title: string, time: string, url: string, at: Carbon}>
     */
    public static function for(?Employee $employee): Collection
    {
        return match ($employee?->role_name) {
            Role::ACCOUNTANT  => self::cashQueue()->concat(self::recentlyPaid(null, Role::ACCOUNTANT)),
            Role::SALESPERSON => self::recentlyPaid($employee->employee_id, Role::SALESPERSON),
            default           => collect(),
        };
    }

    /**
     * Số hóa đơn tiền mặt đang chờ kế toán duyệt (dùng cho dòng mô tả của chuông).
     */
    public static function cashQueueCount(): int
    {
        return Invoice::query()
            ->where('status', Invoice::STATUS_UNPAID)
            ->where('payment_method', Invoice::METHOD_CASH)
            ->count();
    }

    /**
     * HĐ30: hóa đơn tiền mặt Chờ thanh toán, cũ nhất trước (kế toán xử lý theo thứ tự gửi).
     */
    private static function cashQueue(): Collection
    {
        return Invoice::query()
            ->where('status', Invoice::STATUS_UNPAID)
            ->where('payment_method', Invoice::METHOD_CASH)
            ->with(['order.quotation.opportunity.lead:lead_id,full_name', 'createdBy:employee_id,full_name'])
            ->orderBy('issued_date')
            ->orderBy('invoice_id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'title' => __('invoices.notice.cash_pending', [
                    'code'   => $invoice->invoice_id,
                    'name'   => $invoice->order?->quotation?->opportunity?->lead?->full_name ?? '—',
                    'amount' => self::money($invoice->payment_amount),
                ]),
                'time'  => __('invoices.notice.cash_pending_time', [
                    'date' => $invoice->issued_date?->format('d/m/Y'),
                    'by'   => $invoice->createdBy?->full_name ?? '—',
                ]),
                'url'   => route('accountant.invoices.show', $invoice),
                // Hàng đợi xếp lên đầu chuông
                'at'    => Carbon::create(1970),
            ]);
    }

    /**
     * HĐ33: hóa đơn vừa thanh toán thành công. $creatorId = null → tất cả (kế toán); có giá trị → hóa đơn SP đó lập.
     */
    private static function recentlyPaid(?string $creatorId, string $role): Collection
    {
        $prefix = $role === Role::ACCOUNTANT ? 'accountant' : 'salesperson';

        return Invoice::query()
            ->where('status', Invoice::STATUS_PAID)
            ->where('payment_date', '>=', today()->subDays(self::RECENT_DAYS))
            ->when($creatorId, fn ($q) => $q->where('created_by_employee_id', $creatorId))
            ->with(['order.quotation.opportunity.lead:lead_id,full_name', 'accountant:employee_id,full_name'])
            ->orderByDesc('payment_date')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'title' => __('invoices.notice.paid', [
                    'code'   => $invoice->invoice_id,
                    'name'   => $invoice->order?->quotation?->opportunity?->lead?->full_name ?? '—',
                    'amount' => self::money($invoice->payment_amount),
                ]),
                'time'  => ($invoice->payment_method === Invoice::METHOD_CASH
                        ? __('invoices.notice.by_accountant', ['name' => $invoice->accountant?->full_name ?? '—'])
                        : __('invoices.notice.by_system'))
                    .' · '.$invoice->payment_date->format('d/m H:i'),
                'url'   => route($prefix.'.invoices.show', $invoice),
                'at'    => $invoice->payment_date,
            ]);
    }

    private static function money($value): string
    {
        return number_format((float) $value, 0, ',', '.').' ₫';
    }
}
