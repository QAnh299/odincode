@php
    use App\Livewire\Reports\LeadSources;
    use App\Models\Lead;
    use App\Services\LeadSourceReport;

    $t = $this->totals;
    $rows = $this->bySource;
    $charts = $this->fixedCharts;
    $custom = $this->customChart;
    $money = fn ($v) => number_format((float) $v, 0, ',', '.').' ₫';
    $num = fn ($v) => number_format((float) $v, 0, ',', '.');
    $pct = fn ($v) => number_format((float) $v, 1, ',', '.').'%';
    $fmt = fn ($unit, $v) => match ($unit) { 'money' => $money($v), 'percent' => $pct($v), default => $num($v) };

    // Tiền rút gọn cho thẻ KPI (số đầy đủ để ở title): 1,39 tỷ ₫ / 390,6 triệu ₫
    $short = function ($v) use ($money) {
        $v = (float) $v;
        foreach ([[1e9, 'billion'], [1e6, 'million']] as [$base, $unit]) {
            if (abs($v) >= $base) {
                return number_format($v / $base, 2, ',', '.').' '.__('reports.money_'.$unit).' ₫';
            }
        }

        return $money($v);
    };

    // [key, icon, giá trị hiển thị, giá trị đầy đủ (title), chú thích]
    $kpis = [
        ['leads', 'layers', $num($t['leads']), null,
            __('reports.lead_sources.kpi.leads_hint', ['count' => $num($t['sources'])])],
        ['conversion', 'trending', $pct($t['conversion_rate']), null,
            __('reports.lead_sources.kpi.conversion_hint', ['count' => $num($t['converted'])])],
        ['win', 'check-circle', $pct($t['win_rate']), null,
            __('reports.lead_sources.kpi.win_hint', ['count' => $num($t['won'])])],
        ['revenue', 'cash', $short($t['revenue']), $money($t['revenue']),
            __('reports.lead_sources.kpi.revenue_hint', ['paid' => $short($t['paid'])])],
    ];

    $round = in_array($chartType, LeadSources::ROUND_TYPES, true);
    $isTime = in_array($dimension, LeadSourceReport::TIME_DIMENSIONS, true);
    $pending = $applied && $applied !== [
        'chartType' => $chartType, 'dimension' => $dimension, 'breakdown' => $breakdown,
        'metrics' => array_values($metrics), 'values' => array_values($values), 'sort' => $sort, 'limit' => $limit,
    ];
    $metricsByUnit = collect(LeadSourceReport::METRICS)->keys()->groupBy(fn ($m) => LeadSourceReport::METRICS[$m]);
@endphp

<div class="vc rp">
    {{-- Dùng lại giao diện chung .vc-* (voucher.css) + phần riêng báo cáo (report.css); Chart.js nạp ở charts.js --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/lead.css', 'resources/css/report.css', 'resources/js/charts.js'])
    @endassets

    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ __('reports.lead_sources.title') }}</h1>
            <p class="vc-head__sub">{{ __('reports.lead_sources.subtitle') }}</p>
        </div>
        <div class="rp-head__actions">
            <span class="vc-results__loading" wire:loading.delay wire:target.except="export">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('reports.loading') }}
            </span>
            {{-- Xuất báo cáo tổng hợp dạng văn bản theo bộ lọc (Giám đốc, Sale Admin) --}}
            @if ($this->canExport)
                <button type="button" class="vc-btn vc-btn--primary" wire:click="openExport">
                    <x-icon name="file" />
                    {{ __('reports.lead_sources.export.button') }}
                </button>
            @endif
        </div>
    </header>

    {{-- ── Bộ lọc chung (áp dụng cho cả 2 phần) ─────────────────────── --}}
    <section class="vc-card rp-filters" aria-label="{{ __('reports.lead_sources.filters') }}">
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.from') }}</span>
            <input type="date" wire:model.live="from" class="vc-control" max="{{ $to ?: '' }}">
        </label>
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.to') }}</span>
            <input type="date" wire:model.live="to" class="vc-control" min="{{ $from ?: '' }}">
        </label>
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.branch') }}</span>
            <select wire:model.live="branch" class="vc-control">
                <option value="">{{ __('reports.all_branches') }}</option>
                @foreach ($this->branches as $option)
                    <option value="{{ $option->branch_id }}">{{ $option->branch_name }}</option>
                @endforeach
            </select>
        </label>
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.method') }}</span>
            <select wire:model.live="method" class="vc-control">
                <option value="">{{ __('reports.all') }}</option>
                @foreach (Lead::CONTACT_METHODS as $option)
                    <option value="{{ $option }}">{{ __('leads.method.'.$option) }}</option>
                @endforeach
            </select>
        </label>
        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('reports.reset') }}
            </button>
        </div>
    </section>

    {{-- ══ PHẦN 1: BIỂU ĐỒ CỐ ĐỊNH ═══════════════════════════════════════ --}}
    <h2 class="rp-section">{{ __('reports.lead_sources.fixed') }}</h2>
    <p class="rp-section__sub">{{ __('reports.lead_sources.fixed_sub') }}</p>

    {{-- Thẻ KPI dạng dọc (cùng giao diện các trang báo cáo khác): số nằm riêng 1 dòng, đủ chỗ cho số tiền --}}
    <div class="rp-kpis rp-kpis--fit" style="--n: {{ count($kpis) }}">
        @foreach ($kpis as [$key, $icon, $value, $full, $hint])
            <div class="rp-stat">
                <div class="rp-stat__head">
                    <span class="rp-stat__icon"><x-icon :name="$icon" /></span>
                    <span>{{ __('reports.lead_sources.kpi.'.$key) }}</span>
                </div>
                <div class="rp-stat__value rp-stat__value--fit" @if ($full) title="{{ $full }}" @endif>{{ $value }}</div>
                <div class="rp-stat__hint">{{ $hint }}</div>
            </div>
        @endforeach
    </div>

    @if (! $rows)
        <section class="vc-card rp-empty">{{ __('reports.no_data') }}</section>
    @else
        @php $barHeight = fn ($n, $perRow) => max(200, $n * $perRow + 60); @endphp
        <div class="rp-grid">
            {{-- 1. Số Lead & Lead chốt theo nguồn: nguồn nào nhiều Lead nhưng ít chốt --}}
            <section class="vc-card rp-card">
                <h3 class="rp-card__title">{{ __('reports.lead_sources.chart.quality') }}</h3>
                <p class="rp-card__sub">{{ __('reports.lead_sources.chart.quality_sub') }}</p>
                <div class="rp-chart" style="height: {{ $barHeight(count($rows), 52) }}px"
                    wire:key="fx-quality-{{ md5(json_encode($charts['quality'])) }}" wire:ignore
                    x-data="odinChart(@js($charts['quality']))">
                    <canvas x-ref="canvas" role="img" aria-label="{{ __('reports.lead_sources.chart.quality') }}"></canvas>
                </div>
            </section>

            {{-- 2. Doanh thu theo nguồn --}}
            <section class="vc-card rp-card">
                <h3 class="rp-card__title">{{ __('reports.lead_sources.chart.revenue') }}</h3>
                <p class="rp-card__sub">{{ __('reports.lead_sources.chart.revenue_sub') }}</p>
                @if ($charts['revenue']['labels'])
                    <div class="rp-chart" style="height: {{ $barHeight(count($charts['revenue']['labels']), 40) }}px"
                        wire:key="fx-revenue-{{ md5(json_encode($charts['revenue'])) }}" wire:ignore
                        x-data="odinChart(@js($charts['revenue']))">
                        <canvas x-ref="canvas" role="img" aria-label="{{ __('reports.lead_sources.chart.revenue') }}"></canvas>
                    </div>
                @else
                    <p class="rp-empty rp-empty--inline">{{ __('reports.lead_sources.chart.revenue_none') }}</p>
                @endif
            </section>
        </div>

        {{-- 5. Bảng xếp hạng chất lượng nguồn --}}
        <section class="vc-card rp-card">
            <h3 class="rp-card__title">{{ __('reports.lead_sources.table.title') }}</h3>
            <p class="rp-card__sub">{{ __('reports.lead_sources.table.sub') }}</p>
            <div class="table-responsive">
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.lead_sources.table.source') }}</th>
                            @foreach (['leads', 'conversion_rate', 'won', 'win_rate', 'revenue', 'revenue_per_lead'] as $m)
                                <th class="rp-num">{{ __('reports.metric.'.$m) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr wire:key="src-{{ md5($row['dim']) }}">
                                <th scope="row">{{ $row['dim'] }}</th>
                                <td class="rp-num">{{ $num($row['leads']) }}</td>
                                <td class="rp-num">
                                    <span class="rp-bar" style="--w: {{ min(100, $row['conversion_rate']) }}%">{{ $pct($row['conversion_rate']) }}</span>
                                </td>
                                <td class="rp-num">{{ $num($row['won']) }}</td>
                                <td class="rp-num">
                                    <span class="rp-bar" style="--w: {{ min(100, $row['win_rate']) }}%">{{ $pct($row['win_rate']) }}</span>
                                </td>
                                <td class="rp-num">{{ $money($row['revenue']) }}</td>
                                <td class="rp-num">{{ $money($row['revenue_per_lead']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">{{ __('reports.lead_sources.table.total') }}</th>
                            <td class="rp-num">{{ $num($t['leads']) }}</td>
                            <td class="rp-num">{{ $pct($t['conversion_rate']) }}</td>
                            <td class="rp-num">{{ $num($t['won']) }}</td>
                            <td class="rp-num">{{ $pct($t['win_rate']) }}</td>
                            <td class="rp-num">{{ $money($t['revenue']) }}</td>
                            <td class="rp-num">{{ $money($t['revenue_per_lead']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    @endif

    {{-- ══ PHẦN 2: BIỂU ĐỒ LINH HOẠT ═════════════════════════════════════ --}}
    <h2 class="rp-section">{{ __('reports.builder.title') }}</h2>
    <p class="rp-section__sub">{{ __('reports.builder.sub') }}</p>

    <section class="vc-card rp-builder">
        <form wire:submit="draw" class="rp-builder__form">
            {{-- Bước 1: loại biểu đồ --}}
            <fieldset class="rp-step">
                <legend class="rp-step__title">{{ __('reports.builder.step_type') }}</legend>
                <div class="rp-types" role="radiogroup">
                    @foreach (LeadSources::CHART_TYPES as $type)
                        <label class="rp-type {{ $chartType === $type ? 'is-current' : '' }}">
                            <input type="radio" wire:model.live="chartType" value="{{ $type }}" class="visually-hidden">
                            <x-icon :name="'chart-'.$type" />
                            <span>{{ __('reports.builder.type.'.$type) }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- Bước 2: chiều phân tích, chia nhóm, chỉ số --}}
            <fieldset class="rp-step">
                <legend class="rp-step__title">{{ __('reports.builder.step_data') }}</legend>
                <div class="rp-step__row">
                    <label class="vc-field">
                        <span class="vc-field__label">{{ __('reports.builder.dimension') }}</span>
                        <select wire:model.live="dimension" class="vc-control">
                            @foreach (array_keys(LeadSourceReport::DIMENSIONS) as $d)
                                <option value="{{ $d }}">{{ __('reports.dimension.'.$d) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="vc-field">
                        <span class="vc-field__label">{{ __('reports.builder.breakdown') }}</span>
                        <select wire:model.live="breakdown" class="vc-control" @disabled($round)
                            title="{{ $round ? __('reports.builder.round_note') : '' }}">
                            <option value="">{{ __('reports.builder.no_breakdown') }}</option>
                            @foreach ($this->breakdownOptions() as $d)
                                <option value="{{ $d }}">{{ __('reports.dimension.'.$d) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="vc-field">
                        <span class="vc-field__label">{{ __('reports.builder.sort') }}</span>
                        <select wire:model.live="sort" class="vc-control" @disabled($isTime)>
                            @foreach (LeadSources::SORTS as $s)
                                <option value="{{ $s }}">{{ __('reports.builder.sorts.'.$s) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="vc-field">
                        <span class="vc-field__label">{{ __('reports.builder.limit') }}</span>
                        <select wire:model.live="limit" class="vc-control" @disabled($isTime)>
                            @foreach (LeadSources::LIMITS as $l)
                                <option value="{{ $l }}">{{ $l ? __('reports.builder.limit_top', ['count' => $l]) : __('reports.builder.limit_all') }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="rp-metrics">
                    <span class="vc-field__label">{{ __('reports.builder.metrics') }}</span>
                    <p class="rp-hint">{{ __('reports.builder.metrics_hint') }}</p>
                    @foreach ($metricsByUnit as $unit => $keys)
                        <div class="rp-metrics__group">
                            <span class="rp-metrics__unit">{{ __('reports.unit.'.$unit) }}</span>
                            @foreach ($keys as $m)
                                <label class="rp-chip {{ in_array($m, $metrics, true) ? 'is-on' : '' }}">
                                    <input type="checkbox" wire:model.live="metrics" value="{{ $m }}" class="visually-hidden">
                                    {{ __('reports.metric.'.$m) }}
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </fieldset>

            {{-- Bước 3: giá trị cần vẽ --}}
            <fieldset class="rp-step">
                <legend class="rp-step__title">
                    {{ __('reports.builder.step_values') }} · {{ __('reports.dimension.'.$dimension) }}
                </legend>
                @if ($isTime)
                    <p class="rp-hint">{{ __('reports.builder.values_time') }}</p>
                @else
                    <p class="rp-hint">{{ __('reports.builder.values_hint') }}</p>
                    <div class="rp-values">
                        <button type="button" class="rp-chip {{ $values ? '' : 'is-on' }}" wire:click="selectAllValues">
                            {{ __('reports.builder.values_all') }}
                        </button>
                        @foreach ($this->valueOptions as $option)
                            <label class="rp-chip {{ in_array($option['value'], $values, true) ? 'is-on' : '' }}"
                                wire:key="val-{{ $dimension }}-{{ md5($option['value']) }}">
                                <input type="checkbox" wire:model.live="values" value="{{ $option['value'] }}" class="visually-hidden">
                                {{ $option['label'] }} <span class="rp-chip__count">{{ $option['leads'] }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </fieldset>

            @if ($builderErrors)
                <div class="rp-errors" role="alert">
                    <x-icon name="alert" />
                    <ul>
                        @foreach ($builderErrors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rp-builder__actions">
                @if ($pending)
                    <span class="rp-hint rp-hint--pending">{{ __('reports.builder.pending') }}</span>
                @endif
                <button type="submit" class="vc-btn vc-btn--primary">
                    <span wire:loading.remove wire:target="draw"><x-icon name="trending" /></span>
                    <span wire:loading wire:target="draw" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    {{ __('reports.builder.draw') }}
                </button>
            </div>
        </form>

        {{-- Biểu đồ đã vẽ --}}
        <div class="rp-builder__result">
            @if (! $custom || $custom['empty'])
                <p class="rp-empty rp-empty--inline">{{ __('reports.no_data') }}</p>
            @else
                @php
                    $spec = $custom['spec'];
                    $height = in_array($spec['type'], ['bar_h'], true)
                        ? max(260, count($spec['labels']) * 40 * max(1, count($spec['series'])) / 1.5 + 80)
                        : 360;
                @endphp
                <h3 class="rp-card__title">{{ $custom['title'] }}</h3>
                <div class="rp-chart" style="height: {{ (int) $height }}px"
                    wire:key="custom-{{ md5(json_encode($spec)) }}" wire:ignore
                    x-data="odinChart(@js($spec))">
                    <canvas x-ref="canvas" role="img" aria-label="{{ $custom['title'] }}"></canvas>
                </div>

                {{-- Bảng số liệu của biểu đồ --}}
                <details class="rp-data">
                    <summary>{{ __('reports.show_table') }}</summary>
                    <div class="table-responsive">
                        <table class="rp-table">
                            <thead>
                                <tr>
                                    <th>{{ __('reports.dimension.'.$applied['dimension']) }}</th>
                                    @foreach ($spec['series'] as $s)
                                        <th class="rp-num">{{ $s['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($spec['labels'] as $i => $label)
                                    <tr>
                                        <th scope="row">{{ $label }}</th>
                                        @foreach ($spec['series'] as $s)
                                            <td class="rp-num">{{ $fmt($spec['unit'], $s['data'][$i] ?? 0) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif
        </div>
    </section>

    {{-- Popup chọn thời gian xuất Excel --}}
    @if ($this->canExport)
        @include('livewire.reports.partials.report-export')
    @endif
</div>
