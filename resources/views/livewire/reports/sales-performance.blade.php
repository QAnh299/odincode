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

    // Thẻ KPI: 1 số chính + 1 dòng giải thích ngắn + so với tháng trước
    $revenueTarget = array_sum(array_column($this->targets, 'revenue'));
    $kpis = [
        ['assigned', 'layers', $num($t['assigned']),
            __('reports.sales_performance.kpi.assigned_hint', ['rate' => $pct($t['processed_rate'])]),
            $delta($t['assigned'], $p['assigned'])],
        ['won', 'check-circle', $num($t['won']),
            __('reports.sales_performance.kpi.won_hint', ['rate' => $pct($t['close_rate'])]),
            $delta($t['won'], $p['won'])],
        ['revenue', 'cash', $money($t['revenue']),
            $revenueTarget
                ? __('reports.sales_performance.kpi.revenue_target_hint', ['rate' => $pct(round($t['revenue'] * 100 / $revenueTarget, 1))])
                : __('reports.sales_performance.kpi.revenue_hint'),
            $delta($t['revenue'], $p['revenue'])],
        ['response', 'clock', $hours($t['avg_response_hours']),
            __('reports.sales_performance.kpi.response_hint'),
            $delta($t['avg_response_hours'], $p['avg_response_hours'], -1, true)],
        ['contacts', 'phone', $num($t['contacts']),
            __('reports.sales_performance.kpi.contacts_hint', ['rate' => $pct($t['contact_success_rate'])]),
            $delta($t['contacts'], $p['contacts'])],
    ];

    // Bảng chỉ hiện top N dòng đã xếp hạng; chọn "Tất cả" thì bảng có thanh cuộn
    $teamShown = $this->topRows($teamRows, $this->teamTop);
    // Bảng team: chỉ tiêu doanh thu + Lead giao mới
    $teamGoals = ['revenue', 'assigned'];
    // Bảng salesperson lọc theo đội chọn ở đầu bảng (khác bộ lọc chung); tổng cộng theo đội đó
    $empRows = $this->employeeView;
    $empTotals = $this->employeeViewTotals;
    $rowsShown = $this->topRows($empRows, $this->employeeTop);
    $chartKeys = array_values(array_filter(['team', 'trend_revenue', 'funnel'], fn ($k) => isset($charts[$k])));

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
        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('reports.reset') }}
            </button>
        </div>
    </section>

    {{-- ── Thẻ KPI (so với tháng trước) ────────────────────────────────── --}}
    <div class="rp-kpis rp-kpis--fit" style="--n: {{ count($kpis) }}">
        @foreach ($kpis as [$key, $icon, $value, $hint, $change])
            <div class="rp-stat">
                <div class="rp-stat__head">
                    <span class="rp-stat__icon"><x-icon :name="$icon" /></span>
                    <span>{{ __('reports.sales_performance.kpi.'.$key) }}</span>
                </div>
                <div class="rp-stat__value {{ $key === 'revenue' ? 'rp-stat__value--fit' : '' }}">{{ $value }}</div>
                @if ($change)
                    <div class="rp-delta {{ $change[1] ? 'rp-delta--'.$change[1] : '' }}">{{ $change[0] }}</div>
                @endif
                <div class="rp-stat__hint">{{ $hint }}</div>
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
                @include('livewire.reports.partials.sp-top', ['model' => 'teamTop', 'shown' => count($teamShown), 'total' => count($teamRows)])
                <div class="rp-scroll">
                    <table class="rp-table">
                        <thead>
                            <tr>
                                <th class="rp-pin rp-pin--rank">{{ __('reports.sales_performance.rank') }}</th>
                                <th class="rp-pin rp-pin--2">{{ __('reports.sales_performance.team') }}</th>
                                @foreach ($teamGoals as $metric)
                                    <th>{{ __('reports.sales_performance.target.'.$metric) }}</th>
                                @endforeach
                                <th class="rp-num">{{ __('reports.sales_performance.metric.close_rate') }}</th>
                                <th class="rp-num">{{ __('reports.sales_performance.metric.processed_rate') }}</th>
                                <th class="rp-num">
                                    {{ __('reports.sales_performance.team_close_days') }}
                                    <span class="rp-sub">{{ __('reports.sales_performance.team_close_days_sub') }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($teamShown as $row)
                                <tr wire:key="team-{{ $row['id'] }}">
                                    <td class="rp-pin rp-pin--rank"><span class="rp-rank rp-rank--{{ $row['rank'] }}">{{ $row['rank'] }}</span></td>
                                    <th scope="row" class="rp-pin rp-pin--2">
                                        {{ $row['name'] }}
                                        <span class="rp-sub">{{ __('reports.sales_performance.members_count', ['count' => $row['members']]) }}</span>
                                    </th>
                                    {{-- Mỗi chỉ tiêu 1 ô: thực tế + % đạt, thanh tiến độ, chỉ tiêu --}}
                                    @foreach ($teamGoals as $metric)
                                        @php
                                            $fmt = $metric === 'revenue' ? $money : $num;
                                            $rate = $row['attain'][$metric];
                                        @endphp
                                        <td>
                                            <div class="rp-goal {{ $rate !== null && $rate >= 100 ? 'is-done' : '' }}">
                                                <div class="rp-goal__top">
                                                    <strong>{{ $fmt($row[$metric]) }}</strong>
                                                    @if ($rate !== null)
                                                        <span class="rp-goal__pct">{{ $pct($rate) }}</span>
                                                    @endif
                                                </div>
                                                @if ($rate !== null)
                                                    <div class="rp-goal__bar"><span style="width: {{ min(100, $rate) }}%"></span></div>
                                                    <span class="rp-sub">{{ __('reports.sales_performance.of_target', ['target' => $fmt($row['target'][$metric])]) }}</span>
                                                @else
                                                    <span class="rp-sub">{{ __('reports.sales_performance.no_target') }}</span>
                                                @endif
                                            </div>
                                        </td>
                                    @endforeach
                                    <td class="rp-num">{{ $pct($row['close_rate']) }}</td>
                                    <td class="rp-num">{{ $pct($row['processed_rate']) }}</td>
                                    <td class="rp-num">{{ $days($row['avg_close_days']) }}</td>
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
            @include('livewire.reports.partials.sp-top', ['model' => 'employeeTop', 'shown' => count($rowsShown), 'total' => count($empRows),
                'teamModel' => 'employeeTeam', 'teamOptions' => $this->employeeTeamOptions])
            <div class="rp-scroll">
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th class="rp-pin rp-pin--rank">{{ __('reports.sales_performance.rank') }}</th>
                            <th class="rp-pin rp-pin--2">{{ __('reports.sales_performance.employee') }}</th>
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
                        @foreach ($rowsShown as $row)
                            <tr wire:key="emp-{{ $row['id'] }}" class="{{ $row['active'] ? '' : 'rp-row--idle' }}">
                                <td class="rp-pin rp-pin--rank">
                                    @if ($row['rank'])
                                        <span class="rp-rank rp-rank--{{ $row['rank'] }}">{{ $row['rank'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <th scope="row" class="rp-pin rp-pin--2">
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
                    @if (count($empRows) > 1)
                        <tfoot>
                            <tr>
                                <td class="rp-pin rp-pin--rank"></td>
                                <th scope="row" class="rp-pin rp-pin--2">{{ __('reports.sales_performance.total') }}</th>
                                @foreach (array_keys(SalesPerformance::COLUMNS) as $key)
                                    <td class="rp-num">{{ $formats[$key]($empTotals[$key]) }}</td>
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
            <div class="rp-scroll">
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th class="rp-pin">{{ __('reports.sales_performance.employee') }}</th>
                            @foreach ($stages as $stage)
                                <th class="rp-num">{{ $stage['name'] }}</th>
                            @endforeach
                            <th class="rp-num">{{ __('reports.sales_performance.metric.moved_rate') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rowsShown as $row)
                            @continue(! $row['assigned'])
                            <tr wire:key="stg-{{ $row['id'] }}">
                                <th scope="row" class="rp-pin">{{ $row['name'] }}</th>
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
                            <th scope="row" class="rp-pin">{{ __('reports.sales_performance.total') }}</th>
                            @foreach ($stages as $stage)
                                @php $cell = $empTotals['stages'][$stage['order']]; @endphp
                                <td class="rp-num">
                                    {{ $num($cell['reached']) }}
                                    @if ($cell['rate'] !== null)
                                        <span class="rp-sub">{{ $pct($cell['rate']) }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="rp-num">{{ $pct($empTotals['moved_rate']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        {{-- ══ BIỂU ĐỒ ═══════════════════════════════════════════════════ --}}
        <h2 class="rp-section">{{ __('reports.sales_performance.chart_section') }}</h2>
        <p class="rp-section__sub">{{ __('reports.sales_performance.chart_section_sub') }}</p>

        <div class="rp-grid">
            @foreach ($chartKeys as $i => $key)
                @php
                    $height = $key === 'funnel' ? max(200, count($stages) * 48 + 40) : 300;
                    // Số biểu đồ lẻ: biểu đồ cuối chiếm cả hàng
                    $wide = $i === count($chartKeys) - 1 && count($chartKeys) % 2 === 1;
                @endphp
                <section class="vc-card rp-card {{ $wide ? 'rp-card--wide' : '' }}">
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
