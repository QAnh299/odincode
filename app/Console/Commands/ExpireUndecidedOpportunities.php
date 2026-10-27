<?php

namespace App\Console\Commands;

use App\Models\Opportunity;
use App\Models\Stage;
use Illuminate\Console\Command;

/**
 * Kết thúc cơ hội quá hạn → chuyển sang Lưu trữ (Archived):
 *   - Data đã có lịch hẹn: quá 7 ngày kể từ buổi khách không đến mà chưa hẹn lại;
 *   - Data đã xử lý: quá hạn quyết định (14 ngày kể từ lần liên hệ "Chốt đăng ký" đầu tiên) mà khách chưa đồng ý.
 * Lên lịch hằng ngày trong routes/console.php.
 */
class ExpireUndecidedOpportunities extends Command
{
    protected $signature = 'opportunities:expire-undecided';

    protected $description = 'Kết thúc cơ hội quá 7 ngày chưa hẹn lại (Data đã có lịch hẹn) và quá hạn quyết định (Data đã xử lý)';

    public function handle(): int
    {
        $opportunities = Opportunity::query()
            ->whereIn('stage_id', [Stage::APPOINTED, Stage::PROCESSED])
            ->where('status', Opportunity::STATUS_IN_PROGRESS)
            ->get();

        $rebook = $opportunities->filter(fn (Opportunity $opportunity) => $opportunity->closeIfRebookOverdue())->count();
        $decision = $opportunities->filter(fn (Opportunity $opportunity) => $opportunity->closeIfDecisionOverdue())->count();

        $this->info("Đã kết thúc {$rebook} cơ hội quá hạn hẹn lại, {$decision} cơ hội quá hạn quyết định.");

        return self::SUCCESS;
    }
}
