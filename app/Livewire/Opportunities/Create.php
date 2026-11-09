<?php

namespace App\Livewire\Opportunities;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\Role;
use App\Models\Student;
use App\Services\LeadDuplicateService;
use App\Services\OpportunityCreateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Form tạo cơ hội mới cho khách cũ quay lại.
 * Chỉ dành cho Salesperson — middleware CheckRole đã chặn ở route.
 *
 * Hai cách vào trang này:
 *   1. Từ Kanban: /opportunities/create — form trắng, salesperson tự tìm khách.
 *   2. Từ trang học viên: /opportunities/create?student={id} — khách chọn sẵn,
 *      hiện ngay cảnh báo/chặn. Nếu student_id không hợp lệ thì bỏ qua, hiện form trắng.
 */
#[Layout('layouts.app')]
class Create extends Component
{
    // Ô tìm kiếm khách
    #[Url(except: '')]
    public string $search = '';

    // Lead ID của khách đang được chọn (null = chưa chọn)
    public ?string $selectedLeadId = null;

    // Form cập nhật thông tin liên hệ (phone / email / source_url)
    public string $contactPhone = '';

    public string $contactEmail = '';

    public string $contactSourceUrl = '';

    public function mount(): void
    {
        // Chỉ Salesperson được dùng trang này (middleware đã chặn, đây là lớp bảo vệ thứ hai)
        abort_unless(Auth::user()?->role_name === Role::SALESPERSON, 403);

        // Nếu vào từ trang học viên với ?student=ID trên URL (query string, không phải route param)
        $studentId = request()->query('student');
        if ($studentId) {
            $this->preselectFromStudent((string) $studentId);
        }
    }

    /**
     * Chọn sẵn khách từ student_id trên URL. Kiểm tra salesperson có quyền xem học viên đó không.
     * Không hợp lệ thì bỏ qua (hiện form trắng), không báo lỗi.
     */
    protected function preselectFromStudent(string $studentId): void
    {
        $student = Student::query()
            ->visibleTo($this->me)
            ->where('student_id', $studentId)
            ->with('opportunity:opportunity_id,lead_id')
            ->first();

        if (! $student?->opportunity?->lead_id) {
            return;
        }

        // Kiểm tra thêm: salesperson phải có ít nhất một cơ hội với khách này
        $lead = app(OpportunityCreateService::class)
            ->findForSalesperson($student->opportunity->lead_id, $this->me);

        if ($lead) {
            $this->selectedLeadId = $lead->lead_id;
        }
    }

    // ── Computed ──────────────────────────────────────────────────────

    #[Computed]
    public function me(): Employee
    {
        return Auth::user()->employee;
    }

    /**
     * Danh sách khách tìm được (tìm theo $search), kèm tình trạng từng khách.
     * Chỉ tải khi có từ khóa tìm kiếm.
     *
     * @return Collection<int, array>
     */
    #[Computed]
    public function results(): Collection
    {
        $search = trim($this->search);

        if ($search === '') {
            return collect();
        }

        $service  = app(OpportunityCreateService::class);
        $leads    = $service->candidates($this->me, $search);

        return $leads->map(fn (Lead $lead) => [
            'lead_id'   => $lead->lead_id,
            'full_name' => $lead->full_name,
            'phone'     => $lead->phone,
            'check'     => $service->check($lead, $this->me),
        ]);
    }

    /**
     * Khách đang được chọn cùng kết quả kiểm tra, null nếu chưa chọn.
     */
    #[Computed]
    public function selected(): ?array
    {
        if (! $this->selectedLeadId) {
            return null;
        }

        $service = app(OpportunityCreateService::class);
        $lead    = $service->findForSalesperson($this->selectedLeadId, $this->me);

        if (! $lead) {
            $this->selectedLeadId = null;

            return null;
        }

        return [
            'lead'  => $lead,
            'check' => $service->check($lead, $this->me),
        ];
    }

    // ── Actions ───────────────────────────────────────────────────────

    /**
     * Cập nhật tìm kiếm: xóa khách đang chọn nếu đang tìm lại.
     */
    public function updatedSearch(): void
    {
        $this->selectedLeadId = null;
        $this->resetContact();
        unset($this->results, $this->selected);
    }

    /**
     * Chọn khách từ danh sách kết quả. Điền sẵn thông tin liên hệ hiện tại vào form.
     */
    public function selectLead(string $leadId): void
    {
        $this->selectedLeadId = $leadId;
        $this->resetContact();
        unset($this->selected);

        // Điền sẵn giá trị hiện tại để salesperson thấy và sửa nếu cần
        $service = app(OpportunityCreateService::class);
        $lead    = $service->findForSalesperson($leadId, $this->me);

        if ($lead) {
            $this->contactPhone     = $lead->phone ?? '';
            $this->contactEmail     = $lead->email ?? '';
            $this->contactSourceUrl = $lead->source_url ?? '';
        }
    }

    /**
     * Bỏ chọn, quay về danh sách tìm kiếm.
     */
    public function clearSelection(): void
    {
        $this->selectedLeadId = null;
        $this->resetContact();
        unset($this->selected);
    }

    /**
     * Reset các ô nhập liên hệ về trống.
     */
    protected function resetContact(): void
    {
        $this->contactPhone     = '';
        $this->contactEmail     = '';
        $this->contactSourceUrl = '';
    }

    /**
     * Xác nhận tạo cơ hội. Sau khi tạo thành công, chuyển ngay sang trang chi tiết cơ hội mới.
     */
    public function confirm(): void
    {
        // Kiểm tra lại quyền ở server (không chỉ dựa vào giao diện)
        abort_unless(Auth::user()?->role_name === Role::SALESPERSON, 403);

        if (! $this->selectedLeadId) {
            return;
        }

        $service = app(OpportunityCreateService::class);
        $lead    = $service->findForSalesperson($this->selectedLeadId, $this->me);

        if (! $lead) {
            $this->addError('general', __('opportunities.create.error_no_permission'));

            return;
        }

        try {
            $opportunity = $service->create($lead, $this->me, [
                'phone'      => trim($this->contactPhone) ?: null,
                'email'      => trim($this->contactEmail) ?: null,
                'source_url' => trim($this->contactSourceUrl) ?: null,
            ]);
        } catch (RuntimeException $e) {
            $this->addError('general', $e->getMessage());
            unset($this->selected); // Tải lại tình trạng mới nhất

            return;
        }

        $this->redirect(
            route('salesperson.opportunities.show', $opportunity),
            navigate: true,
        );
    }

    public function render()
    {
        return view('livewire.opportunities.create')
            ->title(__('opportunities.create.title'));
    }
}
