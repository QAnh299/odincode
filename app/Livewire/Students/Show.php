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
 * Chi tiết học viên: đầu trang (tổng học phí / đã thu / còn lại), dải thẻ các khóa học khách đã đăng ký
 * hoặc đang được tư vấn (cuộn ngang, gom theo mọi cơ hội cùng Lead), chi tiết khóa đang chọn
 * (tab Thanh toán / Lịch sử chăm sóc / Lịch hẹn), thông tin học viên và nguồn gốc.
 * Các mã cơ hội / đơn / báo giá / hóa đơn / khóa học / Lead dẫn sang trang chi tiết nếu vai trò có trang đó.
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

    // Thẻ khóa học đang chọn ("{opportunity_id}|{course_id}") và tab đang xem của khóa đó
    public string $selectedCard = '';

    public string $courseTab = 'payment';

    public const COURSE_TABS = ['payment', 'care', 'appointments'];

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

        // Mặc định mở khóa học của lần đăng ký này
        $cards = $this->courseCards;
        $this->selectedCard = ($cards->firstWhere('opportunity_id', $student->opportunity_id) ?? $cards->first())['key'] ?? '';
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
     * Salesperson đang xem trang này được tạo cơ hội mới cho khách:
     *   - Vai trò phải là Salesperson.
     *   - Đã từng có ít nhất một cơ hội với khách (đảm bảo bởi visibleTo khi load trang).
     * Điều kiện thứ hai được đảm bảo vì Student::visibleTo lọc theo Opportunity::visibleTo,
     * và Opportunity::visibleTo với Salesperson chỉ trả về cơ hội của chính mình.
     */
    #[Computed]
    public function canCreateOpportunity(): bool
    {
        return $this->routePrefix === Role::SALESPERSON;
    }

    // Trang chi tiết nối tiếp breadcrumb "Quản lý học viên › mã học viên › ..." (trait Concerns\StudentTrail)
    public const TRAIL_PAGES = ['opportunities', 'quotations', 'orders', 'invoices'];

    /**
     * Link sang trang chi tiết "{vai trò}.{tên}.show" nếu vai trò hiện tại có trang đó, ngược lại null.
     * Cơ hội / báo giá / đơn hàng / hóa đơn mang theo ?student= để trang đó hiện breadcrumb từ học viên này.
     */
    public function detailUrl(string $name, mixed $model): ?string
    {
        $route = $this->routePrefix.'.'.$name.'.show';

        if ($model === null || ! Route::has($route)) {
            return null;
        }

        return route($route, in_array($name, self::TRAIL_PAGES, true)
            ? [$model, 'student' => $this->student->student_id]
            : $model);
    }

    // ── Khóa học ──────────────────────────────────────────────────────

    /**
     * Các cơ hội của khách (cùng Lead) trong phạm vi được xem, mới nhất lên đầu.
     */
    #[Computed]
    public function opportunities(): Collection
    {
        $leadId = $this->student->opportunity?->lead_id;

        if (! $leadId) {
            return collect();
        }

        return Opportunity::query()
            ->visibleTo(Auth::user()->employee)
            ->where('lead_id', $leadId)
            ->with([
                'stage:stage_id,stage_name',
                'employee:employee_id,full_name,team_id',
                'employee.team:team_id,team_name',
                'student:student_id,opportunity_id,status',
                'quotations' => fn ($q) => $q->orderByDesc('created_at'),
                'quotations.details' => fn ($q) => $q->orderBy('course_id'),
                'quotations.details.course',
                'quotations.order',
            ])
            ->orderByDesc('conversion_date')
            ->get();
    }

    /**
     * Thẻ khóa học: mỗi dòng khóa học trong báo giá của từng cơ hội (báo giá đã có đơn, chưa có thì báo giá mới nhất).
     * Cơ hội chưa có báo giá vẫn có một thẻ "Chưa có khoá học" để xem lịch sử chăm sóc.
     * Tổng tiền / đã thu của đơn nhiều khóa chia theo tỷ lệ thành tiền từng dòng.
     */
    #[Computed]
    public function courseCards(): Collection
    {
        return $this->opportunities->flatMap(function (Opportunity $opportunity) {
            $quotation = $opportunity->quotations->first(fn ($q) => $q->order !== null) ?? $opportunity->quotations->first();
            $order = $quotation?->order;
            $details = $quotation?->details ?? collect();

            $status = $opportunity->student
                ? ['label' => __('students.status_name.'.$opportunity->student->status), 'tone' => 'st-tone--'.$opportunity->student->status]
                : ['label' => __('students.opportunity_status.'.$opportunity->status), 'tone' => 'st-opp--'.$opportunity->status];

            $base = [
                'opportunity_id' => $opportunity->opportunity_id,
                'opportunity'    => $opportunity,
                'quotation'      => $quotation,
                'order'          => $order,
                'status'         => $status,
            ];

            if ($details->isEmpty()) {
                return [$base + [
                    'key' => $opportunity->opportunity_id.'|', 'detail' => null, 'course' => null,
                    'name' => __('students.no_courses'), 'course_id' => null, 'total' => 0.0, 'paid' => 0.0,
                ]];
            }

            $lines = (float) $details->sum('line_total');

            return $details->map(function ($detail) use ($base, $order, $lines) {
                $share = $lines > 0 ? (float) $detail->line_total / $lines : 0;

                return $base + [
                    'key'       => $base['opportunity_id'].'|'.$detail->course_id,
                    'detail'    => $detail,
                    'course'    => $detail->course,
                    'name'      => $detail->course?->course_name ?? $detail->course_id,
                    'course_id' => $detail->course_id,
                    'total'     => $order ? round((float) $order->total_amount * $share) : (float) $detail->line_total,
                    'paid'      => $order ? round((float) $order->paid_amount * $share) : 0.0,
                ];
            })->all();
        })->values();
    }

    /**
     * Số liệu đầu trang: số khóa học, tổng học phí, đã thanh toán, còn lại (theo các thẻ có khóa học).
     */
    #[Computed]
    public function totals(): array
    {
        $cards = $this->courseCards->whereNotNull('detail');
        $total = (float) $cards->sum('total');
        $paid = (float) $cards->sum('paid');

        return ['courses' => $cards->count(), 'total' => $total, 'paid' => $paid, 'remaining' => max(0, $total - $paid)];
    }

    #[Computed]
    public function selected(): ?array
    {
        return $this->courseCards->firstWhere('key', $this->selectedCard) ?? $this->courseCards->first();
    }

    /**
     * Đơn hàng của khóa đang chọn kèm các đợt hoá đơn.
     */
    #[Computed]
    public function selectedOrder(): ?SalesOrder
    {
        $order = $this->selected['order'] ?? null;

        return $order?->load(['invoices' => fn ($q) => $q->orderBy('payment_installment')]);
    }

    public function selectCard(string $key): void
    {
        if (! $this->courseCards->contains('key', $key)) {
            return;
        }

        $this->selectedCard = $key;
        $this->courseTab = 'payment';
        unset($this->selected, $this->selectedOrder, $this->openCares, $this->openAppointments);
    }

    public function setCourseTab(string $tab): void
    {
        $this->courseTab = in_array($tab, self::COURSE_TABS, true) ? $tab : 'payment';
    }

    /**
     * Lịch sử chăm sóc của cơ hội chứa khóa đang chọn, mới nhất lên đầu.
     */
    #[Computed]
    public function openCares(): Collection
    {
        return $this->selected
            ? $this->selected['opportunity']->careResults()
                ->with(['activity', 'employee:employee_id,full_name'])
                ->orderByDesc('performed_at')
                ->get()
            : collect();
    }

    /**
     * Lịch hẹn của cơ hội chứa khóa đang chọn, gom theo mã (mới nhất lên đầu).
     */
    #[Computed]
    public function openAppointments(): Collection
    {
        return $this->selected
            ? Appointment::groupForDisplay($this->selected['opportunity']->appointments()->orderBy('scheduled_time')->get())
            : collect();
    }

    /**
     * Địa điểm lịch hẹn: cơ sở của khách - "Tên cơ sở - Địa chỉ".
     */
    public function appointmentPlace(): ?string
    {
        $branch = $this->student->opportunity?->lead?->branch;

        return $branch ? $branch->branch_name.' - '.$branch->address : null;
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

        // Trạng thái học viên hiện trên thẻ khóa học
        unset($this->opportunities, $this->courseCards, $this->selected);

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
