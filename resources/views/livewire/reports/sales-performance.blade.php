@php
    use App\Livewire\Reports\SalesPerformance;
    use App\Services\SalesPerformanceReport;

    $t = $this->totals;
    $p = $this->previousTotals;
    $goals = $this->targetTotals;
    $teamRows = $this->byTeam;
    $charts = $this->charts;
    $sortKey = $this->activeSort();
    $columns = SalesPerformance::columns();
    $monthText = $this->monthLabel($this->activeMonth);
    $prevText = $this->monthLabel($this->previousMonth);
    $sp = fn ($key, $replace = []) => __('reports.sales_performance.'.$key, $replace);

    $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', '.').' ₫';
    $num = fn ($v) => number_format((float) $v, 0, ',', '.');
    $dec = fn ($v) => number_format((float) $v, 1, ',', '.');
    $pct = fn ($v) => $v === null ? '—' : $dec($v).'%';
    $hours = fn ($v) => $v === null ? '—' : $sp('hours', ['value' => $dec($v)]);
    $days = fn ($v) => $v === null ? '—' : $sp('days', ['value' => $dec($v)]);
    $formats = [
        'revenue' => $money, 'avg_order' => $money, 'won' => $num, 'open' => $num, 'activity' => $num,
        'close_rate' => $pct, 'reach_rate' => $pct, 'show_rate' => $pct, 'avg_response_hours' => $hours,
    ];

    // So với tháng trước: [chữ, class]. $mode: pct = % thay đổi, points = điểm %, days = số ngày; $dir = 1 cao là tốt, -1 thấp là tốt
    $delta = function ($cur, $prev, string $mode = 'pct', int $dir = 1) use ($dec, $sp, $prevText) {
        if ($cur === null || $prev === null) {
            return null;
        }
        if ($mode === 'pct') {
            if ((float) $prev == 0) {
                return $cur > 0 ? [$sp('delta_new', ['month' => $prevText]), 'up'] : null;
            }
            $change = ($cur - $prev) * 100 / $prev;
            $text = $dec(abs($change)).'%';
        } else {
            $change = $cur - $prev;
            $text = $mode === 'points' ? $sp('points', ['value' => $dec(abs($change))]) : $sp('days', ['value' => $dec(abs($change))]);
        }
        if (abs($change) < 0.05) {
            return [$sp('delta_same', ['month' => $prevText]), ''];
        }

        return [($change > 0 ? '▲ ' : '▼ ').$text.' '.$sp('delta_vs', ['month' => $prevText]), $change * $dir > 0 ? 'up' : 'down'];
    };
    $attain = fn ($value, $goal) => $goal ? round($value * 100 / $goal, 1) : null;

    // Thẻ KPI: [khoá, icon, số chính, chú thích, so với tháng trước, % đạt chỉ tiêu (thanh tiến độ)]
    $kpis = [
        ['revenue', 'cash', $money($t['revenue']),
            $goals['revenue'] ? $sp('kpi.of_target', ['target' => $money($goals['revenue'])]) : $sp('kpi.revenue_hint', ['count' => $num($t['orders'])]),
            $delta($t['revenue'], $p['revenue']), $attain($t['revenue'], $goals['revenue'])],
        ['students', 'user-check', $num($t['students']),
            $goals['students'] ? $sp('kpi.of_target', ['target' => $num($goals['students'])]) : $sp('kpi.students_hint'),
            $delta($t['students'], $p['students']), $attain($t['students'], $goals['students'])],
        ['close_rate', 'check-circle', $pct($t['close_rate']),
            $sp('kpi.close_rate_hint', ['won' => $num($t['won']), 'lost' => $num($t['lost'])]),
            $delta($t['close_rate'], $p['close_rate'], 'points'), null],
        ['close_days', 'hourglass', $days($t['avg_close_days']),
            $sp('kpi.close_days_hint'),
            $delta($t['avg_close_days'], $p['avg_close_days'], 'days', -1), null],
        ['pipeline', 'layers', $money($t['open_value']),
            $sp('kpi.pipeline_hint', ['count' => $num($t['open'])]),
            null, null],
    ];

    $teamShown = $this->topRows($teamRows, $this->teamTop);
    $empRows = $this->employeeView;
    $empTotals = $this->employeeViewTotals;
    $rowsShown = $this->topRows($empRows, $this->employeeTop);
    $alertRows = $this->alertRows;
    $flaggedTotal = array_sum(array_map(fn ($r) => $r['alerts']['flagged'], $alertRows));

    $hasData = $t['assigned'] > 0 || $t['won'] + $t['lost'] > 0 || $t['revenue'] > 0 || $t['activity'] > 0 || $t['open'] > 0;
@endphp

<div class="vc rp">
    {{-- Dùng lại giao diện chung .vc-* (voucher.css) + phần riêng báo cáo (report.css); Chart.js nạp ở charts.js --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/lead.css', 'resources/css/report.css', 'resources/js/charts.js'])
    @endassets

    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ $sp('title') }} · {{ $monthText }}</h1>
            <p class="vc-head__sub">{{ $sp('subtitle') }}</p>
        </div>
        <div class="rp-head__actions">
            <span class="vc-results__loading" wire:loading.delay wire:target.except="export">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('reports.loading') }}
            </span>
            {{-- Xuất báo cáo tổng hợp dạng văn bản (Giám đốc, Sale Admin) --}}
            @if ($this->canExport)
                <button type="button" class="vc-btn vc-btn--primary" wire:click="openExport">
                    <x-icon name="file" />
                    {{ $sp('export.button') }}
                </button>
            @endif
        </div>
    </header>

    {{-- ── Bộ lọc ──────────────────────────────────────────────────────── --}}
    <section class="vc-card rp-filters" aria-label="{{ __('reports.lead_sources.filters') }}">
        <label class="vc-field">
            <span class="vc-field__label">{{ $sp('month') }}</span>
            <select wire:model.live="month" class="vc-control">
                @foreach ($this->months as $option)
                    <option value="{{ $option }}" @selected($option === $this->activeMonth)>{{ $this->monthLabel($option) }}</option>
                @endforeach
            </select>
        </label>
        @if ($this->canFilterTeam)
            <label class="vc-field">
                <span class="vc-field__label">{{ $sp('team') }}</span>
                <select wire:model.live="team" class="vc-control">
                    <option value="">{{ $sp('all_teams') }}</option>
                    @foreach ($this->teams as $option)
                        <option value="{{ $option->team_id }}">{{ $option->team_name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="vc-field">
            <span class="vc-field__label">{{ $sp('employee') }}</span>
            <select wire:model.live="employee" class="vc-control">
                <option value="">{{ __('reports.all') }}</option>
                @foreach ($this->employeeOptions as $option)
                    <option value="{{ $option->employee_id }}">
                        {{ $option->full_name }}{{ $option->isActive() ? '' : ' ('.$sp('resigned').')' }}
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

    {{-- ── Thẻ KPI ─────────────────────────────────────────────────────── --}}
    <div class="rp-kpis rp-kpis--fit" style="--n: {{ count($kpis) }}">
        @foreach ($kpis as [$key, $icon, $value, $hint, $change, $rate])
            <div class="rp-stat">
                <div class="rp-stat__head">
                    <span class="rp-stat__icon"><x-icon :name="$icon" /></span>
                    <span>{{ $sp('kpi.'.$key) }}</span>
                </div>
                <div class="rp-stat__value {{ in_array($key, ['revenue', 'pipeline'], true) ? 'rp-stat__value--fit' : '' }}">{{ $value }}</div>
                @if ($rate !== null)
                    <div class="rp-goal {{ $rate >= 100 ? 'is-done' : '' }}">
                        <div class="rp-goal__bar"><span style="width: {{ min(100, $rate) }}%"></span></div>
                        <span class="rp-goal__pct">{{ $sp('kpi.attain', ['rate' => $pct($rate)]) }}</span>
                    </div>
                @endif
                @if ($change)
                    <div class="rp-delta {{ $change[1] ? 'rp-delta--'.$change[1] : '' }}">{{ $change[0] }}</div>
                @endif
                <div class="rp-stat__hint">{{ $hint }}</div>
            </div>
        @endforeach
    </div>

    @if (! $hasData)
        <section class="vc-card rp-empty">{{ $sp('no_data', ['month' => $monthText]) }}</section>
    @else
        {{-- ══ 1. XẾP HẠNG ĐỘI ═══════════════════════════════════════════ --}}
        @if ($teamRows)
            <h2 class="rp-section">{{ $sp('team_section') }}</h2>
            <p class="rp-section__sub">{{ $sp('team_section_sub') }}</p>

            <section class="vc-card rp-card">
                @include('livewire.reports.partials.sp-top', ['model' => 'teamTop', 'shown' => count($teamShown), 'total' => count($teamRows)])
                <div class="rp-scroll">
                    <table class="rp-table">
                        <thead>
                            <tr>
                                <th class="rp-pin rp-pin--rank">{{ $sp('rank') }}</th>
                                <th class="rp-pin rp-pin--2">{{ $sp('team') }}</th>
                                <th>{{ $sp('target.revenue') }}</th>
                                <th>{{ $sp('target.students') }}</th>
                                <th class="rp-num">{{ $sp('metric.close_rate') }}</th>
                                <th class="rp-num">{{ $sp('metric.avg_order') }}</th>
                                <th class="rp-num">{{ $sp('metric.open') }} <span class="rp-sub">{{ $sp('now') }}</span></th>
                                <th class="rp-num">{{ $sp('metric.flagged') }} <span class="rp-sub">{{ $sp('now') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($teamShown as $row)
                                <tr wire:key="team-{{ $row['id'] }}">
                                    <td class="rp-pin rp-pin--rank"><span class="rp-rank rp-rank--{{ $row['rank'] }}">{{ $row['rank'] }}</span></td>
                                    <th scope="row" class="rp-pin rp-pin--2">
                                        {{ $row['name'] }}
                                        <span class="rp-sub">{{ $sp('members_count', ['count' => $row['members']]) }}</span>
                                    </th>
                                    {{-- Mỗi chỉ tiêu 1 ô: thực tế + % đạt, thanh tiến độ, chỉ tiêu --}}
                                    @foreach (['revenue' => $money, 'students' => $num] as $metric => $fmt)
                                        @php $rate = $row['attain'][$metric]; @endphp
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
                                                    <span class="rp-sub">{{ $sp('of_target', ['target' => $fmt($row['target'][$metric])]) }}</span>
                                                @else
                                                    <span class="rp-sub">{{ $sp('no_target') }}</span>
                                                @endif
                                            </div>
                                        </td>
                                    @endforeach
                                    <td class="rp-num">
                                        {{ $pct($row['close_rate']) }}
                                        <span class="rp-sub">{{ $sp('won_lost', ['won' => $row['won'], 'lost' => $row['lost']]) }}</span>
                                    </td>
                                    <td class="rp-num">{{ $money($row['avg_order']) }}</td>
                                    <td class="rp-num">
                                        {{ $num($row['open']) }}
                                        <span class="rp-sub">{{ $money($row['open_value']) }}</span>
                                    </td>
                                    <td class="rp-num">
                                        @if ($row['flagged'])
                                            <span class="rp-flag">{{ $row['flagged'] }}</span>
                                        @else
                                            <span class="rp-ok">✓</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- ══ 2. XẾP HẠNG SALESPERSON ═══════════════════════════════════ --}}
        <h2 class="rp-section">{{ $sp('employee_section') }}</h2>
        <p class="rp-section__sub">
            {{ $sp('employee_section_sub', ['metric' => mb_strtolower($sp('metric.'.$sortKey))]) }}
            <span class="rp-legend"><span class="rp-cmp--up">●</span> {{ $sp('legend_up') }}</span>
            <span class="rp-legend"><span class="rp-cmp--down">●</span> {{ $sp('legend_down') }}</span>
        </p>

        <section class="vc-card rp-card">
            @include('livewire.reports.partials.sp-top', ['model' => 'employeeTop', 'shown' => count($rowsShown), 'total' => count($empRows),
                'teamModel' => 'employeeTeam', 'teamOptions' => $this->employeeTeamOptions])
            <div class="rp-scroll">
                <table class="rp-table rp-table--grouped">
                    <thead>
                        <tr>
                            <th class="rp-pin rp-pin--rank" rowspan="2">{{ $sp('rank') }}</th>
                            <th class="rp-pin rp-pin--2" rowspan="2">{{ $sp('employee') }}</th>
                            @foreach (SalesPerformance::COLUMN_GROUPS as $group => $cols)
                                <th class="rp-group rp-group--{{ $group }}" colspan="{{ count($cols) }}">{{ $sp('group.'.$group) }}</th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach (SalesPerformance::COLUMN_GROUPS as $group => $cols)
                                @foreach (array_keys($cols) as $i => $key)
                                    <th class="rp-num {{ $key === $sortKey ? 'is-sorted' : '' }} {{ $i === 0 ? 'rp-group-start' : '' }}">
                                        <button type="button" class="rp-sort" wire:click="$set('sort', '{{ $key }}')">
                                            {{ $sp('metric.'.$key) }}{{ $key === $sortKey ? (SalesPerformance::SORTS[$key] === 'desc' ? ' ↓' : ' ↑') : '' }}
                                        </button>
                                    </th>
                                @endforeach
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
                                    {{ $row['name'] }}{{ $row['resigned'] ? ' ('.$sp('resigned').')' : '' }}
                                    @if ($row['team'])
                                        <span class="rp-sub">{{ $row['team'] }}</span>
                                    @endif
                                </th>
                                @foreach (SalesPerformance::COLUMN_GROUPS as $cols)
                                    @foreach (array_keys($cols) as $i => $key)
                                        <td class="rp-num {{ $row['compare'][$key] ? 'rp-cmp--'.$row['compare'][$key] : '' }} {{ $key === $sortKey ? 'is-sorted' : '' }} {{ $i === 0 ? 'rp-group-start' : '' }}">
                                            {{ $formats[$key]($row[$key]) }}
                                            @if ($key === 'revenue' && $row['share'] !== null && $row['revenue'] > 0)
                                                <span class="rp-sub">{{ $sp('share', ['rate' => $pct($row['share'])]) }}</span>
                                            @elseif ($key === 'close_rate' && $row['won'] + $row['lost'] > 0)
                                                <span class="rp-sub">{{ $sp('won_lost', ['won' => $row['won'], 'lost' => $row['lost']]) }}</span>
                                            @elseif ($key === 'open' && $row['open'] > 0)
                                                <span class="rp-sub">{{ $money($row['open_value']) }}</span>
                                            @elseif ($key === 'activity' && $row['activity'] > 0)
                                                <span class="rp-sub">{{ $sp('activity_split', ['care' => $row['care'], 'appts' => $row['appts']]) }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if (count($empRows) > 1)
                        <tfoot>
                            <tr>
                                <td class="rp-pin rp-pin--rank"></td>
                                <th scope="row" class="rp-pin rp-pin--2">{{ $sp('total') }}</th>
                                @foreach (SalesPerformance::COLUMN_GROUPS as $cols)
                                    @foreach (array_keys($cols) as $i => $key)
                                        <td class="rp-num {{ $i === 0 ? 'rp-group-start' : '' }}">{{ $formats[$key]($empTotals[$key]) }}</td>
                                    @endforeach
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>

        {{-- ══ 3. VIỆC CẦN XỬ LÝ (hiện tại) ══════════════════════════════ --}}
        <h2 class="rp-section">{{ $sp('alert_section') }}</h2>
        <p class="rp-section__sub">{{ $sp('alert_section_sub', ['days' => SalesPerformanceReport::STALE_DAYS]) }}</p>

        <section class="vc-card rp-card">
            @if (! $alertRows)
                <p class="rp-empty rp-empty--inline rp-empty--ok">{{ $sp('alert_none') }}</p>
            @else
                <div class="rp-scroll">
                    <table class="rp-table">
                        <thead>
                            <tr>
                                <th class="rp-pin">{{ $sp('employee') }}</th>
                                @foreach (SalesPerformanceReport::ALERTS as $alert)
                                    <th class="rp-num">{{ $sp('alert.'.$alert, ['days' => SalesPerformanceReport::STALE_DAYS]) }}</th>
                                @endforeach
                                <th class="rp-num">{{ $sp('alert.flagged') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($alertRows as $row)
                                <tr wire:key="al-{{ $row['id'] }}">
                                    <th scope="row" class="rp-pin">
                                        {{ $row['name'] }}
                                        @if ($row['team'])
                                            <span class="rp-sub">{{ $row['team'] }}</span>
                                        @endif
                                    </th>
                                    @foreach (SalesPerformanceReport::ALERTS as $alert)
                                        <td class="rp-num">
                                            @if ($row['alerts'][$alert])
                                                <span class="rp-flag rp-flag--{{ $alert }}">{{ $row['alerts'][$alert] }}</span>
                                            @else
                                                <span class="rp-sub">0</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="rp-num"><strong>{{ $row['alerts']['flagged'] }}</strong></td>
                                    <td class="rp-num">
                                        @if ($url = $this->kanbanUrl($row))
                                            <a href="{{ $url }}" class="rp-link" wire:navigate>{{ $sp('alert_open') }} →</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if (count($alertRows) > 1)
                            <tfoot>
                                <tr>
                                    <th scope="row" class="rp-pin">{{ $sp('total') }}</th>
                                    @foreach (SalesPerformanceReport::ALERTS as $alert)
                                        <td class="rp-num">{{ array_sum(array_map(fn ($r) => $r['alerts'][$alert], $alertRows)) }}</td>
                                    @endforeach
                                    <td class="rp-num"><strong>{{ $flaggedTotal }}</strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            @endif
        </section>

        {{-- ══ 4. XU HƯỚNG 6 THÁNG ═══════════════════════════════════════ --}}
        <h2 class="rp-section">{{ $sp('trend_section') }}</h2>
        <p class="rp-section__sub">{{ $sp('trend_section_sub') }}</p>

        <div class="rp-grid">
            @foreach (['trend_revenue', 'trend_close'] as $key)
                @php
                    // Tỷ lệ thắng chỉ 1 đường (1 đội / 1 người / quá nhiều đội) thì không ghi "theo đội"
                    $title = $key === 'trend_close' && count($charts[$key]['series']) === 1 ? 'trend_close_all' : $key;
                @endphp
                @include('livewire.reports.partials.chart-card', ['id' => 'sp-'.$key, 'spec' => $charts[$key],
                    'title' => $sp('chart.'.$title), 'sub' => $sp('chart.'.$key.'_sub')])
            @endforeach
        </div>

    @endif

    {{-- Popup chọn thời gian xuất Excel --}}
    @if ($this->canExport)
        @include('livewire.reports.partials.report-export')
    @endif
</div>
