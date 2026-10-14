<?php

namespace App\Livewire\Students;

use App\Models\Appointment;
use App\Models\Opportunity;
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

    // Lịch sử cơ hội: Opportunity đang mở (mặc định đóng hết) và tab đang xem
    public ?string $openOpportunity = null;

    public string $opportunityTab = 'care';

    const OPPORTUNITY_TABS = ['care', 'appointments'];

    public function mount(Student $student): void
    {
        abort_unless(
            Student::query()->visibleTo(Auth::user()->employee)->whereKey($student->getKey())->exists(),
            404
        );

        $this->student = $student->load([
            'opportunity.lead.branch:branch_id,branch_name,address',
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

    // ── Lịch sử cơ hội ────────────────────────────────────────────────

    /**
     * Các lần khách hàng là Opportunity (cùng Lead), mới nhất lên đầu. Mỗi Opportunity kèm các khoá học
     * (courses) lấy từ báo giá đã có đơn hàng, nếu chưa có thì báo giá mới nhất.
     */
    #[Computed]
    public function opportunities(): Collection
    {
        $leadId = $this->student->opportunity?->lead_id;

        if (! $leadId) {
            return collect();
        }

        $opportunities = Opportunity::query()
            ->visibleTo(Auth::user()->employee)
            ->where('lead_id', $leadId)
            ->with([
                'stage:stage_id,stage_name',
                'employee:employee_id,full_name',
                'student:student_id,opportunity_id',
                'quotations' => fn ($q) => $q->orderByDesc('created_at'),
                'quotations.details.course:course_id,course_name',
            ])
            ->orderByDesc('conversion_date')
            ->get();

        $ordered = SalesOrder::query()
            ->whereIn('quotation_id', $opportunities->flatMap->quotations->pluck('quotation_id'))
            ->pluck('quotation_id')
            ->all();

        return $opportunities->each(function (Opportunity $opportunity) use ($ordered) {
            $quotation = $opportunity->quotations->first(fn ($q) => in_array($q->quotation_id, $ordered, true))
                ?? $opportunity->quotations->first();

            $opportunity->courses = $quotation?->details->map(fn ($d) => $d->course?->course_name ?? $d->course_id)->all() ?? [];
        });
    }

    /**
     * Mở / đóng một Opportunity trong Lịch sử cơ hội (mở thì về tab Lịch sử chăm sóc).
     */
    public function toggleOpportunity(string $opportunityId): void
    {
        if (! $this->opportunities->contains('opportunity_id', $opportunityId)) {
            return;
        }

        $this->openOpportunity = $this->openOpportunity === $opportunityId ? null : $opportunityId;
        $this->opportunityTab = 'care';
    }

    public function setOpportunityTab(string $tab): void
    {
        $this->opportunityTab = in_array($tab, self::OPPORTUNITY_TABS, true) ? $tab : 'care';
    }

    /**
     * Opportunity đang mở (chỉ trong phạm vi được xem).
     */
    protected function openedOpportunity(): ?Opportunity
    {
        return $this->openOpportunity
            ? $this->opportunities->firstWhere('opportunity_id', $this->openOpportunity)
            : null;
    }

    /**
     * Lịch sử chăm sóc của Opportunity đang mở, mới nhất lên đầu.
     */
    #[Computed]
    public function openCares(): Collection
    {
        return $this->openedOpportunity()?->careResults()
            ->with(['activity', 'employee:employee_id,full_name'])
            ->orderByDesc('performed_at')
            ->get() ?? collect();
    }

    /**
     * Lịch hẹn của Opportunity đang mở, gom theo mã (mới nhất lên đầu).
     */
    #[Computed]
    public function openAppointments(): Collection
    {
        $opportunity = $this->openedOpportunity();

        return $opportunity
            ? Appointment::groupForDisplay($opportunity->appointments()->orderBy('scheduled_time')->get())
            : collect();
    }

    /**
     * Địa điểm lịch hẹn: cơ sở của khách – "Tên cơ sở - Địa chỉ".
     */
    public function appointmentPlace(): ?string
    {
        $branch = $this->student->opportunity?->lead?->branch;

        return $branch ? $branch->branch_name.' - '.$branch->address : null;
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
