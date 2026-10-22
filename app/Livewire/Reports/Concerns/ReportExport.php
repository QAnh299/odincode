<?php

namespace App\Livewire\Reports\Concerns;

use App\Services\ReportDocumentWriter;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;

/**
 * Popup xuất báo cáo Excel kiểu "chọn sheet, chọn cột, xem trước" (cùng cách làm với báo cáo chuyển đổi Lead):
 * kỳ = năm + (cả năm / quý / tháng), tiêu chí riêng của popup (không theo bộ lọc trên màn hình),
 * mỗi sheet bật / tắt được, mỗi sheet chọn cột (cột khoá luôn có), xem trước đúng bố cục file.
 * Giao diện: livewire.reports.partials.report-export.
 *
 * Component dùng trait khai báo:
 *   const EXPORT_SHEETS (thứ tự sheet), EXPORT_LOCKED [sheet => cột luôn có],
 *         EXPORT_DEFAULT_SHEETS, EXPORT_DEFAULT_COLUMNS [sheet => [cột...]]
 *   exportColumnCatalog()  [sheet => [cột => kiểu]] - tiêu đề cột = exportLabel("{sheet}.{cột}")
 *   exportLabel()          nhãn: dialog_title, sheet.{sheet}, title.{sheet}, {sheet}.{cột}, scope_all
 *   exportFilterFields()   ô tiêu chí của popup
 *   resetExportFilters(), exportCriteria(), exportData(), exportRows(), exportFileName(), exportYears()
 *
 * Dòng của 1 sheet (exportRows): [[loại, [cột => giá trị, '_stt' => ô STT của dòng không phải item]], ...];
 * loại: total | group | section | item - STT của item đánh lại từ 1 sau mỗi dòng group / section.
 */
trait ReportExport
{
    use ExportPeriod {
        openExport as protected openExportPeriod;
    }

    // Số dòng chi tiết hiện trong khung xem trước
    public static int $exportPreviewRows = 50;

    // Sheet được xuất [sheet => bool], cột được chọn [sheet => [cột => bool]], sheet đang xem trước
    public array $exportSheets = [];

    public array $exportColumns = [];

    public string $exportTab = '';

    // Kỳ chọn trong popup (cùng năm exportYear): 'year' = cả năm, 'q1'..'q4' = quý, 'm1'..'m12' = tháng
    public string $exportPick = '';

    abstract public function exportColumnCatalog(): array;

    abstract public function exportLabel(string $key, array $replace = []): string;

    /**
     * Ô tiêu chí trong popup: [['model' => thuộc tính Livewire, 'label', 'all' => nhãn "Tất cả", 'options' => [giá trị => nhãn]], ...].
     */
    abstract public function exportFilterFields(): array;

    abstract protected function resetExportFilters(): void;

    /**
     * Tiêu chí đã kiểm tra: [bộ lọc cho service, dòng mô tả phạm vi in trên báo cáo].
     */
    abstract protected function exportCriteria(): array;

    /**
     * Dữ liệu dùng chung cho các sheet cần xuất ($period: kỳ của ExportPeriod).
     */
    abstract protected function exportData(array $period, array $filters, array $sheets): array;

    abstract protected function exportRows(string $sheet, array $data): array;

    /**
     * Tiền tố tên file (không dấu), ví dụ "bao-cao-hieu-qua-sales".
     */
    abstract protected function exportFileName(): string;

    /**
     * Mở popup: kỳ = tháng hiện tại, tiêu chí "Tất cả", sheet và cột theo mặc định.
     */
    public function openExport(): void
    {
        $this->openExportPeriod();
        $this->exportPick = 'm'.$this->exportMonth;
        $this->resetExportFilters();
        $this->exportTab = static::EXPORT_SHEETS[0];
        $this->exportSheets = array_combine(static::EXPORT_SHEETS,
            array_map(fn ($sheet) => in_array($sheet, static::EXPORT_DEFAULT_SHEETS, true), static::EXPORT_SHEETS));
        $this->exportColumns = [];
        foreach (static::EXPORT_SHEETS as $sheet) {
            $this->setExportColumns($sheet, static::EXPORT_DEFAULT_COLUMNS[$sheet]);
        }
    }

    public function defaultExportColumns(): void
    {
        $tab = $this->activeExportTab();
        $this->setExportColumns($tab, static::EXPORT_DEFAULT_COLUMNS[$tab]);
    }

    public function allExportColumns(): void
    {
        $tab = $this->activeExportTab();
        $this->setExportColumns($tab, array_keys($this->exportColumnCatalog()[$tab]));
    }

    protected function setExportColumns(string $sheet, array $on): void
    {
        $columns = array_keys($this->exportColumnCatalog()[$sheet]);
        $this->exportColumns[$sheet] = array_combine($columns, array_map(fn ($c) => in_array($c, $on, true), $columns));
    }

    /**
     * Tháng chọn được của năm đang chọn (năm nay thì đến tháng hiện tại).
     */
    public function exportMonths(): array
    {
        $now = CarbonImmutable::now();

        return range(1, $this->exportYear === (int) $now->year ? (int) $now->month : 12);
    }

    /**
     * Quý chọn được của năm đang chọn (năm nay thì đến quý hiện tại).
     */
    public function exportQuarters(): array
    {
        $now = CarbonImmutable::now();

        return range(1, $this->exportYear === (int) $now->year ? (int) $now->quarter : 4);
    }

    /**
     * Đổi năm: quý / tháng đang chọn chưa tới ở năm mới thì lùi về quý / tháng gần nhất.
     */
    public function updatedExportYear(): void
    {
        if (preg_match('/^([qm])(\d+)$/', $this->exportPick, $m)) {
            $max = max($m[1] === 'q' ? $this->exportQuarters() : $this->exportMonths());
            $this->exportPick = $m[1].min((int) $m[2], $max);
        }
    }

    public function exportColumnOn(string $sheet, string $column): bool
    {
        return $column === static::EXPORT_LOCKED[$sheet] || (bool) ($this->exportColumns[$sheet][$column] ?? true);
    }

    public function exportSheetOn(string $sheet): bool
    {
        return (bool) ($this->exportSheets[$sheet] ?? true);
    }

    public function activeExportTab(): string
    {
        return in_array($this->exportTab, static::EXPORT_SHEETS, true) ? $this->exportTab : static::EXPORT_SHEETS[0];
    }

    /**
     * Xem trước sheet đang mở: đúng bố cục file (phần đầu văn bản, bảng, chữ ký), tối đa $exportPreviewRows dòng chi tiết.
     */
    #[Computed]
    public function exportPreview(): ?array
    {
        $period = $this->resolveExportPeriod();
        if (! $period) {
            return null;
        }

        [$filters, $criteria] = $this->exportCriteria();
        $tab = $this->activeExportTab();
        $sheet = $this->exportSheet($tab, $this->exportData($period, $filters, [$tab]), $period, $criteria);

        // Giữ các dòng tổng / nhóm, chỉ cắt bớt dòng chi tiết
        $items = 0;
        $rows = [];
        foreach ($sheet['rows'] as $row) {
            if ($row[0] === 'item' && ++$items > static::$exportPreviewRows) {
                continue;
            }
            $rows[] = $row;
        }
        $sheet['rows'] = $rows;

        return [
            'sheet'    => $sheet,
            'items'    => $items,
            'shown'    => min($items, static::$exportPreviewRows),
            'document' => app(ReportDocumentWriter::class)->document([]),
        ];
    }

    /**
     * Báo cáo dạng văn bản (quốc hiệu, tiêu ngữ, ngày lập, khối chữ ký) theo kỳ, tiêu chí, sheet và cột đã chọn.
     */
    public function export(ReportDocumentWriter $writer)
    {
        abort_unless($this->canExport, 403);

        $period = $this->resolveExportPeriod();
        if (! $period) {
            return null;
        }

        $sheets = array_values(array_filter(static::EXPORT_SHEETS, fn ($sheet) => $this->exportSheetOn($sheet)));
        if (! $sheets) {
            $this->addError('exportSheets', __('reports.export_ui.err_no_sheet'));

            return null;
        }

        [$filters, $criteria] = $this->exportCriteria();
        $data = $this->exportData($period, $filters, $sheets);
        $path = $writer->write($writer->document(array_map(
            fn ($sheet) => $this->exportSheet($sheet, $data, $period, $criteria), $sheets)));

        $this->exportOpen = false;

        return response()->download($path, $this->exportFileName().'-'.$period['slug'].'.xlsx')->deleteFileAfterSend();
    }

    /**
     * Kỳ xuất theo exportPick: cả năm / quý / tháng (giá trị lạ coi như tháng hiện tại).
     */
    protected function resolveExportPeriod(): ?array
    {
        preg_match('/^(year|q([1-4])|m(\d{1,2}))$/', $this->exportPick, $m);
        if (($m[1] ?? '') === 'year') {
            $this->exportType = 'year';
        } elseif (! empty($m[2])) {
            $this->exportType = 'quarter';
            $this->exportQuarter = (int) $m[2];
        } else {
            $this->exportType = 'month';
            $this->exportMonth = ! empty($m[3]) ? max(1, min(12, (int) $m[3])) : (int) CarbonImmutable::now()->month;
        }
        $this->resetErrorBag(['exportYear', 'exportType']);

        return $this->exportPeriod();
    }

    /**
     * Thông tin thêm của 1 sheet (ghi đè phần đầu): 'unit', 'notes', 'period'...
     */
    protected function exportSheetMeta(string $sheet, array $data, array $period): array
    {
        return [];
    }

    /**
     * 1 cột chọn được trải thành nhiều cột của file (ví dụ "Các tháng"): [[tiêu đề, kiểu, khoá giá trị], ...]; null = 1 cột thường.
     */
    protected function expandExportColumn(string $sheet, string $column, array $data): ?array
    {
        return null;
    }

    /**
     * 1 sheet của tài liệu: cột STT + các cột đã chọn.
     */
    protected function exportSheet(string $sheet, array $data, array $period, string $criteria): array
    {
        $catalog = $this->exportColumnCatalog()[$sheet];
        $columns = [];
        foreach ($catalog as $key => $type) {
            if ($this->exportColumnOn($sheet, $key)) {
                $columns = array_merge($columns,
                    $this->expandExportColumn($sheet, $key, $data) ?? [[$this->exportLabel($sheet.'.'.$key), $type, $key]]);
            }
        }

        $n = 0;
        $out = [];
        foreach ($this->exportRows($sheet, $data) as [$kind, $values]) {
            if (in_array($kind, ['group', 'section'], true)) {
                $n = 0;
            }
            $out[] = [$kind, array_merge([$kind === 'item' ? ++$n : ($values['_stt'] ?? '')],
                array_map(fn ($c) => $values[$c[2]] ?? null, $columns))];
        }

        return array_merge([
            'name'    => $this->exportLabel('sheet.'.$sheet),
            'title'   => $this->exportLabel('title.'.$sheet),
            'period'  => $period['label'],
            'scope'   => [$period['range'], $criteria],
            'columns' => array_merge([[__('reports.export_ui.stt'), 'stt']], array_map(fn ($c) => [$c[0], $c[1]], $columns)),
            'rows'    => $out,
        ], $this->exportSheetMeta($sheet, $data, $period));
    }
}
