<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Cảnh báo chuyển khoản lệch số tiền (chốt 18/10/2026): giao dịch có mã hóa đơn chuyển khoản đang Chờ thanh toán
 * nhưng sai số tiền thì hệ thống KHÔNG ghi nhận, chỉ báo lỗi cho kế toán và Salesperson lập hóa đơn.
 * Kế toán kiểm tra sao kê; thiếu thì SP nhắc khách chuyển đủ; đủ rồi kế toán xác nhận thủ công (InvoiceService::confirmTransfer).
 *
 * Lưu trong cache (bảng cache của Laravel) vì CSDL nghiệp vụ không có bảng giao dịch / thông báo; chỉ để báo lỗi,
 * không dùng để cộng tiền. Xóa khi hóa đơn được ghi nhận thanh toán.
 */
class TransferAlerts
{
    const KEY = 'invoices.transfer_alerts';

    const TTL_DAYS = 30;

    /**
     * Ghi lại lần chuyển lệch gần nhất của hóa đơn. $overdue = giao dịch vào hóa đơn đã Quá hạn (chờ SP gia hạn).
     */
    public static function add(string $invoiceId, float $amount, ?Carbon $at = null, bool $overdue = false): void
    {
        $alerts = self::all();
        $alerts[$invoiceId] = [
            'amount'  => $amount,
            'at'      => ($at ?? now())->toIso8601String(),
            'count'   => ($alerts[$invoiceId]['count'] ?? 0) + 1,
            'overdue' => $overdue,
        ];

        Cache::put(self::KEY, $alerts, now()->addDays(self::TTL_DAYS));
    }

    public static function forget(string $invoiceId): void
    {
        $alerts = self::all();
        if (isset($alerts[$invoiceId])) {
            unset($alerts[$invoiceId]);
            Cache::put(self::KEY, $alerts, now()->addDays(self::TTL_DAYS));
        }
    }

    /**
     * @return array{amount: float, at: Carbon, count: int, overdue: bool}|null
     */
    public static function find(string $invoiceId): ?array
    {
        $alert = self::all()[$invoiceId] ?? null;

        return $alert ? [
            'amount'  => (float) $alert['amount'],
            'at'      => Carbon::parse($alert['at']),
            'count'   => (int) $alert['count'],
            'overdue' => (bool) ($alert['overdue'] ?? false),
        ] : null;
    }

    /**
     * @return array<string, array{amount: float, at: string, count: int}>
     */
    public static function all(): array
    {
        return Cache::get(self::KEY, []);
    }
}
