<?php

namespace App\Services;

use DateTimeImmutable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Ghi báo cáo dạng danh sách ra file .xlsx: mỗi sheet đúng 1 bảng, dòng 1 là tiêu đề cột
 * (cố định khi cuộn, có bộ lọc), sau đó là dữ liệu và dòng tổng (nếu có).
 *
 * Sheet: ['name', 'columns' => [[tiêu đề, kiểu], ...], 'rows' => [[giá trị, ...], ...], 'total' => ?[giá trị, ...]]
 * Kiểu cột: text | int | money | decimal | percent (giá trị 0-100, ghi ra dạng %) | datetime (timestamp)
 * Giá trị null ghi thành ô trống.
 */
class ReportExcelWriter
{
    const FORMATS = [
        'int'      => '#,##0',
        'money'    => '#,##0',
        'decimal'  => '#,##0.0',
        'percent'  => '0.0%',
        'datetime' => 'dd/mm/yyyy hh:mm',
    ];

    const MIN_WIDTH = 10;

    const MAX_WIDTH = 48;

    /**
     * Ghi các sheet ra file tạm, trả về đường dẫn file.
     */
    public function write(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'report').'.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);

        $header = (new Style())->setFontBold()->setBackgroundColor('E8F0E9')->setShouldWrapText();

        foreach (array_values($sheets) as $i => $sheet) {
            $current = $i ? $writer->addNewSheetAndMakeItCurrent() : $writer->getCurrentSheet();
            $current->setName(mb_substr($sheet['name'], 0, 31));
            foreach ($this->widths($sheet) as $column => $width) {
                $current->setColumnWidth($width, $column);
            }

            $writer->addRow(Row::fromValues(array_column($sheet['columns'], 0), $header));
            foreach ($sheet['rows'] as $values) {
                $writer->addRow($this->dataRow($sheet['columns'], $values));
            }
            if (! empty($sheet['total'])) {
                $writer->addRow($this->dataRow($sheet['columns'], $sheet['total'], true));
            }

            // Bộ lọc chỉ phủ phần dữ liệu (không gồm dòng tổng) để sắp xếp không kéo dòng tổng theo
            $current->setSheetView((new SheetView())->setFreezeRow(2));
            if ($sheet['rows']) {
                $current->setAutoFilter(new AutoFilter(0, 1, count($sheet['columns']) - 1, count($sheet['rows']) + 1));
            }
        }

        $writer->close();

        return $path;
    }

    protected function dataRow(array $columns, array $values, bool $bold = false): Row
    {
        $cells = [];
        foreach (array_values($columns) as $i => [, $type]) {
            $style = new Style();
            if (isset(self::FORMATS[$type])) {
                $style->setFormat(self::FORMATS[$type]);
            }
            if ($bold) {
                $style->setFontBold();
            }
            $cells[] = Cell::fromValue($this->value($type, $values[$i] ?? null), $style);
        }

        return new Row($cells);
    }

    protected function value(string $type, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'percent'  => round($value / 100, 4),
            'datetime' => (new DateTimeImmutable())->setTimestamp((int) $value),
            'int'      => (int) $value,
            'money', 'decimal' => (float) $value,
            default    => (string) $value,
        };
    }

    /**
     * Độ rộng từng cột (1-based) theo nội dung dài nhất của cột.
     */
    protected function widths(array $sheet): array
    {
        $widths = [];
        foreach (array_values($sheet['columns']) as $i => [$label, $type]) {
            $lengths = [mb_strlen($label) * 0.9];
            foreach (array_merge($sheet['rows'], [$sheet['total'] ?? []]) as $values) {
                $value = $values[$i] ?? null;
                $lengths[] = match ($type) {
                    'datetime' => 16,
                    'money'    => strlen(number_format((float) $value)) + 2,
                    'percent'  => 7,
                    default    => mb_strlen((string) $value),
                };
            }
            $widths[$i + 1] = min(self::MAX_WIDTH, max(self::MIN_WIDTH, ceil(max($lengths)) + 2));
        }

        return $widths;
    }
}
