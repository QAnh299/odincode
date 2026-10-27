<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\ReportExport;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Role;
use App\Services\LeadSourceReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard & Báo cáo → Phân tích chất lượng nguồn Lead (Giám đốc, Sale Admin).
 *
 * Trả lời 1 câu hỏi: nguồn nào mang về Lead tốt (chốt được, ra doanh thu)?
 *   - 4 thẻ KPI: tổng Lead, nguồn nhiều Lead nhất, nguồn chốt tốt nhất, nguồn doanh thu cao nhất
 *   - 4 biểu đồ theo nguồn: số Lead & Lead chốt, tỷ lệ chốt, doanh thu, doanh thu / Lead
 *   - bảng tóm tắt theo nguồn
 * Tỷ lệ chốt = Lead chốt thành công (có Cơ hội Won) / Lead, tính theo ngày tạo Lead - xem App\Services\LeadSourceReport.
 * Xuất Excel: popup chọn kỳ, tiêu chí, sheet, cột và xem trước (trait ReportExport) - Giám đốc, Sale Admin.
 */
#[Layout('layouts.app')]
class LeadSources extends Component
{
    use ReportExport;

    // Bảng màu phân loại (thứ tự cố định) - dùng chung các trang báo cáo
    const PALETTE = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];

    const OTHER_COLOR = '#9ca3af';

    // Màu cố định theo chỉ số
    const COLORS = [
        'leads'            => '#c3cad5',
        'won'              => '#1baf7a',
        'win_rate'         => '#1baf7a',
        'revenue'          => '#2a78d6',
        'revenue_per_lead' => '#6250d6',
    ];

    // Nguồn ít hơn số Lead này không xét "chốt tốt nhất" (tỷ lệ chưa đáng tin)
    const MIN_LEADS = 10;

    /* ── Bộ lọc ───────────────────────────────────────────────────────── */

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $branch = '';

    #[Url(except: '')]
    public string $method = '';

    public function resetFilters(): void
    {
        $this->reset('from', 'to', 'branch', 'method');
    }

    public function hasFilters(): bool
    {
        return $this->from !== '' || $this->to !== '' || $this->branch !== '' || $this->method !== '';
    }

    /* ── Số liệu ──────────────────────────────────────────────────────── */

    #[Computed]
    public function totals(): array
    {
        return $this->report()->totals($this->filterValues());
    }

    /**
     * Chỉ số theo nguồn có Lead trong phạm vi lọc, nhiều Lead trước.
     */
    #[Computed]
    public function bySource(): array
    {
        $rows = $this->report()->aggregate($this->filterValues(), 'source');
        usort($rows, fn ($a, $b) => $b['leads'] <=> $a['leads'] ?: strcmp($a['dim'], $b['dim']));

        return $rows;
    }

    /**
     * Nguồn nổi bật cho thẻ KPI. Bằng nhau thì nguồn nhiều Lead hơn được chọn.
     */
    #[Computed]
    public function highlights(): array
    {
        $pick = function (array $rows, string $metric) {
            usort($rows, fn ($a, $b) => $b[$metric] <=> $a[$metric] ?: $b['leads'] <=> $a['leads']);

            return $rows[0] ?? null;
        };
        $rows = $this->bySource;
        $enough = array_filter($rows, fn ($r) => $r['leads'] >= self::MIN_LEADS);
        $revenue = $pick($rows, 'revenue');

        return [
            'leads'   => $pick($rows, 'leads'),
            'win'     => $pick($enough ?: $rows, 'win_rate'),
            'revenue' => $revenue && $revenue['revenue'] > 0 ? $revenue : null,
        ];
    }

    /**
     * 4 biểu đồ theo nguồn, mỗi biểu đồ sắp giảm dần theo chỉ số của nó.
     */
    #[Computed]
    public function charts(): array
    {
        $chart = function (string $type, string $unit, array $metrics) {
            $rows = $this->bySource;
            usort($rows, fn ($a, $b) => $b[end($metrics)] <=> $a[end($metrics)] ?: $b['leads'] <=> $a['leads']);

            return ['type' => $type, 'unit' => $unit, 'labels' => array_column($rows, 'dim'),
                'series' => array_map(fn ($m) => ['label' => __('reports.metric.'.$m), 'color' => self::COLORS[$m],
                    'data' => array_column($rows, $m)], $metrics)];
        };

        return [
            'volume'           => $chart('bar_h', 'count', ['leads', 'won']),
            'win_rate'         => $chart('bar_h', 'percent', ['win_rate']),
            'revenue'          => $chart('bar_h', 'money', ['revenue']),
            'revenue_per_lead' => $chart('bar_h', 'money', ['revenue_per_lead']),
        ];
    }

    /* ── Xuất Excel (popup chọn sheet / cột / xem trước - trait ReportExport) ── */

    // Sheet: theo nguồn · theo kênh & chi nhánh · theo tháng · Lead mới nguồn × tháng · danh sách Lead
    const EXPORT_SHEETS = ['summary', 'breakdown', 'months', 'matrix', 'leads'];

    const EXPORT_LOCKED = ['summary' => 'source', 'breakdown' => 'criteria', 'months' => 'month', 'matrix' => 'source', 'leads' => 'name'];

    // Mặc định: sheet tổng hợp với các cột trả lời "nguồn nào đáng đầu tư"; danh sách Lead chọn thêm khi cần đối chiếu
    const EXPORT_DEFAULT_SHEETS = ['summary', 'breakdown', 'months', 'matrix'];

    const EXPORT_DEFAULT_COLUMNS = [
        'summary'   => ['source', 'leads', 'converted', 'conversion_rate', 'won', 'win_rate', 'close_rate', 'revenue', 'revenue_per_lead', 'revenue_share', 'note'],
        'breakdown' => ['criteria', 'leads', 'conversion_rate', 'won', 'win_rate', 'revenue', 'revenue_per_lead'],
        'months'    => ['month', 'leads', 'converted', 'conversion_rate', 'won', 'win_rate', 'revenue'],
        'matrix'    => ['source', 'months', 'sum'],
        'leads'     => ['name', 'created', 'source', 'method', 'branch', 'owner', 'outcome', 'revenue'],
    ];

    // Chỉ số dùng chung cho các sheet tổng hợp → kiểu cột
    const EXPORT_METRICS = [
        'leads' => 'int', 'converted' => 'int', 'conversion_rate' => 'percent', 'won' => 'int', 'lost' => 'int',
        'win_rate' => 'percent', 'close_rate' => 'percent', 'revenue' => 'money', 'paid' => 'money',
        'expected' => 'money', 'revenue_per_lead' => 'money', 'revenue_share' => 'percent',
    ];

    // Tiêu chí riêng của popup ('' = tất cả)
    public string $exportBranch = '';

    public string $exportMethod = '';

    public string $exportSource = '';

    /**
     * Xuất Excel: Giám đốc, Sale Admin.
     */
    #[Computed]
    public function canExport(): bool
    {
        return in_array(Auth::user()->role_name, [Role::DIRECTOR, Role::SALE_ADMIN], true);
    }

    public function exportColumnCatalog(): array
    {
        return [
            'summary'   => ['source' => 'label'] + self::EXPORT_METRICS + ['note' => 'text'],
            'breakdown' => ['criteria' => 'label'] + self::EXPORT_METRICS,
            'months'    => ['month' => 'label'] + self::EXPORT_METRICS,
            'matrix'    => ['source' => 'label', 'months' => 'int', 'sum' => 'int'],
            'leads'     => [
                'id' => 'text', 'name' => 'label', 'created' => 'text', 'source' => 'text', 'method' => 'text',
                'branch' => 'text', 'status' => 'text', 'owner' => 'text', 'outcome' => 'text', 'revenue' => 'money',
            ],
        ];
    }

    /**
     * Tiêu đề cột của các sheet tổng hợp dùng chung nhóm "metric".
     */
    public function exportLabel(string $key, array $replace = []): string
    {
        [$sheet, $column] = array_pad(explode('.', $key, 2), 2, null);
        if (in_array($sheet, ['summary', 'breakdown', 'months'], true) && isset(self::EXPORT_METRICS[$column])) {
            $key = 'metric.'.$column;
        }

        return __('reports.lead_sources.export.'.$key, $replace);
    }

    public function exportFilterFields(): array
    {
        return [
            ['model' => 'exportBranch', 'label' => __('reports.branch'), 'all' => __('reports.all_branches'),
                'options' => $this->branches->pluck('branch_name', 'branch_id')->all()],
            ['model' => 'exportMethod', 'label' => __('reports.method'), 'all' => __('reports.lead_sources.export.all_methods'),
                'options' => collect(Lead::CONTACT_METHODS)->mapWithKeys(fn ($m) => [$m => $this->label('method', $m)])->all()],
            ['model' => 'exportSource', 'label' => __('reports.dimension.source'), 'all' => __('reports.all'),
                'options' => array_combine($this->allSources, $this->allSources)],
        ];
    }

    protected function resetExportFilters(): void
    {
        $this->reset('exportBranch', 'exportMethod', 'exportSource');
    }

    protected function exportCriteria(): array
    {
        $branch = $this->branches->firstWhere('branch_id', $this->exportBranch);
        $method = in_array($this->exportMethod, Lead::CONTACT_METHODS, true) ? $this->exportMethod : null;
        $source = in_array($this->exportSource, $this->allSources, true) ? $this->exportSource : null;

        $parts = array_filter([
            $branch ? __('reports.branch').': '.$branch->branch_name : null,
            $method ? __('reports.method').': '.$this->label('method', $method) : null,
            $source ? __('reports.dimension.source').': '.$source : null,
        ]);

        return [
            ['branch' => $branch?->branch_id, 'method' => $method, 'sources' => $source ? [$source] : null],
            $parts ? implode(' · ', $parts) : $this->exportLabel('scope_all'),
        ];
    }

    /**
     * Số liệu Lead tạo trong kỳ theo tiêu chí; chỉ tính phần có sheet cần.
     */
    protected function exportData(array $period, array $filters, array $sheets): array
    {
        $f = ['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()] + $filters;
        $need = fn (string ...$names) => (bool) array_intersect($names, $sheets);

        // Tháng: đủ các tháng của kỳ (tháng chưa có Lead ghi 0)
        $byMonth = $need('months', 'matrix') ? array_column($this->report()->aggregate($f, 'month'), null, 'dim') : [];
        $cells = [];
        if ($need('matrix')) {
            foreach ($this->report()->aggregate($f, 'month', 'source') as $r) {
                $cells[$r['group']][$r['dim']] = $r['leads'];
            }
        }

        return [
            'total'   => $this->report()->totals($f),
            'sources' => $need('summary', 'matrix') ? $this->sorted($this->report()->aggregate($f, 'source')) : [],
            'methods' => $need('breakdown') ? $this->sorted($this->report()->aggregate($f, 'method')) : [],
            'branchs' => $need('breakdown') ? $this->sorted($this->report()->aggregate($f, 'branch')) : [],
            'months'  => array_map(fn ($m) => $byMonth[$m] ?? $this->report()->derive(['dim' => $m]), $period['months']),
            'cells'   => $cells,
            'leads'   => $need('leads') ? $this->report()->leads($f) : [],
        ];
    }

    protected function exportRows(string $sheet, array $data): array
    {
        $total = $data['total'];
        $metrics = fn (array $r) => array_intersect_key($r, self::EXPORT_METRICS) + [
            'revenue_share' => $total['revenue'] > 0 ? round($r['revenue'] * 100 / $total['revenue'], 1) : null,
        ];

        switch ($sheet) {
            case 'summary':
                $notes = $this->sourceNotes($data['sources']);
                $rows = [['total', ['source' => $this->exportLabel('total')] + $metrics($total)]];
                foreach ($data['sources'] as $r) {
                    $rows[] = ['item', ['source' => $r['dim'], 'note' => $notes[$r['dim']] ?? null] + $metrics($r)];
                }

                return $rows;

            case 'breakdown':
                // Mỗi phần (A kênh, B chi nhánh) cộng lại đúng bằng tổng cộng
                $rows = [['total', ['criteria' => $this->exportLabel('total')] + $metrics($total)]];
                foreach (['method' => $data['methods'], 'branch' => $data['branchs']] as $i => $items) {
                    $rows[] = ['section', ['_stt' => $i === 'method' ? 'A' : 'B', 'criteria' => mb_strtoupper($this->exportLabel('by_'.$i))]];
                    foreach ($items as $r) {
                        $rows[] = ['item', ['criteria' => $this->label($i, $r['dim'])] + $metrics($r)];
                    }
                }

                return $rows;

            case 'months':
                $rows = [['total', ['month' => $this->exportLabel('total')] + $metrics($total)]];
                foreach ($data['months'] as $r) {
                    $rows[] = ['item', ['month' => $this->exportLabel('month', ['month' => $this->label('month', $r['dim'])])] + $metrics($r)];
                }

                return $rows;

            case 'matrix':
                $months = array_column($data['months'], 'dim');
                $rows = [['total', ['source' => $this->exportLabel('total'), 'sum' => $total['leads']]
                    + array_combine(array_map(fn ($m) => 'm:'.$m, $months), array_column($data['months'], 'leads'))]];
                foreach ($data['sources'] as $r) {
                    $rows[] = ['item', ['source' => $r['dim'], 'sum' => $r['leads']]
                        + array_combine(array_map(fn ($m) => 'm:'.$m, $months), array_map(fn ($m) => $data['cells'][$r['dim']][$m] ?? 0, $months))];
                }

                return $rows;

            default:
                return array_map(fn ($l) => ['item', [
                    'created' => CarbonImmutable::parse($l['created'])->format('d/m/Y H:i'),
                    'method'  => $this->label('method', $l['method']),
                    'branch'  => $this->label('branch', $l['branch']),
                    'status'  => $this->label('status', $l['status']),
                    'outcome' => __('reports.lead_conversion.outcome.'.$l['outcome']),
                ] + $l], $data['leads']);
        }
    }

    /**
     * Cột "Các tháng" của sheet nguồn × tháng: mỗi tháng của kỳ 1 cột.
     */
    protected function expandExportColumn(string $sheet, string $column, array $data): ?array
    {
        if ($sheet !== 'matrix' || $column !== 'months') {
            return null;
        }

        return array_map(fn ($r) => [$this->label('month', $r['dim']), 'int', 'm:'.$r['dim']], $data['months']);
    }

    protected function exportSheetMeta(string $sheet, array $data, array $period): array
    {
        return ['unit' => $this->exportLabel(in_array($sheet, ['matrix'], true) ? 'unit_trend' : 'unit')];
    }

    protected function exportFileName(): string
    {
        return 'bao-cao-chat-luong-nguon-lead';
    }

    /**
     * Năm chọn được trong popup xuất: từ năm có Lead sớm nhất đến năm hiện tại.
     */
    public function exportYears(): array
    {
        $first = Lead::query()->min('created_at');
        $current = (int) now()->year;

        return range($current, $first ? min($current, (int) CarbonImmutable::parse($first)->year) : $current);
    }

    /**
     * Ghi chú cho nguồn nổi bật (khi có từ 2 nguồn): chuyển đổi cao / thấp nhất, doanh thu cao nhất.
     */
    protected function sourceNotes(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn ($r) => $r['leads'] > 0));
        if (count($rows) < 2) {
            return [];
        }

        $pick = function (string $metric, bool $max) use ($rows) {
            // Bằng nhau thì nguồn nhiều Lead hơn được chọn (tỷ lệ đáng tin hơn)
            usort($rows, fn ($a, $b) => ($max ? $b[$metric] <=> $a[$metric] : $a[$metric] <=> $b[$metric]) ?: $b['leads'] <=> $a['leads']);

            return $rows[0]['dim'];
        };

        $notes = [];
        $notes[$pick('conversion_rate', true)][] = __('reports.lead_sources.export.note_best_conversion');
        if (max(array_column($rows, 'revenue')) > 0) {
            $notes[$pick('revenue', true)][] = __('reports.lead_sources.export.note_best_revenue');
        }
        $notes[$pick('conversion_rate', false)][] = __('reports.lead_sources.export.note_worst_conversion');

        return array_map(fn ($items) => implode('; ', $items), $notes);
    }

    protected function sorted(array $rows): array
    {
        usort($rows, fn ($a, $b) => $b['leads'] <=> $a['leads'] ?: strcmp($a['dim'], $b['dim']));

        return $rows;
    }

    /* ── Danh mục cho bộ lọc ──────────────────────────────────────────── */

    #[Computed]
    public function branches(): Collection
    {
        return Branch::query()->orderBy('branch_id')->get(['branch_id', 'branch_name']);
    }

    /**
     * Toàn bộ nguồn (không theo bộ lọc) - cho ô chọn nguồn khi xuất Excel.
     */
    #[Computed]
    public function allSources(): array
    {
        return Lead::SOURCES;
    }

    /* ── Hỗ trợ ───────────────────────────────────────────────────────── */

    protected function report(): LeadSourceReport
    {
        return app(LeadSourceReport::class);
    }

    /**
     * Bộ lọc đã kiểm tra (giá trị không hợp lệ bị bỏ qua).
     */
    protected function filterValues(): array
    {
        return [
            'from'   => $this->validDate($this->from) ? $this->from : null,
            'to'     => $this->validDate($this->to) ? $this->to : null,
            'branch' => $this->branches->contains('branch_id', $this->branch) ? $this->branch : null,
            'method' => in_array($this->method, Lead::CONTACT_METHODS, true) ? $this->method : null,
        ];
    }

    protected function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }

    /**
     * Nhãn hiển thị của 1 giá trị theo chiều phân tích.
     */
    public function label(string $dimension, string $value): string
    {
        return match ($dimension) {
            'method' => __('leads.method.'.$value),
            'status' => __('leads.status_name.'.$value),
            'branch' => $this->branches->firstWhere('branch_id', $value)?->branch_name ?? $value,
            'month'  => preg_match('/^(\d{4})-(\d{2})$/', $value, $m) ? "$m[2]/$m[1]" : $value,
            default  => $value,
        };
    }

    public function render()
    {
        return view('livewire.reports.lead-sources')->title(__('reports.lead_sources.title'));
    }
}
