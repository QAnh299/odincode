<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

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
