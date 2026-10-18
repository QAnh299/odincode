@php
    use App\Livewire\Reports\SalesPerformance;

    $t = $this->totals;
    $p = $this->previousTotals;
    $rows = $this->byEmployee;
    $teamRows = $this->byTeam;
    $stages = $this->stages;
    $charts = $this->charts;
    $sortKey = $this->activeSort();
    $monthText = $this->monthLabel($this->activeMonth);
    $prevText = $this->monthLabel($this->previousMonth);

    $money = fn ($v) => number_format((float) $v, 0, ',', '.').' ₫';
    $num = fn ($v) => number_format((float) $v, 0, ',', '.');
    $dec = fn ($v) => number_format((float) $v, 1, ',', '.');
    $pct = fn ($v) => $v === null ? '—' : $dec($v).'%';
    $hours = fn ($v) => $v === null ? '—' : __('reports.sales_performance.hours', ['value' => $dec($v)]);
    $days = fn ($v) => $v === null ? '—' : __('reports.sales_performance.days', ['value' => $dec($v)]);
    $formats = [
        'assigned' => $num, 'won' => $num, 'contacts' => $num, 'unreachable' => $num, 'backward' => $num,
        'revenue' => $money, 'paid' => $money,
        'processed_rate' => $pct, 'moved_rate' => $pct, 'close_rate' => $pct, 'contact_success_rate' => $pct,
        'avg_response_hours' => $hours, 'avg_close_days' => $days,
    ];

    // So với tháng trước: [chữ, class]; $dir = 1 cao là tốt, -1 thấp là tốt; $abs = so chênh lệch tuyệt đối (giờ)
    $delta = function ($cur, $prev, int $dir = 1, bool $abs = false) use ($dec, $prevText) {
        if ($cur === null || $prev === null) {
            return null;
        }
        if ($abs) {
            $change = $cur - $prev;
            $text = __('reports.sales_performance.hours', ['value' => $dec(abs($change))]);
        } else {
            if ((float) $prev == 0) {
                return $cur > 0 ? [__('reports.sales_performance.delta_new', ['month' => $prevText]), 'up'] : null;
            }
            $change = ($cur - $prev) * 100 / $prev;
            $text = $dec(abs($change)).'%';
        }
        if (abs($change) < 0.05) {
            return [__('reports.sales_performance.delta_same', ['month' => $prevText]), ''];
        }

        return [($change > 0 ? '▲ ' : '▼ ').$text.' '.__('reports.sales_performance.delta_vs', ['month' => $prevText]),
            $change * $dir > 0 ? 'up' : 'down'];
    };

    $revenueTarget = array_sum(array_column($this->targets, 'revenue'));
    $kpis = [
        ['assigned', 'layers', $num($t['assigned']),
            __('reports.sales_performance.kpi.assigned_hint', ['count' => $num($t['processed']), 'rate' => $pct($t['processed_rate'])]),
            $delta($t['assigned'], $p['assigned'])],
        ['won', 'check-circle', $num($t['won']),
            __('reports.sales_performance.kpi.won_hint', ['rate' => $pct($t['close_rate'])]),
            $delta($t['won'], $p['won'])],
        ['revenue', 'cash', $money($t['revenue']),
            $revenueTarget
                ? __('reports.sales_performance.kpi.revenue_target_hint', ['rate' => $pct(round($t['revenue'] * 100 / $revenueTarget, 1)), 'target' => $money($revenueTarget)])
                : __('reports.sales_performance.kpi.revenue_hint', ['paid' => $money($t['paid'])]),
            $delta($t['revenue'], $p['revenue'])],
        ['response', 'clock', $hours($t['avg_response_hours']),
            __('reports.sales_performance.kpi.response_hint', ['days' => $days($t['avg_close_days'])]),
            $delta($t['avg_response_hours'], $p['avg_response_hours'], -1, true)],
        ['contacts', 'phone', $num($t['contacts']),
            __('reports.sales_performance.kpi.contacts_hint', ['unreachable' => $num($t['unreachable']), 'backward' => $num($t['backward'])]),
            $delta($t['contacts'], $p['contacts'])],
    ];

    $hasData = $t['assigned'] > 0 || $t['won'] + $t['lost'] > 0 || $t['revenue'] > 0;
@endphp

<div class="vc rp">
    {{-- Dùng lại giao diện chung .vc-* (voucher.css) + phần riêng báo cáo (report.css); Chart.js nạp ở charts.js --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/lead.css', 'resources/css/report.css', 'resources/js/charts.js'])
    @endassets

    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ __('reports.sales_performance.title') }} · {{ $monthText }}</h1>
            <p class="vc-head__sub">{{ __('reports.sales_performance.subtitle') }}</p>
        </div>
        <div class="rp-head__actions">
            <span class="vc-results__loading" wire:loading.delay wire:target.except="export">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('reports.loading') }}
            </span>
            {{-- Xuất báo cáo tổng hợp tháng đang xem dạng văn bản (Giám đốc, Sale Admin) --}}
            @if ($this->canExport)
                <button type="button" class="vc-btn vc-btn--primary" wire:click="openExport">
                    <x-icon name="file" />
                    {{ __('reports.sales_performance.export.button') }}
                </button>
            @endif
        </div>
    </header>

    {{-- ── Bộ lọc ──────────────────────────────────────────────────────── --}}
    <section class="vc-card rp-filters" aria-label="{{ __('reports.lead_sources.filters') }}">
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.sales_performance.month') }}</span>
            <select wire:model.live="month" class="vc-control">
                @foreach ($this->months as $option)
                    <option value="{{ $option }}" @selected($option === $this->activeMonth)>{{ $this->monthLabel($option) }}</option>
                @endforeach
            </select>
        </label>
        @if ($this->canFilterTeam)
            <label class="vc-field">
                <span class="vc-field__label">{{ __('reports.sales_performance.team') }}</span>
                <select wire:model.live="team" class="vc-control">
                    <option value="">{{ __('reports.sales_performance.all_teams') }}</option>
                    @foreach ($this->teams as $option)
                        <option value="{{ $option->team_id }}">{{ $option->team_name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.sales_performance.employee') }}</span>
            <select wire:model.live="employee" class="vc-control">
                <option value="">{{ __('reports.all') }}</option>
                @foreach ($this->employeeOptions as $option)
                    <option value="{{ $option->employee_id }}">
                        {{ $option->full_name }}{{ $option->isActive() ? '' : ' ('.__('reports.sales_performance.resigned').')' }}
                    </option>
                @endforeach
            </select>
        </label>
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.sales_performance.sort') }}</span>
            <select wire:model.live="sort" class="vc-control">
                @foreach (array_keys(SalesPerformance::SORTS) as $key)
                    <option value="{{ $key }}">{{ __('reports.sales_performance.metric.'.$key) }}</option>
                @endforeach
            </select>
        </label>
        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('reports.reset') }}
            </button>
        </div>
    </section>

    {{-- ── Thẻ KPI (so với tháng trước) ────────────────────────────────── --}}
    <div class="rp-kpis">
        @foreach ($kpis as [$key, $icon, $value, $hint, $change])
            <div class="vc-summary__item rp-kpi">
                <span class="vc-summary__icon"><x-icon :name="$icon" /></span>
                <span class="vc-summary__text">
                    <span class="vc-summary__label">{{ __('reports.sales_performance.kpi.'.$key) }}</span>
                    <span class="vc-summary__value">{{ $value }}</span>
                    <span class="rp-kpi__hint" title="{{ $hint }}">{{ $hint }}</span>
                    @if ($change)
                        <span class="rp-delta {{ $change[1] ? 'rp-delta--'.$change[1] : '' }}">{{ $change[0] }}</span>
                    @endif
                </span>
            </div>
        @endforeach
    </div>

    @if (! $hasData)
        <section class="vc-card rp-empty">{{ __('reports.sales_performance.no_data', ['month' => $monthText]) }}</section>
    @else
        {{-- ══ HIỆU QUẢ TEAM ══════════════════════════════════════════════ --}}
        @if ($teamRows)
            <h2 class="rp-section">{{ __('reports.sales_performance.team_section') }}</h2>
            <p class="rp-section__sub">{{ __('reports.sales_performance.team_section_sub') }}</p>

            <section class="vc-card rp-card">
                <div class="table-responsive">
                    <table class="rp-table">
                        <thead>
                            <tr>
                                <th>{{ __('reports.sales_performance.rank') }}</th>
                                <th>{{ __('reports.sales_performance.team') }}</th>
                                <th class="rp-num">{{ __('reports.sales_performance.members') }}</th>
                                @foreach (\App\Services\SalesPerformanceReport::TARGETS as $metric)
                                    <th class="rp-num">{{ __('reports.sales_performance.target.'.$metric) }}</th>
                                    <th class="rp-num">{{ __('reports.sales_performance.attain') }}</th>
                                @endforeach
                                <th class="rp-num">{{ __('reports.sales_performance.metric.won') }}</th>
                                <th class="rp-num">{{ __('reports.sales_performance.metric.close_rate') }}</th>
                                <th class="rp-num">{{ __('reports.sales_performance.metric.processed_rate') }}</th>
                                <th class="rp-num">{{ __('reports.sales_performance.metric.avg_response_hours') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($teamRows as $row)
                                <tr wire:key="team-{{ $row['id'] }}">
                                    <td><span class="rp-rank rp-rank--{{ $row['rank'] }}">{{ $row['rank'] }}</span></td>
                                    <th scope="row">{{ $row['name'] }}</th>
                                    <td class="rp-num">{{ $num($row['members']) }}</td>
                                    @foreach (\App\Services\SalesPerformanceReport::TARGETS as $metric)
                                        <td class="rp-num">
                                            {{ $metric === 'revenue' ? $money($row[$metric]) : $num($row[$metric]) }}
                                            <span class="rp-sub">
                                                @if (isset($row['target'][$metric]))
                                                    / {{ $metric === 'revenue' ? $money($row['target'][$metric]) : $num($row['target'][$metric]) }}
                                                @else
                                                    {{ __('reports.sales_performance.no_target') }}
                                                @endif
                                            </span>
                                        </td>
                                        <td class="rp-num">
                                            @if ($row['attain'][$metric] !== null)
                                                <span class="rp-bar {{ $row['attain'][$metric] >= 100 ? 'rp-bar--done' : '' }}"
                                                    style="--w: {{ min(100, $row['attain'][$metric]) }}%">{{ $pct($row['attain'][$metric]) }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="rp-num">{{ $num($row['won']) }}</td>
                                    <td class="rp-num">{{ $pct($row['close_rate']) }}</td>
                                    <td class="rp-num">{{ $pct($row['processed_rate']) }}</td>
                                    <td class="rp-num">{{ $hours($row['avg_response_hours']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- ══ HIỆU QUẢ SALESPERSON ═══════════════════════════════════════ --}}
        <h2 class="rp-section">{{ __('reports.sales_performance.employee_section') }}</h2>
        <p class="rp-section__sub">
            {{ __('reports.sales_performance.employee_section_sub', ['metric' => mb_strtolower(__('reports.sales_performance.metric.'.$sortKey))]) }}
            <span class="rp-legend"><span class="rp-cmp--up">●</span> {{ __('reports.sales_performance.legend_up') }}</span>
            <span class="rp-legend"><span class="rp-cmp--down">●</span> {{ __('reports.sales_performance.legend_down') }}</span>
        </p>

        <section class="vc-card rp-card">
            <div class="table-responsive">
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.sales_performance.rank') }}</th>
                            <th>{{ __('reports.sales_performance.employee') }}</th>
                            @foreach (array_keys(SalesPerformance::COLUMNS) as $key)
                                <th class="rp-num {{ $key === $sortKey ? 'is-sorted' : '' }}">
                                    @if (isset(SalesPerformance::SORTS[$key]))
                                        <button type="button" class="rp-sort" wire:click="$set('sort', '{{ $key }}')">
                                            {{ __('reports.sales_performance.metric.'.$key) }}{{ $key === $sortKey ? (SalesPerformance::SORTS[$key] === 'desc' ? ' ↓' : ' ↑') : '' }}
                                        </button>
                                    @else
                                        {{ __('reports.sales_performance.metric.'.$key) }}
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr wire:key="emp-{{ $row['id'] }}" class="{{ $row['active'] ? '' : 'rp-row--idle' }}">
                                <td>
                                    @if ($row['rank'])
                                        <span class="rp-rank rp-rank--{{ $row['rank'] }}">{{ $row['rank'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <th scope="row">
                                    {{ $row['name'] }}
                                    @if ($row['team'])
                                        <span class="rp-sub">{{ $row['team'] }}</span>
                                    @endif
                                </th>
                                @foreach (array_keys(SalesPerformance::COLUMNS) as $key)
                                    <td class="rp-num {{ $row['compare'][$key] ? 'rp-cmp--'.$row['compare'][$key] : '' }} {{ $key === $sortKey ? 'is-sorted' : '' }}">
                                        {{ $formats[$key]($row[$key]) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if (count($rows) > 1)
                        <tfoot>
                            <tr>
                                <td></td>
                                <th scope="row">{{ __('reports.sales_performance.total') }}</th>
                                @foreach (array_keys(SalesPerformance::COLUMNS) as $key)
                                    <td class="rp-num">{{ $formats[$key]($t[$key]) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>

        {{-- Tỷ lệ chuyển đổi từng Stage theo salesperson (Lead được giao trong tháng) --}}
        <section class="vc-card rp-card">
            <h3 class="rp-card__title">{{ __('reports.sales_performance.stage_table.title') }}</h3>
            <p class="rp-card__sub">{{ __('reports.sales_performance.stage_table.sub') }}</p>
            <div class="table-responsive">
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.sales_performance.employee') }}</th>
                            @foreach ($stages as $stage)
                                <th class="rp-num">{{ $stage['name'] }}</th>
                            @endforeach
                            <th class="rp-num">{{ __('reports.sales_performance.metric.moved_rate') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @continue(! $row['assigned'])
                            <tr wire:key="stg-{{ $row['id'] }}">
                                <th scope="row">{{ $row['name'] }}</th>
                                @foreach ($stages as $stage)
                                    @php $cell = $row['stages'][$stage['order']]; @endphp
                                    <td class="rp-num">
                                        {{ $num($cell['reached']) }}
                                        @if ($cell['rate'] !== null)
                                            <span class="rp-sub">{{ $pct($cell['rate']) }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="rp-num">{{ $pct($row['moved_rate']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">{{ __('reports.sales_performance.total') }}</th>
                            @foreach ($stages as $stage)
                                @php $cell = $t['stages'][$stage['order']]; @endphp
                                <td class="rp-num">
                                    {{ $num($cell['reached']) }}
                                    @if ($cell['rate'] !== null)
                                        <span class="rp-sub">{{ $pct($cell['rate']) }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="rp-num">{{ $pct($t['moved_rate']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        {{-- ══ BIỂU ĐỒ ═══════════════════════════════════════════════════ --}}
        <h2 class="rp-section">{{ __('reports.sales_performance.chart_section') }}</h2>
        <p class="rp-section__sub">{{ __('reports.sales_performance.chart_section_sub') }}</p>

        <div class="rp-grid">
            @foreach (['team', 'funnel', 'pipeline', 'revenue', 'rates', 'quality', 'trend_count', 'trend_revenue'] as $key)
                @continue(! isset($charts[$key]))
                @php
                    $height = $key === 'funnel' ? max(200, count($stages) * 48 + 40) : 300;
                @endphp
                <section class="vc-card rp-card">
                    <h3 class="rp-card__title">{{ __('reports.sales_performance.chart.'.$key) }}</h3>
                    <p class="rp-card__sub">{{ __('reports.sales_performance.chart.'.$key.'_sub') }}</p>
                    <div class="rp-chart" style="height: {{ $height }}px"
                        wire:key="sp-{{ $key }}-{{ md5(json_encode($charts[$key])) }}" wire:ignore
                        x-data="odinChart(@js($charts[$key]))">
                        <canvas x-ref="canvas" role="img" aria-label="{{ __('reports.sales_performance.chart.'.$key) }}"></canvas>
                    </div>
                    @if ($key === 'funnel')
                        <ol class="rp-steps">
                            @foreach (array_slice($stages, 1) as $i => $stage)
                                <li>
                                    <span>{{ $stages[$i]['name'] }} → {{ $stage['name'] }}</span>
                                    <strong>{{ $pct($t['stages'][$stage['order']]['rate']) }}</strong>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            @endforeach
        </div>
    @endif

    {{-- Cách tính --}}
    <details class="vc-card rp-card rp-method">
        <summary>{{ __('reports.sales_performance.method.title') }}</summary>
        <ul>
            @foreach (['assigned', 'process', 'outcome', 'revenue', 'target', 'compare'] as $key)
                <li>{{ __('reports.sales_performance.method.'.$key) }}</li>
            @endforeach
        </ul>
    </details>

    {{-- Popup chọn thời gian xuất Excel --}}
    @if ($this->canExport)
        @include('livewire.reports.partials.export-period')
    @endif
</div>
