<?php

namespace App\Livewire\Reports\Concerns;

use Carbon\CarbonImmutable;

/**
 * Popup chọn thời gian trước khi xuất báo cáo Excel: tháng / quý / kỳ lũy kế từ đầu năm (3, 6, 9 tháng) / năm.
 * Kỳ chưa kết thúc thì số liệu tính đến hôm nay. Component dùng trait khai báo exportYears() và export().
 */
trait ExportPeriod
{
    const EXPORT_TYPES = ['month', 'quarter', 'span', 'year'];

    const EXPORT_SPANS = [3, 6, 9];

    public bool $exportOpen = false;

    public string $exportType = 'month';

    public int $exportYear = 0;

    public int $exportMonth = 1;

    public int $exportQuarter = 1;

    public int $exportSpan = 6;

    /**
     * Các năm chọn được (mới nhất trước).
     */
    abstract public function exportYears(): array;

    public function openExport(): void
    {
        abort_unless($this->canExport, 403);

        $now = CarbonImmutable::now();
        $this->exportYear = (int) $now->year;
        $this->exportMonth = (int) $now->month;
        $this->exportQuarter = (int) $now->quarter;
        $this->resetErrorBag();
        $this->exportOpen = true;
    }

    public function closeExport(): void
    {
        $this->exportOpen = false;
        $this->resetErrorBag();
    }

    /**
     * Kỳ xuất đã kiểm tra: from, to (đã chặn ở hôm nay), months (Y-m trong kỳ), label (in trên báo cáo),
     * range ("Từ ngày ... đến ngày ..."), slug (tên file). Không hợp lệ → null và gắn lỗi vào popup.
     */
    protected function exportPeriod(): ?array
    {
        $type = in_array($this->exportType, self::EXPORT_TYPES, true) ? $this->exportType : 'month';
        $year = $this->exportYear;
        $p = fn ($key, $replace = []) => __('reports.export_period.'.$key, $replace);

        if (! in_array($year, $this->exportYears(), true)) {
            $this->addError('exportYear', $p('err_year'));

            return null;
        }

        $jan = CarbonImmutable::create($year, 1, 1)->startOfDay();
        if ($type === 'month') {
            $month = max(1, min(12, $this->exportMonth));
            $from = $jan->setMonth($month);
            $end = $from->endOfMonth();
            $label = $p('label_month', ['month' => $from->format('m'), 'year' => $year]);
            $slug = 'thang-'.$from->format('m')."-$year";
        } elseif ($type === 'quarter') {
            $quarter = max(1, min(4, $this->exportQuarter));
            $from = $jan->setMonth(($quarter - 1) * 3 + 1);
            $end = $from->addMonths(2)->endOfMonth();
            $label = $p('label_quarter', ['quarter' => ['I', 'II', 'III', 'IV'][$quarter - 1], 'year' => $year]);
            $slug = "quy-$quarter-$year";
        } elseif ($type === 'span') {
            // Lũy kế từ đầu năm, như "Báo cáo 6 tháng năm ..."
            $span = in_array($this->exportSpan, self::EXPORT_SPANS, true) ? $this->exportSpan : 6;
            $from = $jan;
            $end = $jan->addMonths($span - 1)->endOfMonth();
            $label = $p('label_span', ['months' => $span, 'year' => $year]);
            $slug = "$span-thang-$year";
        } else {
            $from = $jan;
            $end = $jan->endOfYear();
            $label = $p('label_year', ['year' => $year]);
            $slug = "nam-$year";
        }

        $today = CarbonImmutable::now()->endOfDay();
        if ($from->greaterThan($today)) {
            $this->addError('exportType', $p('err_future'));

            return null;
        }

        $to = $end->min($today);
        $months = [];
        for ($m = $from->startOfMonth(); $m <= $to; $m = $m->addMonth()) {
            $months[] = $m->format('Y-m');
        }

        return [
            'from'   => $from,
            'to'     => $to,
            'months' => $months,
            'label'  => $label,
            'range'  => $p($to->lessThan($end) ? 'range_partial' : 'range', ['from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y')]),
            'slug'   => $slug,
        ];
    }
}
