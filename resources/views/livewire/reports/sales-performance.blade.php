@php
    $t = $this->totals;
    $rows = $this->byEmployee;
    $teamRows = $this->byTeam;
    $stages = $this->stages;
    $charts = $this->charts;
    $money = fn ($v) => number_format((float) $v, 0, ',', '.').' ₫';
    $num = fn ($v) => number_format((float) $v, 0, ',', '.');
    $dec = fn ($v) => number_format((float) $v, 1, ',', '.');
    $pct = fn ($v) => $v === null ? '—' : $dec($v).'%';
    $hours = fn ($v) => $v === null ? '—' : __('reports.sales_performance.hours', ['value' => $dec($v)]);
    $days = fn ($v) => $v === null ? '—' : __('reports.sales_performance.days', ['value' => $dec($v)]);

    $kpis = [
        ['assigned', 'layers', $num($t['assigned']),
            __('reports.sales_performance.kpi.assigned_hint', ['count' => $num($t['processed']), 'rate' => $pct($t['processed_rate'])])],
        ['won', 'check-circle', $num($t['won']),
            __('reports.sales_performance.kpi.won_hint', ['rate' => $pct($t['win_rate'])])],
        ['revenue', 'cash', $money($t['revenue']),
            __('reports.sales_performance.kpi.revenue_hint', ['paid' => $money($t['paid'])])],
        ['response', 'clock', $hours($t['avg_response_hours']),
            __('reports.sales_performance.kpi.response_hint', ['days' => $days($t['avg_close_days'])])],
        ['contacts', 'phone', $num($t['contacts']),
            __('reports.sales_performance.kpi.contacts_hint', ['unreachable' => $num($t['unreachable']), 'backward' => $num($t['backward'])])],
    ];

    // Cột của bảng xếp hạng: key → định dạng
    $columns = [
        'assigned'           => $num,
        'processed'          => $num,
        'moved'              => $num,
        'won'                => $num,
        'win_rate'           => null,
        'revenue'            => $money,
        'paid'               => $money,
        'avg_response_hours' => $hours,
        'avg_close_days'     => $days,
        'contacts'           => $num,
        'unreachable'        => $num,
        'backward'           => $num,
    ];
    $hasData = $t['assigned'] > 0;
@endphp

<div class="vc rp">
    {{-- Dùng lại giao diện chung .vc-* (voucher.css) + phần riêng báo cáo (report.css); Chart.js nạp ở charts.js --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/report.css', 'resources/js/charts.js'])
    @endassets

    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ __('reports.sales_performance.title') }}</h1>
            <p class="vc-head__sub">{{ __('reports.sales_performance.subtitle') }}</p>
        </div>
        <span class="vc-results__loading" wire:loading.delay>
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            {{ __('reports.loading') }}
        </span>
    </header>

    {{-- ── Bộ lọc ──────────────────────────────────────────────────────── --}}
    <section class="vc-card rp-filters" aria-label="{{ __('reports.lead_sources.filters') }}">
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.sales_performance.from') }}</span>
            <input type="date" wire:model.live="from" class="vc-control" max="{{ $to ?: '' }}">
        </label>
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.sales_performance.to') }}</span>
            <input type="date" wire:model.live="to" class="vc-control" min="{{ $from ?: '' }}">
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

    <div class="rp-kpis">
        @foreach ($kpis as [$key, $icon, $value, $hint])
            <div class="vc-summary__item rp-kpi">
                <span class="vc-summary__icon"><x-icon :name="$icon" /></span>
                <span class="vc-summary__text">
                    <span class="vc-summary__label">{{ __('reports.sales_performance.kpi.'.$key) }}</span>
                    <span class="vc-summary__value">{{ $value }}</span>
                    <span class="rp-kpi__hint" title="{{ $hint }}">{{ $hint }}</span>
                </span>
            </div>
        @endforeach
    </div>

    @if (! $hasData)
        <section class="vc-card rp-empty">{{ __('reports.no_data') }}</section>
    @else
        {{-- ══ BIỂU ĐỒ ═══════════════════════════════════════════════════ --}}
        <div class="rp-grid">
            @foreach (['pipeline', 'rates', 'revenue', 'contacts'] as $key)
                <section class="vc-card rp-card">
                    <h3 class="rp-card__title">{{ __('reports.sales_performance.chart.'.$key) }}</h3>
                    <p class="rp-card__sub">{{ __('reports.sales_performance.chart.'.$key.'_sub') }}</p>
                    <div class="rp-chart" style="height: 300px"
                        wire:key="sp-{{ $key }}-{{ md5(json_encode($charts[$key])) }}" wire:ignore
                        x-data="odinChart(@js($charts[$key]))">
                        <canvas x-ref="canvas" role="img" aria-label="{{ __('reports.sales_performance.chart.'.$key) }}"></canvas>
                    </div>
                </section>
            @endforeach

            {{-- Phễu chuyển đổi theo stage --}}
            <section class="vc-card rp-card">
                <h3 class="rp-card__title">{{ __('reports.sales_performance.chart.funnel') }}</h3>
                <p class="rp-card__sub">{{ __('reports.sales_performance.chart.funnel_sub') }}</p>
                <div class="rp-chart" style="height: {{ max(200, count($stages) * 48 + 40) }}px"
                    wire:key="sp-funnel-{{ md5(json_encode($charts['funnel'])) }}" wire:ignore
                    x-data="odinChart(@js($charts['funnel']))">
                    <canvas x-ref="canvas" role="img" aria-label="{{ __('reports.sales_performance.chart.funnel') }}"></canvas>
                </div>
                <ol class="rp-steps">
                    @foreach (array_slice($stages, 1) as $i => $stage)
                        <li>
                            <span>{{ $stages[$i]['name'] }} → {{ $stage['name'] }}</span>
                            <strong>{{ $pct($t['stages'][$stage['order']]['rate']) }}</strong>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- Xu hướng theo tháng --}}
            <section class="vc-card rp-card">
                <h3 class="rp-card__title">{{ __('reports.sales_performance.chart.trend') }}</h3>
                <p class="rp-card__sub">{{ __('reports.sales_performance.chart.trend_sub') }}</p>
                <div class="rp-chart" style="height: 300px"
                    wire:key="sp-trend-{{ md5(json_encode($charts['trend'])) }}" wire:ignore
                    x-data="odinChart(@js($charts['trend']))">
                    <canvas x-ref="canvas" role="img" aria-label="{{ __('reports.sales_performance.chart.trend') }}"></canvas>
                </div>
            </section>
        </div>
    @endif

    {{-- ══ BẢNG XẾP HẠNG SALESPERSON ═══════════════════════════════════ --}}
    @if ($rows)
        <section class="vc-card rp-card">
            <h3 class="rp-card__title">{{ __('reports.sales_performance.table.title') }}</h3>
            <p class="rp-card__sub">{{ __('reports.sales_performance.table.sub') }}</p>
            <div class="table-responsive">
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('reports.sales_performance.employee') }}</th>
                            @foreach (array_keys($columns) as $key)
                                <th class="rp-num">{{ __('reports.sales_performance.metric.'.$key) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $i => $row)
                            <tr wire:key="emp-{{ $row['dim'] }}">
                                <td>{{ $i + 1 }}</td>
                                <th scope="row">
                                    {{ $row['name'] }}
                                    @if ($row['team'])
                                        <span class="rp-sub">{{ $row['team'] }}</span>
                                    @endif
                                </th>
                                @foreach ($columns as $key => $format)
                                    <td class="rp-num">
                                        @if ($format)
                                            {{ $format($row[$key]) }}
                                        @else
                                            <span class="rp-bar" style="--w: {{ min(100, $row[$key]) }}%">{{ $pct($row[$key]) }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if (count($rows) > 1)
                        <tfoot>
                            <tr>
                                <td></td>
                                <th scope="row">{{ __('reports.sales_performance.table.total') }}</th>
                                @foreach ($columns as $key => $format)
                                    <td class="rp-num">{{ $format ? $format($t[$key]) : $pct($t[$key]) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>

        {{-- ══ TỶ LỆ CHUYỂN ĐỔI TỪNG STAGE THEO SALESPERSON ══════════════ --}}
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
                            <tr wire:key="stg-{{ $row['dim'] }}">
                                <th scope="row">{{ $row['name'] }}</th>
                                @foreach ($stages as $stage)
                                    @php $cell = $row['stages'][$stage['order']]; @endphp
                                    <td class="rp-num">
                                        {{ $num($cell['reached']) }}
                                        @if ($cell['rate'] !== null)
                                            <span class="rp-sub">{{ $pct($row['assigned'] ? $cell['rate'] : null) }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="rp-num">{{ $pct($row['moved_rate']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if (count($rows) > 1)
                        <tfoot>
                            <tr>
                                <th scope="row">{{ __('reports.sales_performance.table.total') }}</th>
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
                    @endif
                </table>
            </div>
        </section>
    @endif

    {{-- ══ SO SÁNH ĐỘI (Giám đốc, Sale Admin – khi xem tất cả đội) ═════ --}}
    @if ($teamRows)
        <section class="vc-card rp-card">
            <h3 class="rp-card__title">{{ __('reports.sales_performance.team_table.title') }}</h3>
            <p class="rp-card__sub">{{ __('reports.sales_performance.team_table.sub') }}</p>
            <div class="table-responsive">
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.sales_performance.team') }}</th>
                            @foreach (array_keys($columns) as $key)
                                <th class="rp-num">{{ __('reports.sales_performance.metric.'.$key) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teamRows as $row)
                            <tr wire:key="team-{{ $row['dim'] ?: 'none' }}">
                                <th scope="row">{{ $row['name'] }}</th>
                                @foreach ($columns as $key => $format)
                                    <td class="rp-num">{{ $format ? $format($row[$key]) : $pct($row[$key]) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
