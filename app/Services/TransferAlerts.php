<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class TransferAlerts
{
    public const KEY = 'invoices.transfer_alerts';

    public const TTL_DAYS = 30;

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

    public static function all(): array
    {
        return Cache::get(self::KEY, []);
    }
}
