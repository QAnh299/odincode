<?php

namespace App\Livewire\Opportunities;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\Stage;
use App\Models\Student;
use App\Services\LeadDuplicateService;
use App\Services\OpportunityCreateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
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

    // Lead ID của khách đang được chọn (null = chưa chọn); chỉ đổi qua action ở server
    #[Locked]
    public ?string $selectedLeadId = null;

    // Form cập nhật thông tin liên hệ (phone / email / source_url)
    public string $contactPhone = '';

    public string $contactEmail = '';

    public string $contactSourceUrl = '';

    // Mã học viên khi vào từ nút "Đăng ký khóa mới" (null = vào từ Kanban); quyết định nút Quay lại
    #[Locked]
    public ?string $fromStudent = null;

    // Khách được chọn sẵn từ trang học viên: không cho đổi sang khách khác
    #[Locked]
    public bool $lockedLead = false;

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

        if (! $student) {
            return;
        }

        // Học viên hợp lệ: Quay lại về trang chi tiết học viên
        $this->fromStudent = $student->student_id;

        if (! $student->opportunity?->lead_id) {
            return;
        }

        // Kiểm tra thêm: salesperson phải có ít nhất một cơ hội với khách này
        $lead = app(OpportunityCreateService::class)
            ->findForSalesperson($student->opportunity->lead_id, $this->me);

        if ($lead) {
            $this->selectedLeadId = $lead->lead_id;
            $this->lockedLead     = true;
            $this->fillContact($lead);
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
            'lead'    => $lead,
            'check'   => $service->check($lead, $this->me),
            'profile' => $this->profileOf($lead),
        ];
    }

    /**
     * Học viên gần nhất của khách (cơ hội đã load xếp mới nhất trước), null nếu chưa từng là học viên.
     */
    protected function profileOf(Lead $lead): array
    {
        return [
            'student' => $lead->opportunities->first(fn (Opportunity $opp) => $opp->student !== null)?->student,
        ];
    }

    /**
     * Tên giai đoạn đầu của cơ hội mới (Data chưa tương tác).
     */
    #[Computed]
    public function firstStageName(): string
    {
        return Stage::query()->whereKey(Stage::UNTOUCHED)->value('stage_name') ?? Stage::UNTOUCHED;
    }

    // ── Actions ───────────────────────────────────────────────────────

    /**
     * Cập nhật tìm kiếm: xóa khách đang chọn nếu đang tìm lại.
     */
    public function updatedSearch(): void
    {
        if ($this->lockedLead) {
            return;
        }

        $this->selectedLeadId = null;
        $this->resetContact();
        unset($this->results, $this->selected);
    }

    /**
     * Chọn khách từ danh sách kết quả. Điền sẵn thông tin liên hệ hiện tại vào form.
     */
    public function selectLead(string $leadId): void
    {
        if ($this->lockedLead) {
            return;
        }

        $this->selectedLeadId = $leadId;
        $this->resetContact();
        unset($this->selected);

        // Điền sẵn giá trị hiện tại để salesperson thấy và sửa nếu cần
        $service = app(OpportunityCreateService::class);
        $lead    = $service->findForSalesperson($leadId, $this->me);

        if ($lead) {
            $this->fillContact($lead);
        }
    }

    /**
     * Điền thông tin liên hệ hiện tại của khách vào form.
     */
    protected function fillContact(Lead $lead): void
    {
        $this->contactPhone     = $lead->phone ?? '';
        $this->contactEmail     = $lead->email ?? '';
        $this->contactSourceUrl = $lead->source_url ?? '';
    }

    /**
     * Bỏ chọn, quay về danh sách tìm kiếm.
     */
    public function clearSelection(): void
    {
        if ($this->lockedLead) {
            return;
        }

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

    /**
     * Trang để quay lại: chi tiết học viên nếu vào từ đó, ngược lại là Kanban cơ hội.
     */
    public function backUrl(): string
    {
        return $this->fromStudent
            ? route('salesperson.students.show', $this->fromStudent)
            : route('salesperson.opportunities');
    }

    public function render()
    {
        return view('livewire.opportunities.create')
            ->title(__('opportunities.create.title'));
    }
}
