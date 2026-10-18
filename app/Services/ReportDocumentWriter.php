<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\PageMargin;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Ghi báo cáo tổng hợp dạng văn bản hành chính ra file .xlsx (để in / trình ký):
 * tên công ty + quốc hiệu, số / ngày báo cáo, tiêu đề, kỳ báo cáo, phạm vi, bảng số liệu có
 * dòng tổng cộng, nhóm (A, B / I, II) và chi tiết (1, 2...), ghi chú cách tính, khối chữ ký.
 *
 * Tài liệu: ['number' => ký hiệu số báo cáo, 'date' => ngày lập, 'preparer' => người lập, 'approver' => ?giám đốc,
 *            'sheets' => [sheet, ...]]
 * Sheet: ['name', 'title', 'period' => ?string, 'scope' => [dòng phạm vi...], 'unit' => ?string,
 *         'columns' => [[tiêu đề, kiểu], ...], 'rows' => [[loại dòng, [giá trị...]], ...], 'notes' => [...]]
 * Kiểu cột: stt | label | text | int | money | decimal | percent (giá trị 0-100) - null ghi thành ô trống.
 * Loại dòng: total (tổng cộng) | group (nhóm có số liệu) | section (tiêu đề nhóm, gộp ô hết hàng) | item.
 */
class ReportDocumentWriter
{
    const FONT = 'Times New Roman';

    const FORMATS = [
        'int'     => '#,##0',
        'money'   => '#,##0',
        'decimal' => '#,##0.0',
        'percent' => '0.0%',
    ];

    const FILLS = ['total' => 'FCE4D6', 'group' => 'EDEDED', 'section' => 'EDEDED', 'header' => 'E8F0E9'];

    // Chiều cao 1 dòng chữ cỡ 12 (point)
    const LINE_HEIGHT = 16;

    protected Writer $writer;

    protected int $sheetIndex = 0;

    protected int $rowNo = 0;

    /** @var float[] độ rộng từng cột (0-based) của sheet đang ghi */
    protected array $widths = [];

    /**
     * Tài liệu với thông tin chung: ngày lập = hôm nay, người lập = người đang đăng nhập,
     * người duyệt = Giám đốc đang làm việc.
     */
    public function document(array $sheets): array
    {
        $director = Employee::query()
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::DIRECTOR))
            ->where('status', Employee::STATUS_WORKING)
            ->orderBy('employee_id')
            ->value('full_name');

        return [
            'number'   => __('reports.document.code'),
            'date'     => now(),
            'preparer' => Auth::user()?->employee?->full_name,
            'approver' => $director,
            'sheets'   => $sheets,
        ];
    }

    /**
     * Ghi tài liệu ra file tạm, trả về đường dẫn file.
     */
    public function write(array $document): string
    {
        $options = new Options();
        $options->DEFAULT_ROW_STYLE = $this->style();
        $options->setPageSetup(new PageSetup(PageOrientation::LANDSCAPE, PaperSize::A4, fitToHeight: 0, fitToWidth: 1));
        $options->setPageMargin(new PageMargin(0.6, 0.4, 0.6, 0.6));

        $path = tempnam(sys_get_temp_dir(), 'report').'.xlsx';
        $this->writer = new Writer($options);
        $this->writer->openToFile($path);

        foreach (array_values($document['sheets']) as $i => $sheet) {
            $this->sheetIndex = $i;
            $this->rowNo = 0;
            $current = $i ? $this->writer->addNewSheetAndMakeItCurrent() : $this->writer->getCurrentSheet();
            $current->setName(mb_substr($sheet['name'], 0, 31));

            $this->widths = $this->columnWidths($sheet);
            foreach ($this->widths as $column => $width) {
                $current->setColumnWidth($width, $column + 1);
            }

            $this->writeHeading($document, $sheet);
            $this->writeTable($sheet);
            $this->writeNotes($sheet['notes'] ?? []);
            $this->writeSignatures($document);
        }

        $this->writer->close();

        return $path;
    }

    /* ── Các phần của trang ───────────────────────────────────────────── */

    /**
     * Tên công ty (trái) + quốc hiệu (phải), số / địa điểm ngày, tiêu đề, kỳ, phạm vi, đơn vị tính.
     */
    protected function writeHeading(array $document, array $sheet): void
    {
        $d = fn ($key, $replace = []) => __('reports.document.'.$key, $replace);
        $date = $document['date'];

        $this->pair([$d('company_1'), false], [$d('nation'), true]);
        $this->pair([$d('company_2'), false], [$d('motto'), true]);
        $this->pair([$d('brand'), true], [$d('rule'), false]);
        $this->pair([$d('rule'), false], ['', false]);
        $this->blank();
        $this->pair([$d('number', ['code' => $document['number']]), false],
            [$d('place_date', ['day' => $date->format('d'), 'month' => $date->format('m'), 'year' => $date->format('Y')]), false, true]);
        $this->blank();

        $this->line(mb_strtoupper($sheet['title']), $this->style()->setFontBold()->setFontSize(14), 22);
        if (! empty($sheet['period'])) {
            $this->line(mb_strtoupper($sheet['period']), $this->style()->setFontBold());
        }
        foreach ($sheet['scope'] ?? [] as $scope) {
            $this->line($scope, $this->style()->setFontItalic());
        }
        if (! empty($sheet['unit'])) {
            $this->line($sheet['unit'], $this->style()->setFontItalic()->setCellAlignment(CellAlignment::RIGHT));
        } else {
            $this->blank();
        }
    }

    protected function writeTable(array $sheet): void
    {
        $columns = array_values($sheet['columns']);

        // Dòng tiêu đề cột + dòng đánh số cột (1), (2)...
        $header = $this->style()->setFontBold()->setShouldWrapText()->setBackgroundColor(self::FILLS['header'])
            ->setCellAlignment(CellAlignment::CENTER)->setCellVerticalAlignment(CellVerticalAlignment::CENTER)->setBorder($this->border());
        $cells = array_map(fn ($c) => Cell::fromValue($c[0], $header), $columns);
        $this->add((new Row($cells))->setHeight($this->heightFor(array_column($columns, 0), 2)));

        $index = $this->style()->setFontItalic()->setFontSize(10)->setCellAlignment(CellAlignment::CENTER)->setBorder($this->border());
        $this->add(new Row(array_map(fn ($i) => Cell::fromValue('('.($i + 1).')', $index), array_keys($columns))));

        foreach ($sheet['rows'] as [$kind, $values]) {
            $this->add($this->dataRow($columns, $kind, array_values($values)));
            if ($kind === 'section') {
                $this->merge(1, count($columns) - 1);
            }
        }
    }

    protected function writeNotes(array $notes): void
    {
        if (! $notes) {
            return;
        }

        $this->blank();
        $this->line(__('reports.document.notes'), $this->style()->setFontBold()->setFontItalic()->setCellAlignment(CellAlignment::LEFT));
        $style = $this->style()->setFontItalic()->setShouldWrapText()->setCellAlignment(CellAlignment::LEFT)
            ->setCellVerticalAlignment(CellVerticalAlignment::TOP);
        $total = array_sum($this->widths);
        foreach ($notes as $note) {
            $this->line('- '.$note, $style, $this->lines($note, $total) * self::LINE_HEIGHT);
        }
    }

    /**
     * Người lập biểu (trái) và Giám đốc (phải).
     */
    protected function writeSignatures(array $document): void
    {
        $d = fn ($key) => __('reports.document.'.$key);

        $this->blank();
        $this->pair([$d('preparer'), true], [$d('approver'), true]);
        $this->pair([$d('sign_hint'), false, true], [$d('sign_hint'), false, true]);
        $this->blank();
        $this->blank();
        $this->blank();
        $this->pair([$document['preparer'] ?? '', true], [$document['approver'] ?? '', true]);
    }

    /* ── Dòng / ô ─────────────────────────────────────────────────────── */

    protected function dataRow(array $columns, string $kind, array $values): Row
    {
        $bold = $kind !== 'item';
        $cells = [];
        $texts = [];

        foreach ($columns as $i => [, $type]) {
            $value = $values[$i] ?? null;
            if ($kind === 'section' && $i > 1) {
                $value = null;
            }

            $style = $this->style()->setBorder($this->border())->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
            if ($bold) {
                $style->setFontBold();
            }
            if (isset(self::FILLS[$kind])) {
                $style->setBackgroundColor(self::FILLS[$kind]);
            }
            match ($type) {
                'stt'   => $style->setCellAlignment(CellAlignment::CENTER),
                'label', 'text' => $style->setCellAlignment(CellAlignment::LEFT)->setShouldWrapText(),
                default => $style->setCellAlignment(CellAlignment::RIGHT)->setFormat(self::FORMATS[$type] ?? '@'),
            };

            $cells[] = Cell::fromValue($this->value($type, $value), $style);
            if (in_array($type, ['label', 'text'], true)) {
                $texts[$i] = (string) $value;
            }
        }

        $lines = 1;
        foreach ($texts as $i => $text) {
            $width = $kind === 'section' ? array_sum(array_slice($this->widths, 1)) : $this->widths[$i];
            $lines = max($lines, $this->lines($text, $width));
        }

        return (new Row($cells))->setHeight($lines * self::LINE_HEIGHT + 4);
    }

    protected function value(string $type, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'percent' => round($value / 100, 4),
            'int'     => (int) $value,
            'money', 'decimal' => (float) $value,
            default   => (string) $value,
        };
    }

    /**
     * 1 dòng gộp hết chiều ngang bảng.
     */
    protected function line(string $text, Style $style, ?float $height = null): void
    {
        $style->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
        if (! $style->hasSetCellAlignment()) {
            $style->setCellAlignment(CellAlignment::CENTER);
        }

        $row = new Row($this->spread([[$text, $style, count($this->widths)]]));
        $this->add($height ? $row->setHeight($height) : $row);
        $this->merge(0, count($this->widths) - 1);
    }

    /**
     * 1 dòng chia 2 khối căn giữa: trái (tên đơn vị, người lập) và phải (quốc hiệu, giám đốc).
     * Mỗi khối: [chữ, đậm, nghiêng].
     */
    protected function pair(array $left, array $right): void
    {
        $count = count($this->widths);
        $split = max(2, intdiv($count, 2));

        $make = function (array $block) {
            $style = $this->style()->setCellAlignment(CellAlignment::CENTER)->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
            if ($block[1] ?? false) {
                $style->setFontBold();
            }
            if ($block[2] ?? false) {
                $style->setFontItalic();
            }

            return $style;
        };

        $this->add(new Row($this->spread([[$left[0], $make($left), $split], [$right[0], $make($right), $count - $split]])));
        $this->merge(0, $split - 1);
        $this->merge($split, $count - 1);
    }

    /**
     * Các khối [chữ, kiểu, số cột] → danh sách ô (ô đầu khối có chữ, các ô sau để trống cùng kiểu).
     */
    protected function spread(array $blocks): array
    {
        $cells = [];
        foreach ($blocks as [$text, $style, $span]) {
            $cells[] = Cell::fromValue($text, $style);
            for ($i = 1; $i < $span; $i++) {
                $cells[] = Cell::fromValue(null, $style);
            }
        }

        return $cells;
    }

    protected function blank(): void
    {
        $this->add(Row::fromValues([]));
    }

    protected function add(Row $row): void
    {
        $this->writer->addRow($row);
        $this->rowNo++;
    }

    /**
     * Gộp ô trên dòng vừa ghi (cột 0-based).
     */
    protected function merge(int $from, int $to): void
    {
        if ($to > $from) {
            $this->writer->getOptions()->mergeCells($from, $this->rowNo, $to, $this->rowNo, $this->sheetIndex);
        }
    }

    /* ── Kích thước ───────────────────────────────────────────────────── */

    /**
     * Độ rộng cột: STT hẹp, cột tên theo nội dung dài nhất (có xuống dòng), cột số đủ cho số lớn nhất
     * và từ dài nhất của tiêu đề.
     */
    protected function columnWidths(array $sheet): array
    {
        $widths = [];
        foreach (array_values($sheet['columns']) as $i => [$label, $type]) {
            $longestWord = max(array_map('mb_strlen', explode(' ', $label)));
            $content = 0;
            foreach ($sheet['rows'] as [$kind, $values]) {
                $value = array_values($values)[$i] ?? null;
                if ($kind === 'section' && $i > 0) {
                    continue;
                }
                $content = max($content, match ($type) {
                    'money', 'int' => strlen(number_format((float) $value)),
                    'decimal'      => strlen(number_format((float) $value, 1)),
                    'percent'      => 6,
                    default        => mb_strlen((string) $value),
                });
            }

            $widths[$i] = match ($type) {
                'stt'   => 6,
                'label' => min(38, max(22, $content + 2)),
                'text'  => min(30, max(12, $content + 2)),
                default => max(11, $longestWord + 2, $content + 3),
            };
        }

        return $widths;
    }

    protected function heightFor(array $texts, int $minLines = 1): float
    {
        $lines = $minLines;
        foreach (array_values($texts) as $i => $text) {
            $lines = max($lines, $this->lines($text, $this->widths[$i] ?? 10));
        }

        return $lines * self::LINE_HEIGHT + 4;
    }

    /**
     * Ước lượng số dòng khi xuống dòng tự động (Excel không tự giãn chiều cao ô gộp).
     */
    protected function lines(string $text, float $width): int
    {
        $perLine = max(1, $width * 1.05 - 1);
        $lines = 0;
        foreach (explode("\n", $text) as $part) {
            $lines += max(1, (int) ceil(mb_strlen($part) / $perLine));
        }

        return $lines;
    }

    protected function style(): Style
    {
        return (new Style())->setFontName(self::FONT)->setFontSize(12);
    }

    protected function border(): Border
    {
        $part = fn ($side) => new BorderPart($side, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID);

        return new Border($part(Border::LEFT), $part(Border::RIGHT), $part(Border::TOP), $part(Border::BOTTOM));
    }
}
