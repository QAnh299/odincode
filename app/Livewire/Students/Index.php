<?php

namespace App\Livewire\Students;

use App\Models\Course;
use App\Models\Employee;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesTeam;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Danh sách học viên + tra cứu. Học viên = Opportunity đã thanh toán toàn bộ học phí (đơn hàng Hoàn tất).
 * Dùng chung cho mọi vai trò, phạm vi theo Student::visibleTo():
 *   - Giám đốc, Sale Admin, Kế toán: tất cả; lọc theo Đội rồi đến Salesperson của đội.
 *   - Sale Leader: học viên của đội mình; lọc theo Salesperson trong đội.
 *   - Salesperson: học viên từ cơ hội mình phụ trách.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    const PER_PAGE = 10;

    const FILTERS = ['search', 'status', 'course', 'team', 'employee', 'from', 'to'];

    // Các bộ lọc có thể bị sửa từ client → chỉ áp dụng giá trị nằm trong danh sách hợp lệ

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $course = '';

    #[Url(except: '')]
    public string $team = '';

    #[Url(except: '')]
    public string $employee = '';

    // Khoảng ngày trở thành học viên (Y-m-d)
    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function updated(string $property): void
    {
        if ($property === 'team') {
            $this->employee = '';
        }

        if (in_array($property, self::FILTERS, true)) {
            $this->resetPage();
        }
    }

    /**
     * Bấm thẻ thống kê: lọc theo trạng thái, bấm lại thì bỏ lọc.
     */
    public function filterStatus(string $status): void
    {
        $this->status = $this->status === $status ? '' : $status;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(...self::FILTERS);
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        foreach (self::FILTERS as $filter) {
            if ($this->$filter !== '') {
                return true;
            }
        }

        return false;
    }

    // ── Phạm vi theo vai trò ──────────────────────────────────────────

    #[Computed]
    public function me(): ?Employee
    {
        return Auth::user()->employee;
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    #[Computed]
    public function canFilterTeam(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN, Role::ACCOUNTANT], true);
    }

    #[Computed]
    public function canFilterEmployee(): bool
    {
        return $this->canFilterTeam || $this->routePrefix === Role::SALE_LEADER;
    }

    #[Computed]
    public function teams(): Collection
    {
        return $this->canFilterTeam
            ? SalesTeam::query()->orderBy('team_name')->get(['team_id', 'team_name'])
            : collect();
    }

    /**
     * Đội đang xem: Giám đốc / Sale Admin / Kế toán = đội đã chọn; Sale Leader = đội của mình.
     */
    #[Computed]
    public function activeTeam(): ?SalesTeam
    {
        if ($this->canFilterTeam) {
            return $this->teams->firstWhere('team_id', $this->team);
        }

        return $this->routePrefix === Role::SALE_LEADER && $this->me?->team_id
            ? SalesTeam::find($this->me->team_id)
            : null;
    }

    #[Computed]
    public function employeeOptions(): Collection
    {
        if (! $this->canFilterEmployee || ! $this->activeTeam) {
            return collect();
        }

        return Employee::query()
            ->where('team_id', $this->activeTeam->team_id)
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::SALESPERSON))
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'status']);
    }

    #[Computed]
    public function courses(): Collection
    {
        return Course::query()->orderBy('course_id')->get(['course_id', 'course_name']);
    }

    // ── Truy vấn ──────────────────────────────────────────────────────

    /**
     * Phạm vi vai trò + bộ lọc trên màn hình (bỏ bộ lọc trạng thái khi tính thẻ thống kê).
     */
    protected function filtered(bool $withStatus = true): Builder
    {
        $search = trim($this->search);

        return Student::query()
            ->visibleTo($this->me)
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($q) => $q->where('student_id', 'like', $like)
                    ->orWhere('full_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->when($withStatus && in_array($this->status, Student::STATUSES, true),
                fn ($q) => $q->where('status', $this->status))
            ->when($this->courses->contains('course_id', $this->course),
                fn ($q) => $q->whereHas('opportunity.quotations', fn ($quo) => $quo
                    ->whereHas('order', fn ($o) => $o->where('status', SalesOrder::STATUS_COMPLETED))
                    ->whereHas('details', fn ($d) => $d->where('course_id', $this->course))))
            ->when($this->canFilterTeam && $this->activeTeam,
                fn ($q) => $q->whereHas('opportunity.employee', fn ($e) => $e->where('team_id', $this->activeTeam->team_id)))
            ->when($this->employeeOptions->contains('employee_id', $this->employee),
                fn ($q) => $q->whereHas('opportunity', fn ($o) => $o->where('employee_id', $this->employee)))
            ->when($this->validDate($this->from), fn ($q) => $q->whereDate('conversion_date', '>=', $this->from))
            ->when($this->validDate($this->to), fn ($q) => $q->whereDate('conversion_date', '<=', $this->to));
    }

    protected function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }

    /**
     * Số học viên theo trạng thái (theo bộ lọc, trừ bộ lọc trạng thái).
     */
    #[Computed]
    public function stats(): array
    {
        $byStatus = $this->filtered(withStatus: false)
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return ['all' => (int) $byStatus->sum()] + collect(Student::STATUSES)
            ->mapWithKeys(fn ($s) => [$s => (int) ($byStatus[$s] ?? 0)])
            ->all();
    }

    public function render()
    {
        $students = $this->filtered()
            ->with([
                'opportunity:opportunity_id,lead_id,employee_id',
                'opportunity.employee:employee_id,full_name',
                // Khoá học = dòng của báo giá có đơn hàng đã thanh toán đủ
                'opportunity.quotations' => fn ($q) => $q
                    ->whereHas('order', fn ($o) => $o->where('status', SalesOrder::STATUS_COMPLETED))
                    ->with('details.course:course_id,course_name'),
            ])
            ->orderByDesc('conversion_date')
            ->orderByDesc('student_id')
            ->paginate(self::PER_PAGE);

        return view('livewire.students.index', [
            'students' => $students,
        ])->title(__('students.title'));
    }
}
