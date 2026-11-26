<?php

namespace App\Livewire\Reports\Concerns;

use App\Services\ReportDocumentWriter;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;

trait ReportExport
{
    use ExportPeriod {
        openExport as protected openExportPeriod;
    }

    public static int $exportPreviewRows = 50;

    public array $exportSheets = [];

    public array $exportColumns = [];

    public string $exportTab = '';

    public string $exportPick = '';

    abstract public function exportColumnCatalog(): array;

    abstract public function exportLabel(string $key, array $replace = []): string;

    abstract public function exportFilterFields(): array;

    abstract protected function resetExportFilters(): void;

    abstract protected function exportCriteria(): array;

    abstract protected function exportData(array $period, array $filters, array $sheets): array;

    abstract protected function exportRows(string $sheet, array $data): array;

    abstract protected function exportFileName(): string;

    public function openExport(): void
    {
        $this->openExportPeriod();
        $this->exportPick = 'm'.$this->exportMonth;
        $this->resetExportFilters();
        $this->exportTab = static::EXPORT_SHEETS[0];
        $this->exportSheets = array_fill_keys(static::EXPORT_SHEETS, true);
        $this->exportColumns = [];
        foreach (static::EXPORT_SHEETS as $sheet) {
            $this->setExportColumns($sheet, array_keys($this->exportColumnCatalog()[$sheet]));
        }
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

    public function exportMonths(): array
    {
        $now = CarbonImmutable::now();

        return range(1, $this->exportYear === (int) $now->year ? (int) $now->month : 12);
    }

    public function exportQuarters(): array
    {
        $now = CarbonImmutable::now();

        return range(1, $this->exportYear === (int) $now->year ? (int) $now->quarter : 4);
    }

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
            fn ($sheet) => $this->exportSheet($sheet, $data, $period, $criteria),
            $sheets
        )));

        $this->exportOpen = false;

        return response()->download($path, $this->exportFileName().'-'.$period['slug'].'.xlsx')->deleteFileAfterSend();
    }

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

    protected function exportSheetMeta(string $sheet, array $data, array $period): array
    {
        return [];
    }

    protected function expandExportColumn(string $sheet, string $column, array $data): ?array
    {
        return null;
    }

    protected function exportSheet(string $sheet, array $data, array $period, string $criteria): array
    {
        $catalog = $this->exportColumnCatalog()[$sheet];
        $columns = [];
        foreach ($catalog as $key => $type) {
            if ($this->exportColumnOn($sheet, $key)) {
                $columns = array_merge(
                    $columns,
                    $this->expandExportColumn($sheet, $key, $data) ?? [[$this->exportLabel($sheet.'.'.$key), $type, $key]]
                );
            }
        }

        $n = 0;
        $out = [];
        foreach ($this->exportRows($sheet, $data) as [$kind, $values]) {
            if (in_array($kind, ['group', 'section'], true)) {
                $n = 0;
            }
            $out[] = [$kind, array_merge(
                [$kind === 'item' ? ++$n : ($values['_stt'] ?? '')],
                array_map(fn ($c) => $values[$c[2]] ?? null, $columns)
            )];
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
