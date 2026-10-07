<?php

namespace App\Livewire\Courses;

use App\Models\Course;
use App\Models\Invoice;
use App\Models\QuotationDetail;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    // Khoảng tối đa của bộ lọc lịch sử (tháng)
    const MAX_MONTHS = 36;

    // Mặc định: 12 tháng gần nhất
    const DEFAULT_MONTHS = 12;

    public Course $course;

    // Khoảng thời gian của lịch sử kinh doanh, dạng YYYY-MM
    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function mount(Course $course): void
    {
        $this->course = $course;

        // Ô chọn tháng luôn hiện đúng khoảng đang xem (mặc định 12 tháng gần nhất)
        [$from, $to] = $this->period;
        $this->from = $from->format('Y-m');
        $this->to = $to->format('Y-m');
    }

    public function resetPeriod(): void
    {
        [$from, $to] = $this->defaultPeriod();
        $this->from = $from->format('Y-m');
        $this->to = $to->format('Y-m');
        unset($this->period, $this->monthly);
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * Sale Leader / Salesperson chỉ thấy số liệu từ báo giá của nhóm / của mình.
     */
    #[Computed]
    public function limitedScope(): bool
    {
        return in_array(Auth::user()->role_name, [Role::SALE_LEADER, Role::SALESPERSON], true);
    }

    /**
     * Mỗi dòng = phần của khóa học trong một đơn hàng:
     * [month, registrations, revenue, paid, remaining, opportunity_id].
     */
    #[Computed]
    public function sales(): Collection
    {
        $employee = Auth::user()->employee;

        return QuotationDetail::query()
            ->where('course_id', $this->course->course_id)
            ->whereHas('quotation', fn ($q) => $q->visibleTo($employee))
            ->whereHas('quotation.order', fn ($q) => $q->where('status', '<>', SalesOrder::STATUS_CANCELLED))
            ->with(['quotation' => fn ($q) => $q
                ->withSum('details as lines_total', 'line_total')
                ->with(['order' => fn ($q) => $q->withSum(
                    ['invoices as paid_sum' => fn ($q) => $q->where('status', Invoice::STATUS_PAID)],
                    'payment_amount'
                )]),
            ])
            ->get()
            ->map(function (QuotationDetail $line) {
                $quotation = $line->quotation;
                $order = $quotation->order;

                $linesTotal = (float) $quotation->lines_total;
                $share = $linesTotal > 0 ? (float) $line->line_total / $linesTotal : 0;

                $revenue = round((float) $order->total_amount * $share, 2);
                $paid = round(min((float) $order->paid_sum, (float) $order->total_amount) * $share, 2);

                return [
                    'month'          => $order->created_at->format('Y-m'),
                    'registrations'  => (int) $line->quantity,
                    'revenue'        => $revenue,
                    'paid'           => $paid,
                    'remaining'      => max(0, $revenue - $paid),
                    'opportunity_id' => $quotation->opportunity_id,
                ];
            });
    }

    /**
     * Tổng quan kinh doanh (toàn thời gian).
     */
    #[Computed]
    public function overview(): array
    {
        $sales = $this->sales;
        $opportunities = $sales->pluck('opportunity_id')->unique()->values();

        return [
            'registrations' => $sales->sum('registrations'),
            'revenue'       => $sales->sum('revenue'),
            'paid'          => $sales->sum('paid'),
            'remaining'     => $sales->sum('remaining'),
            'students'      => $opportunities->isEmpty() ? 0
                : Student::query()->whereIn('opportunity_id', $opportunities)->count(),
        ];
    }

    /**
     * Khoảng tháng đang xem: [from, to] (đầu tháng). Bộ lọc sai / trống thì dùng 12 tháng gần nhất.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    #[Computed]
    public function period(): array
    {
        $to = $this->parseMonth($this->to) ?? $this->defaultPeriod()[1];
        $from = $this->parseMonth($this->from) ?? $to->subMonths(self::DEFAULT_MONTHS - 1);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        // Giới hạn độ dài khoảng để biểu đồ còn đọc được
        if ($from->diffInMonths($to) >= self::MAX_MONTHS) {
            $from = $to->subMonths(self::MAX_MONTHS - 1);
        }

        return [$from, $to];
    }

    /**
     * Lịch sử kinh doanh theo tháng (theo tháng tạo đơn hàng), đủ mọi tháng trong khoảng, cũ → mới.
     */
    #[Computed]
    public function monthly(): Collection
    {
        [$from, $to] = $this->period;
        $byMonth = $this->sales->groupBy('month');

        $months = collect();
        for ($month = $from; $month->lessThanOrEqualTo($to); $month = $month->addMonth()) {
            $rows = $byMonth->get($month->format('Y-m'), collect());

            $months->push([
                'month'         => $month,
                'registrations' => $rows->sum('registrations'),
                'revenue'       => $rows->sum('revenue'),
                'paid'          => $rows->sum('paid'),
                'remaining'     => $rows->sum('remaining'),
            ]);
        }

        return $months;
    }

    public function hasCustomPeriod(): bool
    {
        [$from, $to] = $this->defaultPeriod();
        [$currentFrom, $currentTo] = $this->period;

        return ! $currentFrom->equalTo($from) || ! $currentTo->equalTo($to);
    }

    /**
     * 12 tháng gần nhất, tính đến tháng hiện tại.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function defaultPeriod(): array
    {
        $to = CarbonImmutable::today()->startOfMonth();

        return [$to->subMonths(self::DEFAULT_MONTHS - 1), $to];
    }

    public function render()
    {
        return view('livewire.courses.show')
            ->title(__('courses.detail_title', ['code' => $this->course->course_id]));
    }

    private function parseMonth(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m', $value)->startOfMonth();
    }
}
