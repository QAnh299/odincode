<?php

namespace App\Livewire\Students;

use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Chi tiết học viên: thông tin học viên, khoá học đã đăng ký, thanh toán (đơn hàng + các đợt hoá đơn),
 * cơ hội gốc và các lần đăng ký khác của cùng khách hàng.
 * Xem theo Student::visibleTo(); Chỉnh sửa (họ tên, SĐT, email, ngày sinh, trạng thái) chỉ dành cho
 * Sale Leader (học viên của đội) và Salesperson (học viên từ cơ hội mình phụ trách).
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public Student $student;

    // Popup chỉnh sửa
    public bool $editing = false;

    public string $fullName = '';

    public string $phone = '';

    public string $email = '';

    public string $dateOfBirth = '';

    public string $status = '';

    public ?string $notice = null;

    public function mount(Student $student): void
    {
        abort_unless(
            Student::query()->visibleTo(Auth::user()->employee)->whereKey($student->getKey())->exists(),
            404
        );

        $this->student = $student->load([
            'opportunity.lead.branch:branch_id,branch_name',
            'opportunity.employee.team:team_id,team_name',
            'opportunity.stage:stage_id,stage_name',
        ]);
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * Chỉnh sửa: Sale Leader, Salesperson (phạm vi đã giới hạn bởi visibleTo khi mở trang).
     */
    #[Computed]
    public function canEdit(): bool
    {
        return in_array($this->routePrefix, [Role::SALE_LEADER, Role::SALESPERSON], true);
    }

    /**
     * Đơn hàng đã thanh toán đủ + báo giá, khoá học, voucher, các đợt hoá đơn.
     */
    #[Computed]
    public function order(): ?SalesOrder
    {
        $order = $this->student->paidOrder();

        return $order?->load([
            'quotation.details' => fn ($q) => $q->orderBy('course_id'),
            'quotation.details.course',
            'quotation.voucher',
            'invoices' => fn ($q) => $q->orderBy('payment_installment'),
        ]);
    }

    /**
     * Các lần đăng ký khác của cùng khách hàng (cùng Lead, Opportunity khác).
     */
    #[Computed]
    public function otherEnrollments(): Collection
    {
        $leadId = $this->student->opportunity?->lead_id;

        if (! $leadId) {
            return collect();
        }

        return Student::query()
            ->visibleTo(Auth::user()->employee)
            ->whereKeyNot($this->student->getKey())
            ->whereHas('opportunity', fn ($q) => $q->where('lead_id', $leadId))
            ->orderByDesc('conversion_date')
            ->get(['student_id', 'conversion_date', 'status', 'opportunity_id']);
    }

    /**
     * Link sang chi tiết cơ hội nếu vai trò hiện tại có màn đó (Salesperson, Sale Leader, Giám đốc).
     */
    public function opportunityUrl(): ?string
    {
        $name = $this->routePrefix.'.opportunities.show';

        return Route::has($name) ? route($name, $this->student->opportunity_id) : null;
    }

    // ── Chỉnh sửa ─────────────────────────────────────────────────────

    public function startEdit(): void
    {
        abort_unless($this->canEdit, 403);

        $this->resetValidation();
        $this->fullName = $this->student->full_name;
        $this->phone = $this->student->phone;
        $this->email = (string) $this->student->email;
        $this->dateOfBirth = $this->student->date_of_birth?->format('Y-m-d') ?? '';
        $this->status = $this->student->status;
        $this->editing = true;
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        abort_unless($this->canEdit, 403);

        $this->fullName = trim($this->fullName);
        $this->phone = trim($this->phone);
        $this->email = trim($this->email);

        $this->validate([
            'fullName'    => ['required', 'string', 'max:150'],
            'phone'       => ['required', 'regex:/^0\d{9}$/'],
            'email'       => ['nullable', 'email', 'max:254'],
            'dateOfBirth' => ['nullable', 'date', 'before:today'],
            'status'      => ['required', Rule::in(Student::STATUSES)],
        ], [
            'phone.regex'        => __('students.phone_invalid'),
            'dateOfBirth.before' => __('students.dob_invalid'),
        ], [
            'fullName'    => __('students.full_name'),
            'phone'       => __('students.phone'),
            'email'       => __('students.email'),
            'dateOfBirth' => __('students.date_of_birth'),
            'status'      => __('students.status'),
        ]);

        $this->student->update([
            'full_name'     => $this->fullName,
            'phone'         => $this->phone,
            'email'         => $this->email ?: null,
            'date_of_birth' => $this->dateOfBirth ?: null,
            'status'        => $this->status,
        ]);

        $this->editing = false;
        $this->notice = __('students.saved');
    }

    public function dismissNotice(): void
    {
        $this->notice = null;
    }

    public function render()
    {
        return view('livewire.students.show')
            ->title(__('students.detail_title', ['code' => $this->student->student_id]));
    }
}
