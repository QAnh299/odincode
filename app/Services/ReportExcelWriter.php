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
 * Ghi báo cáo ra file .xlsx: mỗi sheet gồm phần đầu (tiêu đề, dòng mô tả) rồi các bảng nối tiếp nhau.
 *
 * Sheet:  ['name', 'title', 'meta' => [dòng mô tả], 'blocks' => [bảng, ...], 'list' => bool]
 * Bảng:   ['title' => ?string, 'columns' => [[tiêu đề, kiểu], ...], 'rows' => [[giá trị, ...], ...], 'total' => ?[giá trị, ...]]
 * Kiểu cột: text | int | money | decimal | percent (giá trị 0–100, ghi ra dạng %) | datetime (timestamp)
 *           | mixed (mỗi ô tự ghi kiểu: [giá trị, kiểu] – dùng cho bảng chỉ số nhiều đơn vị)
 * 'list' = sheet chỉ có 1 bảng danh sách: bật lọc và cố định dòng tiêu đề.
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

        foreach (array_values($sheets) as $i => $sheet) {
            $current = $i ? $writer->addNewSheetAndMakeItCurrent() : $writer->getCurrentSheet();
            $current->setName(mb_substr($sheet['name'], 0, 31));
            foreach ($this->widths($sheet) as $column => $width) {
                $current->setColumnWidth($width, $column);
            }
            $this->writeSheet($writer, $sheet);
        }

        $writer->close();

        return $path;
    }

    protected function writeSheet(Writer $writer, array $sheet): void
    {
        $writer->addRow(Row::fromValues([$sheet['title']], (new Style())->setFontBold()->setFontSize(14)));
        $muted = (new Style())->setFontColor('6B7280');
        foreach ($sheet['meta'] ?? [] as $line) {
            $writer->addRow(Row::fromValues([$line], $muted));
        }

        $title = (new Style())->setFontBold()->setFontSize(12);
        $header = (new Style())->setFontBold()->setBackgroundColor('E8F0E9')->setShouldWrapText();
        $row = 1 + count($sheet['meta'] ?? []);

        foreach ($sheet['blocks'] as $block) {
            $writer->addRow(Row::fromValues(['']));
            $row++;
            if (! empty($block['title'])) {
                $writer->addRow(Row::fromValues([$block['title']], $title));
                $row++;
            }

            $writer->addRow(Row::fromValues(array_column($block['columns'], 0), $header));
            $headerRow = ++$row;

            foreach ($block['rows'] as $values) {
                $writer->addRow($this->dataRow($block['columns'], $values));
                $row++;
            }
            if (! empty($block['total'])) {
                $writer->addRow($this->dataRow($block['columns'], $block['total'], true));
                $row++;
            }

            if (! empty($sheet['list']) && $block['rows']) {
                $writer->getCurrentSheet()->setAutoFilter(new AutoFilter(0, $headerRow, count($block['columns']) - 1, $row));
                $writer->getCurrentSheet()->setSheetView((new SheetView())->setFreezeRow($headerRow + 1));
            }
        }
    }

    protected function dataRow(array $columns, array $values, bool $bold = false): Row
    {
        $cells = [];
        foreach (array_values($columns) as $i => [, $type]) {
            $value = $values[$i] ?? null;
            if (is_array($value)) {
                [$value, $type] = $value;
            }
            $style = new Style();
            if (isset(self::FORMATS[$type])) {
                $style->setFormat(self::FORMATS[$type]);
            }
            if ($bold) {
                $style->setFontBold();
            }
            $cells[] = Cell::fromValue($this->value($type, $value), $style);
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
     * Độ rộng từng cột (1-based) theo nội dung dài nhất của mọi bảng trong sheet; cột đầu không tính dòng tiêu đề sheet.
     */
    protected function widths(array $sheet): array
    {
        $widths = [];
        foreach ($sheet['blocks'] as $block) {
            foreach (array_values($block['columns']) as $i => [$label, $type]) {
                $lengths = [mb_strlen($label) * 0.9];
                foreach (array_merge($block['rows'], [$block['total'] ?? []]) as $values) {
                    $value = $values[$i] ?? null;
                    $cellType = $type;
                    if (is_array($value)) {
                        [$value, $cellType] = $value;
                    }
                    $lengths[] = match ($cellType) {
                        'datetime' => 16,
                        'money'    => strlen(number_format((float) $value)) + 2,
                        'percent'  => 7,
                        default    => mb_strlen((string) $value),
                    };
                }
                $widths[$i + 1] = max($widths[$i + 1] ?? 0, ...$lengths);
            }
        }

        return array_map(fn ($w) => min(self::MAX_WIDTH, max(self::MIN_WIDTH, ceil($w) + 2)), $widths);
    }
}
