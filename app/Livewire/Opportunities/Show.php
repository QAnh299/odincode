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
 * Chi tiết Opportunity của Salesperson: thanh giai đoạn, thông tin khách hàng,
 * lịch sử chăm sóc, lịch hẹn và báo giá.
 * Chỉ xem được Opportunity do chính mình phụ trách.
 * Nút "Tạo báo giá" chỉ hiện ở stage Chốt (hiện chỉ hiển thị, chưa có chức năng).
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public Opportunity $opportunity;

    public function mount(Opportunity $opportunity): void
    {
        abort_unless($opportunity->employee_id === Auth::user()->employee?->employee_id, 404);

        $this->opportunity = $opportunity->load([
            'lead.branch:branch_id,branch_name',
            'stage',
            'student',
            'careResults' => fn ($q) => $q->with('activity')->orderByDesc('performed_at'),
            'appointments' => fn ($q) => $q->orderByDesc('scheduled_time'),
            'quotations' => fn ($q) => $q->withSum('details as total', 'line_total')->orderByDesc('created_at'),
        ]);
    }

    #[Computed]
    public function stages(): Collection
    {
        return Stage::query()->orderBy('sort_order')->get();
    }

    public function canCreateQuotation(): bool
    {
        return $this->opportunity->stage_id === Stage::CLOSED;
    }

    public function render()
    {
        return view('livewire.opportunities.show')
            ->title(__('opportunities.detail_title', ['code' => $this->opportunity->opportunity_id]));
    }
}
