<?php

namespace App\Livewire;

use App\Services\SalesCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Lịch việc cần làm trên topbar (Salesperson): lưới tháng chỉ hiện số việc của từng ngày,
 * bấm 1 ngày mở danh sách việc của ngày đó. Dữ liệu từ App\Services\SalesCalendar.
 */
class TaskCalendar extends Component
{
    #[Locked]
    public string $month = '';

    #[Locked]
    public string $day = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->day = now()->toDateString();
    }

    public function previousMonth(): void
    {
        $this->goTo($this->monthStart()->subMonth());
    }

    public function nextMonth(): void
    {
        $this->goTo($this->monthStart()->addMonth());
    }

    public function selectDay(string $day): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) && str_starts_with($day, $this->month.'-')) {
            $this->day = $day;
        }
    }

    public function closeDay(): void
    {
        $this->day = '';
    }

    protected function goTo(CarbonImmutable $month): void
    {
        $this->month = $month->format('Y-m');
        $today = now()->toDateString();
        $this->day = str_starts_with($today, $this->month.'-') ? $today : '';
        unset($this->items);
    }

    protected function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->month);
    }

    #[Computed]
    public function items(): Collection
    {
        $employee = Auth::user()?->employee;

        return collect($employee ? app(SalesCalendar::class)->month($employee, $this->monthStart()) : []);
    }

    /**
     * Các ô của lưới tháng (thứ Hai đến Chủ nhật, gồm cả ngày đầu / cuối của tháng liền kề).
     */
    #[Computed]
    public function cells(): array
    {
        $byDay = $this->items->groupBy('day');
        $start = $this->monthStart();
        $end = $start->endOfMonth()->endOfWeek();
        $today = now()->toDateString();

        $cells = [];
        for ($date = $start->startOfWeek(); $date <= $end; $date = $date->addDay()) {
            $key = $date->toDateString();
            $items = $byDay[$key] ?? collect();
            $cells[] = [
                'date'    => $key,
                'number'  => $date->day,
                'inMonth' => $date->month === $start->month,
                'today'   => $key === $today,
                'todo'    => $items->where('done', false)->count(),
                'done'    => $items->where('done', true)->count(),
                'overdue' => $items->where('overdue', true)->count(),
            ];
        }

        return $cells;
    }

    public function render()
    {
        // Huy hiệu: số việc còn lại hôm nay (tháng đang xem có thể khác tháng hiện tại)
        $today = now()->format('Y-m') === $this->month
            ? $this->items
            : collect(app(SalesCalendar::class)->month(Auth::user()->employee, CarbonImmutable::now()));

        return view('livewire.task-calendar', [
            'dayItems'   => $this->items->where('day', $this->day)->values(),
            'todayCount' => $today->where('day', now()->toDateString())->where('done', false)->count(),
        ]);
    }
}
