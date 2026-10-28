<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

/**
 * Chuyển hóa đơn Chờ thanh toán đã qua hạn sang Quá hạn (chốt 28/10/2026). Hóa đơn Quá hạn không nhận thanh toán;
 * Salesperson gia hạn (sửa hạn từ hôm nay trở đi) để mở lại chính hóa đơn đó – xem InvoiceService::update().
 * Lên lịch hằng ngày trong routes/console.php.
 */
class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Chuyển hóa đơn Chờ thanh toán đã qua hạn thanh toán sang Quá hạn';

    public function handle(): int
    {
        $count = Invoice::query()
            ->where('status', Invoice::STATUS_UNPAID)
            ->whereDate('due_date', '<', today())
            ->update(['status' => Invoice::STATUS_OVERDUE]);

        $this->info("Đã chuyển {$count} hóa đơn sang Quá hạn.");

        return self::SUCCESS;
    }
}
