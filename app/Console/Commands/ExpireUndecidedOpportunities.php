<?php

namespace App\Console\Commands;

use App\Models\Opportunity;
use App\Models\Stage;
use Illuminate\Console\Command;

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
