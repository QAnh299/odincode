@php
    use App\Livewire\Reports\SalesPerformance;
    use App\Livewire\Reports\SalesTeamPerformance;
    use App\Services\SalesPerformanceReport;

    $tab = $this->activeTab();
    $shareKey = $this->activeShare();
    $t = $this->totals;
    $teamRows = $this->byTeam;
    $sortKey = $this->activeSort();
    $columns = SalesPerformance::columns();
    $period = $this->period;
    $periodText = $period['from']->format('d/m/Y').' - '.$period['until']->format('d/m/Y');
    $active = $this->activePreset();
    $ls = fn ($key, $replace = []) => __('reports.lead_sources.'.$key, $replace);
    $sp = fn ($key, $replace = []) => __('reports.sales_performance.'.$key, $replace);
    $tk = fn ($key, $replace = []) => __('reports.sales_team.'.$key, $replace);

    $money = fn ($v) => $v === null ? '-' : number_format((float) $v, 0, ',', '.').' ₫';
    $num = fn ($v) => number_format((float) $v, 0, ',', '.');
    $dec = fn ($v) => number_format((float) $v, 1, ',', '.');
    $pct = fn ($v) => $v === null ? '-' : $dec($v).'%';
    $hours = fn ($v) => $v === null ? '-' : $sp('hours', ['value' => $dec($v)]);
    $days = fn ($v) => $v === null ? '-' : $sp('days', ['value' => $dec($v)]);
    $formats = [
        'revenue' => $money, 'avg_order' => $money, 'won' => $num, 'open' => $num, 'activity' => $num,
        'close_rate' => $pct, 'reach_rate' => $pct, 'show_rate' => $pct, 'avg_response_hours' => $hours,
    ];

    $hasData = $t['assigned'] > 0 || $t['won'] + $t['lost'] > 0 || $t['revenue'] > 0 || $t['activity'] > 0 || $t['open'] > 0;
@endphp

<div class="vc rp tp">
    {{-- Dùng lại giao diện chung .vc-* (voucher.css) + phần riêng báo cáo (report.css); Chart.js nạp ở charts.js --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/lead.css', 'resources/css/report.css', 'resources/js/charts.js'])
    @endassets

    <header class="vc-head">
        <div>
            <h1 class="visually-hidden">{{ $tk('title') }} · {{ $periodText }}</h1>
        </div>
        <div class="rp-head__actions">
            <span class="vc-results__loading" wire:loading.delay wire:target.except="export">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('reports.loading') }}
            </span>
            @if ($this->canExport)
                <button type="button" class="vc-btn vc-btn--primary" wire:click="openExport">
                    <x-icon name="file" />
                    {{ $sp('export.button') }}
                </button>
            @endif
        </div>
    </header>

    {{-- ── Bộ lọc (giống báo cáo Chất lượng nguồn Lead, không có kênh liên hệ / nguồn Lead) ─────── --}}
    <section class="vc-card ls-filters tp-filters" style="--n: {{ 1 + ($this->canFilterTeam ? 1 : 0) + ($tab === 'employees' ? 1 : 0) + 1 }}"
        aria-label="{{ $ls('filters') }}">
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.from') }}</span>
            <input type="date" wire:model.live="from" class="vc-control"
                max="{{ $period['until']->toDateString() }}">
        </label>
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.to') }}</span>
            <input type="date" wire:model.live="to" class="vc-control"
                min="{{ $period['from']->toDateString() }}">
        </label>
        {{-- Kỳ nhanh theo tháng: chọn → đặt Từ ngày / Đến ngày đúng tháng đó; tự nhập ngày khác → hiện "Tự chọn" --}}
        <label class="vc-field">
            <span class="vc-field__label">{{ $ls('quick') }}</span>
            <select class="vc-control" wire:key="quick-{{ $active ?? 'custom' }}" wire:change="preset($event.target.value)">
                <option value="" @selected(! $active) disabled>{{ $ls('preset.custom') }}</option>
                {{-- Tháng đang xem không có trong danh sách (vd. tháng tương lai) vẫn hiện để chọn sẵn --}}
                @foreach (array_unique(array_filter([$active, ...$this->months])) as $key)
                    <option value="{{ $key }}" @selected($active === $key)>{{ $tk('month_option', ['month' => $this->monthLabel($key)]) }}</option>
                @endforeach
            </select>
        </label>
        @if ($this->canFilterTeam)
            <label class="vc-field">
                <span class="vc-field__label">{{ $tk('team') }}</span>
                <select wire:model.live="team" class="vc-control">
                    <option value="">{{ $sp('all_teams') }}</option>
                    @foreach ($this->teams as $option)
                        <option value="{{ $option->team_id }}">{{ $option->team_name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.branch') }}</span>
            <select wire:model.live="branch" class="vc-control">
                <option value="">{{ __('reports.all_branches') }}</option>
                @foreach ($this->branches as $option)
                    <option value="{{ $option->branch_id }}">{{ $option->branch_name }}</option>
                @endforeach
            </select>
        </label>
        @if ($tab === 'employees')
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
        @endif
        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('reports.reset') }}
            </button>
        </div>
    </section>

    {{-- ── Tab (dưới bộ lọc, giống báo cáo Hiệu quả chuyển đổi Lead) ─────── --}}
    <nav class="rp-tabs" role="tablist">
        @foreach (SalesTeamPerformance::TABS as $key)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')"
                class="rp-tab {{ $tab === $key ? 'is-active' : '' }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}">
                {{ $tk('tab.'.$key) }}
            </button>
        @endforeach
    </nav>

    @if ($tab === 'teams')
        {{-- ══ TAB ĐỘI KINH DOANH ═══════════════════════════════════════════ --}}

        {{-- Thẻ KPI (giống báo cáo Chất lượng nguồn Lead): tên → số lớn + xu hướng 6 tháng → so với tháng trước → chú thích --}}
        <div class="ls-kpis tp-kpis--4">
            @foreach ($this->kpis as $k)
                <div class="ls-kpi {{ $k['alert'] ? 'tp-kpi--alert' : '' }}" wire:key="kpi-{{ $k['key'] }}">
                    <div class="ls-kpi__head">
                        <span class="ls-kpi__icon"><x-icon :name="$k['icon']" /></span>
                        <span>{{ $tk('kpi.'.$k['key']) }}</span>
                    </div>
                    <div class="ls-kpi__main">
                        <div class="ls-kpi__value {{ ($k['over_sla'] ?? false) ? 'tp-kpi__value--warn' : '' }}">
                            {{ $k['value'] }}@if ($k['unit'])<small>{{ $k['unit'] }}</small>@endif
                        </div>
                        @if ($k['spark'])
                            <svg class="ls-spark" viewBox="0 0 120 36" preserveAspectRatio="none" aria-hidden="true">
                                <polygon points="{{ $k['spark']['area'] }}" fill="{{ $k['color'] }}" fill-opacity="0.14" />
                                <polyline points="{{ $k['spark']['line'] }}" fill="none" stroke="{{ $k['color'] }}" stroke-width="2"
                                    stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                            </svg>
                        @endif
                    </div>
                    @if ($k['delta'])
                        <div class="ls-kpi__delta is-{{ $k['delta'][0] }}">
                            {{ $k['delta'][1] }}
                            @if ($k['delta'][2])
                                <span>{{ $k['delta'][2] }}</span>
                            @endif
                        </div>
                    @endif
                    <div class="ls-kpi__hint">{{ $k['hint'] }}</div>
                </div>
            @endforeach
        </div>

        @if (! $hasData)
            <section class="tp-card rp-empty">{{ __('reports.no_data') }}</section>
        @else
            @php
                $charts = $this->teamCharts;
                // Chiều cao 2 biểu đồ hàng trên theo số đội (nhiều đội thì mỗi dòng thấp hơn)
                $teamCount = count($charts['status']['labels']);
                $topHeight = max(300, $teamCount * ($teamCount > SalesTeamPerformance::STATUS_COMPACT_TEAMS ? 36 : 56) + 80);
                // Doanh số gộp 1 cột (1 đội hoặc quá nhiều đội) thì không phải "cột chồng theo đội"
                $revenueSub = count(array_filter($charts['revenue']['series'], fn ($s) => ($s['type'] ?? null) !== 'line')) > 1
                    ? 'chart.revenue_sub' : 'chart.revenue_sub_single';
            @endphp

            <div class="tp-row tp-row--even">
                @include('livewire.reports.partials.chart-card', ['id' => 'tp-status', 'spec' => $charts['status'],
                    'title' => $tk('chart.status'), 'sub' => $tk('chart.status_sub'), 'height' => $topHeight])

                {{-- Cơ cấu theo đội: biểu đồ tròn ở giữa, chú thích màu từng đội bên dưới; dropdown chọn doanh thu / số học viên --}}
                @php
                    $shareSpec = $charts['share'];
                @endphp
                <section class="vc-card rp-card tp-share">
                    <div class="tp-share__head">
                        <div>
                            <h3 class="rp-card__title">{{ $tk('chart.share') }}</h3>
                            <p class="rp-card__sub">{{ $tk('chart.share_sub.'.$shareKey) }}</p>
                        </div>
                        <label class="tp-share__pick">
                            <span class="visually-hidden">{{ $tk('chart.share_metric') }}</span>
                            <select wire:model.live="share" class="tp-select tp-select--sm">
                                @foreach (SalesTeamPerformance::SHARES as $option)
                                    <option value="{{ $option }}">{{ $tk('share.'.$option) }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    @if (! $shareSpec['hasData'])
                        <p class="rp-empty rp-empty--inline">{{ __('reports.no_data') }}</p>
                    @else
                        <div class="tp-share__body">
                            <div class="rp-chart tp-share__chart" style="height: {{ max(280, $topHeight - 150) }}px"
                                wire:key="tp-share-{{ md5(json_encode($shareSpec)) }}" wire:ignore
                                x-data="odinChart(@js(array_diff_key($shareSpec, ['legend' => 1, 'hasData' => 1])))">
                                <canvas x-ref="canvas" role="img" aria-label="{{ $tk('chart.share') }}"></canvas>
                            </div>
                            <ul class="tp-share__legend">
                                @foreach ($shareSpec['legend'] as $item)
                                    <li><span class="tp-share__swatch" style="--c: {{ $item['color'] }}"></span>{{ $item['name'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
            </div>

            <div class="tp-row tp-row--wide-left">
                @include('livewire.reports.partials.chart-card', ['id' => 'tp-revenue', 'spec' => $charts['revenue'],
                    'title' => $tk('chart.revenue'), 'sub' => $tk($revenueSub), 'height' => 360])
                @include('livewire.reports.partials.chart-card', ['id' => 'tp-win', 'spec' => $charts['win_rate'],
                    'title' => count($charts['win_rate']['series']) > 1 ? $tk('chart.win_rate') : $tk('chart.win_rate_all'),
                    'sub' => $tk('chart.win_rate_sub'), 'height' => 360])
            </div>

            {{-- ── Bảng xếp hạng đội (xếp theo % đạt chỉ tiêu doanh số; bấm 1 đội để lọc riêng đội đó) ── --}}
            @if ($teamRows)
                @php
                    $teamShown = $this->topRows($teamRows, $this->teamTop);
                    $clickable = $this->canFilterTeam && count($teamRows) > 1;
                @endphp
                <section class="tp-card tp-rank">
                    <div class="tp-rank__head">
                        <div>
                            <h3 class="rp-card__title">{{ $tk('ranking') }}</h3>
                            <p class="rp-card__sub">{{ $tk($clickable ? 'ranking_sub_click' : 'ranking_sub') }}</p>
                        </div>
                        @include('livewire.reports.partials.sp-top', ['model' => 'teamTop', 'shown' => count($teamShown), 'total' => count($teamRows)])
                    </div>
                    <div class="rp-scroll">
                        <table class="tp-table">
                            <thead>
                                <tr>
                                    <th class="tp-col-rank">{{ $tk('col.rank') }}</th>
                                    <th>{{ $tk('col.team') }}</th>
                                    <th class="tp-col-goal">{{ $tk('col.revenue') }}</th>
                                    <th class="is-num">{{ $tk('col.students') }}</th>
                                    <th class="is-num">{{ $tk('col.close_rate') }}</th>
                                    <th class="is-num">{{ $tk('col.close_days') }}</th>
                                    <th class="is-num">{{ $tk('col.flagged') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($teamShown as $row)
                                    @php
                                        $rate = $row['attain']['revenue'];
                                        $goal = $row['target']['revenue'] ?? null;
                                        $studentGoal = $row['target']['students'] ?? null;
                                        // Mức đạt chỉ tiêu: >= 100% xanh lá, >= 80% xanh dương, >= 50% vàng, thấp hơn đỏ
                                        $level = match (true) {
                                            $rate === null => '',
                                            $rate >= 100   => 'is-done',
                                            $rate >= 80    => 'is-good',
                                            $rate >= 50    => 'is-mid',
                                            default        => 'is-low',
                                        };
                                    @endphp
                                    <tr wire:key="rank-{{ $row['id'] }}" @if ($clickable) class="is-click" wire:click="$set('team', '{{ $row['id'] }}')" title="{{ $tk('ranking_open') }}" @endif>
                                        <td class="tp-col-rank">
                                            <span class="tp-place {{ $row['rank'] <= 3 ? 'tp-place--'.$row['rank'] : '' }}">{{ $row['rank'] }}</span>
                                        </td>
                                        <td>
                                            <div class="tp-team">
                                                <span class="tp-team__dot" style="--c: {{ $this->teamColor($row['id']) }}"></span>
                                                <strong>{{ $row['name'] }}</strong>
                                            </div>
                                            <span class="tp-sub tp-team__sub">{{ $tk('members', ['count' => $row['members']]) }}</span>
                                        </td>
                                        {{-- Doanh số / chỉ tiêu: số liệu + chip % đạt, thanh tiến độ bên dưới --}}
                                        <td class="tp-col-goal">
                                            <div class="tp-goal">
                                                <span>
                                                    <strong>{{ $this->shortMoney($row['revenue']) }}</strong>
                                                    @if ($goal)
                                                        <span class="tp-muted">/ {{ $this->shortMoney($goal) }}</span>
                                                    @endif
                                                </span>
                                                @if ($goal)
                                                    <span class="tp-rate {{ $level }}">{{ $pct($rate) }}</span>
                                                @else
                                                    <span class="tp-sub">{{ $sp('no_target') }}</span>
                                                @endif
                                            </div>
                                            @if ($goal)
                                                <div class="tp-bar {{ $level }}"><span style="width: {{ min(100, $rate) }}%"></span></div>
                                            @endif
                                        </td>
                                        <td class="is-num">
                                            <strong>{{ $num($row['students']) }}</strong>@if ($studentGoal)<span class="tp-muted"> / {{ $num($studentGoal) }}</span>@endif
                                            <span class="tp-sub">{{ $studentGoal ? $tk('attain', ['rate' => $pct($row['attain']['students'])]) : $sp('no_target') }}</span>
                                        </td>
                                        <td class="is-num">
                                            <strong>{{ $pct($row['close_rate']) }}</strong>
                                            <span class="tp-sub">{{ $sp('won_lost', ['won' => $num($row['won']), 'lost' => $num($row['lost'])]) }}</span>
                                        </td>
                                        <td class="is-num"><strong>{{ $days($row['avg_close_days']) }}</strong></td>
                                        <td class="is-num">
                                            @if ($row['flagged'])
                                                <span class="tp-pill tp-pill--warn">{{ $row['flagged'] }}</span>
                                            @else
                                                <span class="rp-ok">✓</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                </section>
            @endif
        @endif
    @else
        {{-- ══ TAB NHÂN VIÊN KINH DOANH ═════════════════════════════════════ --}}
        @if (! $hasData)
            <section class="tp-card rp-empty">{{ __('reports.no_data') }}</section>
        @else
            @php
                $empRows = $this->employeeView;
                $empTotals = $this->employeeViewTotals;
                $rowsShown = $this->topRows($empRows, $this->employeeTop);
                $alertRows = $this->alertRows;
                $flaggedTotal = array_sum(array_map(fn ($r) => $r['alerts']['flagged'], $alertRows));
                $empCharts = $this->employeeCharts;
                $empShareKey = $this->activeEmpShare();
                $empMetricKey = $this->activeEmpMetric();
                $empTop = $empCharts['top'];
                $empHeight = max(300, $empTop['count'] * 34 + 50);
            @endphp

            {{-- ── 2 biểu đồ: cơ cấu theo nhân viên (tròn) · top nhân viên (thanh ngang, màu theo đội) ── --}}
            <div class="tp-row tp-row--even tp-emp-charts">
                <section class="vc-card rp-card tp-share">
                    <div class="tp-share__head">
                        <div>
                            <h3 class="rp-card__title">{{ $tk('emp.share') }}</h3>
                            <p class="rp-card__sub">{{ $tk('emp.share_sub.'.$empShareKey) }}</p>
                        </div>
                        <label>
                            <span class="visually-hidden">{{ $tk('chart.share_metric') }}</span>
                            <select wire:model.live="empShare" class="tp-select tp-select--sm">
                                @foreach (SalesTeamPerformance::SHARES as $option)
                                    <option value="{{ $option }}">{{ $tk('share.'.$option) }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    @if (! $empCharts['share']['hasData'])
                        <p class="rp-empty rp-empty--inline">{{ __('reports.no_data') }}</p>
                    @else
                        <div class="tp-share__body">
                            <div class="rp-chart tp-share__chart" style="height: {{ $empHeight - 70 }}px"
                                wire:key="tp-emp-share-{{ md5(json_encode($empCharts['share'])) }}" wire:ignore
                                x-data="odinChart(@js(array_diff_key($empCharts['share'], ['legend' => 1, 'hasData' => 1])))">
                                <canvas x-ref="canvas" role="img" aria-label="{{ $tk('emp.share') }}"></canvas>
                            </div>
                            <ul class="tp-share__legend">
                                @foreach ($empCharts['share']['legend'] as $item)
                                    <li><span class="tp-share__swatch" style="--c: {{ $item['color'] }}"></span>{{ $item['name'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>

                <section class="vc-card rp-card tp-share">
                    <div class="tp-share__head">
                        <div>
                            <h3 class="rp-card__title">{{ $tk('emp.top', ['count' => SalesTeamPerformance::EMP_TOP]) }}</h3>
                            <p class="rp-card__sub">{{ $tk('emp.top_sub.'.$empMetricKey, ['min' => SalesTeamPerformance::EMP_MIN_CLOSED]) }}</p>
                        </div>
                        <label>
                            <span class="visually-hidden">{{ $tk('chart.share_metric') }}</span>
                            <select wire:model.live="empMetric" class="tp-select tp-select--sm">
                                @foreach (array_keys(SalesTeamPerformance::EMP_METRICS) as $option)
                                    <option value="{{ $option }}">{{ $tk('emp.metric.'.$option) }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    @if (! $empTop['count'])
                        <p class="rp-empty rp-empty--inline">{{ __('reports.no_data') }}</p>
                    @else
                        <div class="rp-chart" style="height: {{ $empHeight }}px"
                            wire:key="tp-emp-top-{{ md5(json_encode($empTop)) }}" wire:ignore
                            x-data="odinChart(@js(array_diff_key($empTop, ['legend' => 1, 'count' => 1])))">
                            <canvas x-ref="canvas" role="img" aria-label="{{ $tk('emp.top', ['count' => SalesTeamPerformance::EMP_TOP]) }}"></canvas>
                        </div>
                        {{-- Màu thanh = màu đội của nhân viên --}}
                        <ul class="tp-share__legend tp-emp-legend">
                            @foreach ($empTop['legend'] as $item)
                                <li><span class="tp-share__swatch" style="--c: {{ $item['color'] }}"></span>{{ $item['name'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            @include('livewire.reports.partials.sp-employees', ['employeeTitle' => $tk('employee_ranking')])
            @include('livewire.reports.partials.sp-alerts', ['alertTitle' => $tk('alerts')])
        @endif
    @endif

    {{-- Popup chọn thời gian xuất Excel --}}
    @if ($this->canExport)
        @include('livewire.reports.partials.report-export')
    @endif
</div>
