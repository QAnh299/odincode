<?php

namespace App\Livewire\Opportunities;

use App\Models\Opportunity;
use App\Models\Stage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Quản lý Opportunity của Salesperson dạng Kanban (giống Odoo CRM):
 * mỗi cột là một Stage (theo sort_order), mỗi thẻ là một Opportunity do chính mình phụ trách.
 * Không hiển thị trạng thái vì mỗi stage đã tương ứng một trạng thái.
 * Hiện chỉ xem; chưa kéo thả đổi stage.
 */
#[Layout('layouts.app')]
class Kanban extends Component
{
    #[Computed]
    public function stages(): Collection
    {
        return Stage::query()->orderBy('sort_order')->get();
    }

    public function render()
    {
        $opportunities = Opportunity::query()
            ->where('employee_id', Auth::user()->employee?->employee_id)
            ->with('lead:lead_id,full_name,phone,email,source_name,contact_method')
            ->orderByDesc('conversion_date')
            ->get();

        return view('livewire.opportunities.kanban', [
            'columns' => $opportunities->groupBy('stage_id'),
            'total'   => $opportunities->count(),
        ])->title(__('opportunities.title'));
    }
}
