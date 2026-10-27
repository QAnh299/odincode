<?php

namespace App\Livewire\Leads;

use App\Livewire\Leads\Concerns\EditsLead;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Role;
use App\Services\LeadImportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Chi tiết Lead: thông tin liên hệ (nguồn, chi nhánh, ngày tạo) và các cơ hội đã tạo từ Lead.
 * Giám đốc, Sale Admin, Sale Leader xem theo Lead::visibleTo() (cùng phạm vi với danh sách Lead).
 * Sale Admin chỉnh sửa khi Lead còn "Mới" (mọi trường) hoặc cơ hội của Lead đang chăm sóc (họ tên, SĐT,
 * email, link nguồn); cơ hội đã chốt / lưu trữ thì giữ nguyên thông tin.
 */
#[Layout('layouts.app')]
class Show extends Component
{
    use EditsLead;

    public Lead $lead;

    public ?string $notice = null;

    public function mount(Lead $lead): void
    {
        abort_unless(
            Lead::query()->visibleTo(Auth::user()->employee)->whereKey($lead->getKey())->exists(),
            404
        );

        $this->lead = $lead->load('branch:branch_id,branch_name,address');
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * Cơ hội tạo từ Lead, mới nhất lên đầu; mỗi cơ hội kèm url chi tiết khi vai trò xem được cơ hội đó.
     */
    #[Computed]
    public function opportunities(): Collection
    {
        $route = $this->routePrefix.'.opportunities.show';
        $viewable = Route::has($route)
            ? Opportunity::query()->visibleTo(Auth::user()->employee)->assigned()
                ->where('lead_id', $this->lead->lead_id)->pluck('opportunity_id')->all()
            : [];

        return $this->lead->opportunities()
            ->with(['employee:employee_id,full_name', 'stage:stage_id,stage_name', 'student:student_id,opportunity_id'])
            ->orderByDesc('conversion_date')
            ->get()
            ->each(fn (Opportunity $opportunity) => $opportunity->url = in_array($opportunity->opportunity_id, $viewable, true)
                ? route($route, $opportunity)
                : null);
    }

    protected function leadToEdit(): ?Lead
    {
        return $this->routePrefix === Role::SALE_ADMIN && $this->lead->isEditable() ? $this->lead : null;
    }

    public function leadEditableFields(): array
    {
        return $this->lead->status === Lead::STATUS_NEW ? LeadImportService::COLUMNS : self::LEAD_CONTACT_FIELDS;
    }

    protected function leadUpdated(Lead $lead): void
    {
        $this->lead = $lead->refresh()->load('branch:branch_id,branch_name,address');
        $this->notice = __('leads.edit_saved');
    }

    public function dismissNotice(): void
    {
        $this->notice = null;
    }

    #[Computed]
    public function branches(): Collection
    {
        return Branch::query()->orderBy('branch_id')->get(['branch_id', 'branch_name']);
    }

    public function render()
    {
        return view('livewire.leads.show')
            ->title(__('leads.detail_title', ['code' => $this->lead->lead_id]));
    }
}
