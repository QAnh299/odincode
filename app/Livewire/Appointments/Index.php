<?php

namespace App\Livewire\Appointments;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lịch hẹn của Salesperson (menu Quản lý Opportunity → Lịch hẹn): các lịch hẹn mình đang phụ trách,
 * thẻ thống kê Hôm nay / Ngày mai / 7 ngày tới / Chờ cập nhật kết quả, lọc nhanh + tìm kiếm.
 * Mỗi dòng là một loại (Test / Tư vấn) vì hai loại có thể khác giờ; bấm để mở chi tiết cơ hội.
 * Địa điểm là cơ sở của khách (Leads.branch_id).
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    const PER_PAGE = 10;

    // Lọc nhanh: khoảng thời gian / trạng thái
    const VIEWS = ['upcoming', 'today', 'tomorrow', 'week', 'overdue', 'done', 'all'];

    #[Url(except: 'upcoming')]
    public string $view = 'upcoming';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['view', 'search', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, self::VIEWS, true) ? $view : 'upcoming';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('view', 'search', 'type');
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->view !== 'upcoming' || $this->search !== '' || $this->type !== '';
    }

    public function activeView(): string
    {
        return in_array($this->view, self::VIEWS, true) ? $this->view : 'upcoming';
    }

    /**
     * Lịch hẹn của salesperson đang đăng nhập.
     */
    protected function own(): Builder
    {
        return Appointment::query()->where('employee_id', Auth::user()->employee?->employee_id);
    }

    /**
     * Điều kiện của từng chế độ xem.
     */
    protected function applyView(Builder $query, string $view): Builder
    {
        $now = CarbonImmutable::now();
        $today = $now->startOfDay();
        $scheduled = fn (Builder $q) => $q->where('status', Appointment::STATUS_SCHEDULED);

        return match ($view) {
            // Chưa tới giờ hẹn
            'upcoming' => $scheduled($query)->where('scheduled_time', '>', $now),
            'today'    => $query->whereBetween('scheduled_time', [$today, $today->endOfDay()]),
            'tomorrow' => $query->whereBetween('scheduled_time', [$today->addDay(), $today->addDay()->endOfDay()]),
            // Sắp tới trong 7 ngày (kể cả hôm nay)
            'week'     => $scheduled($query)->where('scheduled_time', '>', $now)->where('scheduled_time', '<', $today->addDays(7)),
            // Đã qua giờ hẹn mà chưa cập nhật kết quả
            'overdue'  => $scheduled($query)->where('scheduled_time', '<=', $now),
            'done'     => $query->whereIn('status', [Appointment::STATUS_SUCCESS, Appointment::STATUS_FAILED]),
            default    => $query,
        };
    }

    /**
     * Số lịch hẹn cho các thẻ thống kê.
     */
    #[Computed]
    public function counts(): array
    {
        $counts = [];
        foreach (['today', 'tomorrow', 'week', 'overdue'] as $view) {
            $counts[$view] = $this->applyView($this->own(), $view)->count();
        }

        return $counts;
    }

    public function render()
    {
        $view = $this->activeView();
        $search = trim($this->search);

        $appointments = $this->applyView($this->own(), $view)
            ->when(in_array($this->type, Appointment::TYPES, true), fn ($q) => $q->where('appointment_type', $this->type))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($q) => $q->where('appointment_id', 'like', $like)
                    ->orWhere('opportunity_id', 'like', $like)
                    ->orWhereHas('opportunity.lead', fn ($l) => $l->where('full_name', 'like', $like)->orWhere('phone', 'like', $like)));
            })
            ->with(['opportunity:opportunity_id,lead_id,stage_id', 'opportunity.lead.branch:branch_id,branch_name', 'opportunity.stage:stage_id,stage_name,sort_order'])
            // Sắp tới: gần nhất lên đầu; đã qua / đã có kết quả: mới nhất lên đầu
            ->orderBy('scheduled_time', in_array($view, ['upcoming', 'today', 'tomorrow', 'week'], true) ? 'asc' : 'desc')
            ->orderBy('appointment_id')
            // Cùng giờ: Test trước, Tư vấn sau
            ->orderByRaw('FIELD(appointment_type, '.implode(', ', array_map(fn ($t) => "'$t'", Appointment::TYPES)).')')
            ->paginate(self::PER_PAGE);

        return view('livewire.appointments.index', [
            'appointments' => $appointments,
        ])->title(__('appointments.title'));
    }
}
