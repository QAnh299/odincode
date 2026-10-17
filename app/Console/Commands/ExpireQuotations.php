<?php

namespace App\Console\Commands;

use App\Services\QuotationService;
use Illuminate\Console\Command;

/**
 * HĐ15: báo giá Nháp quá hạn mà khách không phản hồi → hệ thống tự Từ chối;
 * cơ hội đang xử lý → Lost, cơ hội đã Won → giữ Won. Lên lịch hằng ngày trong routes/console.php.
 */
class ExpireQuotations extends Command
{
    protected $signature = 'quotations:expire';

    protected $description = 'Tự từ chối báo giá Nháp đã quá hạn (HĐ15)';

    public function handle(QuotationService $service): int
    {
        $count = $service->expireOverdue();

        $this->info("Đã từ chối {$count} báo giá quá hạn.");

        return self::SUCCESS;
    }
}
