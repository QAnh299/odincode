<?php

namespace App\Console\Commands;

use App\Models\Opportunity;
use App\Models\Stage;
use Illuminate\Console\Command;

/**
 * HĐ23: cơ hội ở Data đã xử lý quá hạn quyết định (14 ngày kể từ lần liên hệ "Chốt đăng ký" đầu tiên) mà khách
 * chưa đồng ý → kết thúc: gọi được nhưng còn cân nhắc → Lost, không liên lạc được → Archived.
 * Lên lịch hằng ngày trong routes/console.php.
 */
class ExpireUndecidedOpportunities extends Command
{
    protected $signature = 'opportunities:expire-undecided';

    protected $description = 'Kết thúc cơ hội ở Data đã xử lý đã quá hạn quyết định (HĐ23)';

    public function handle(): int
    {
        $count = Opportunity::query()
            ->where('stage_id', Stage::PROCESSED)
            ->where('status', Opportunity::STATUS_IN_PROGRESS)
            ->get()
            ->filter(fn (Opportunity $opportunity) => $opportunity->closeIfDecisionOverdue())
            ->count();

        $this->info("Đã kết thúc {$count} cơ hội quá hạn quyết định.");

        return self::SUCCESS;
    }
}
