@php
    use App\Livewire\Reports\LeadConversion;
    use App\Services\LeadConversionReport;

    $tab = $this->activeTab();
    $s = $this->summary;
    $prev = $this->previous;
    $seesAll = $this->seesAll;

    $L = fn (string $key, array $params = []) => __('reports.lead_conversion.'.$key, $params);
    $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', '.').' ₫';
    $num = fn ($v) => number_format((float) $v, 0, ',', '.');
    $dec = fn ($v) => number_format((float) $v, 1, ',', '.');
    $pct = fn ($v) => $v === null ? '—' : $dec($v).'%';
    // Thời lượng (giờ): dưới 48 giờ hiện giờ, từ 48 giờ hiện ngày
    $dur = fn ($h) => $h === null ? '—' : ($h < 48 ? $L('hours', ['value' => $dec($h)]) : $L('days', ['value' => $dec($h / 24)]));
    $days = fn ($d) => $d === null ? '—' : $dur($d * 24);

    // So với kỳ trước: [chữ, class]; $dir = 1 cao là tốt, -1 thấp là tốt; $points = chênh lệch điểm % (cho tỷ lệ)
    $delta = function ($cur, $old, int $dir = 1, bool $points = false) use ($prev, $dec, $L) {
        if (! $prev || $cur === null || $old === null) {
            return null;
        }
        if ($points) {
            $change = $cur - $old;
            $text = $L('delta_points', ['value' => $dec(abs($change))]);
        } else {
            if ((float) $old == 0) {
                return $cur > 0 ? [$L('delta_new'), 'up'] : null;
            }
            $change = ($cur - $old) * 100 / $old;
            $text = $dec(abs($change)).'%';
        }
        if (abs($change) < 0.05) {
            return [$L('delta_same'), ''];
        }

        return [($change > 0 ? '▲ ' : '▼ ').$text, $change * $dir > 0 ? 'up' : 'down'];
    };

    $hasData = $s['leads'] > 0 || $this->revenue > 0;
@endphp

<div class="vc rp">
    {{-- Dùng lại giao diện chung .vc-* (voucher.css) + phần riêng báo cáo (report.css); Chart.js nạp ở charts.js --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/report.css', 'resources/js/charts.js'])
    @endassets

    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ $L('title') }}</h1>
            <p class="vc-head__sub">{{ $L('subtitle') }} · <strong>{{ $this->scopeLabel() }}</strong></p>
        </div>
        <div class="rp-head__actions">
            <span class="vc-results__loading" wire:loading.delay wire:target.except="export">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('reports.loading') }}
            </span>
            {{-- Xuất Excel theo bộ lọc đang chọn (Giám đốc, Sale Admin): mỗi tab 1 sheet + danh sách Lead --}}
            @if ($this->canExport)
                <button type="button" class="vc-btn vc-btn--primary" wire:click="export" wire:loading.attr="disabled" wire:target="export"
                    @disabled(! $hasData)>
                    <span wire:loading.remove wire:target="export"><x-icon name="file" /></span>
                    <span class="spinner-border spinner-border-sm" wire:loading wire:target="export" aria-hidden="true"></span>
                    {{ $L('export.button') }}
                </button>
            @endif
        </div>
    </header>

    {{-- ── Bộ lọc ──────────────────────────────────────────────────────── --}}
    <section class="vc-card rp-filters rp-filters--wide" aria-label="{{ __('reports.lead_sources.filters') }}">
        <label class="vc-field">
            <span class="vc-field__label">{{ $L('from') }}</span>
            <input type="date" wire:model.live="from" class="vc-control" max="{{ $to ?: '' }}">
        </label>
        <label class="vc-field">
            <span class="vc-field__label">{{ $L('to') }}</span>
            <input type="date" wire:model.live="to" class="vc-control" min="{{ $from ?: '' }}">
        </label>
        @if ($this->canFilterEmployee)
            <label class="vc-field">
                <span class="vc-field__label">{{ __('reports.branch') }}</span>
                <select wire:model.live="branch" class="vc-control">
                    <option value="">{{ __('reports.all_branches') }}</option>
                    @foreach ($this->branches as $option)
                        <option value="{{ $option->branch_id }}">{{ $option->branch_name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.dimension.source') }}</span>
            <select wire:model.live="source" class="vc-control">
                <option value="">{{ __('reports.all') }}</option>
                @foreach ($this->sources as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </label>
        @if ($this->canFilterTeam)
            <label class="vc-field">
                <span class="vc-field__label">{{ __('reports.sales_performance.team') }}</span>
                <select wire:model.live="team" class="vc-control">
                    <option value="">{{ __('reports.sales_performance.all_teams') }}</option>
                    @foreach ($this->teamOptions as $option)
                        <option value="{{ $option->team_id }}">{{ $option->team_name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        @if ($this->canFilterEmployee)
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
        @endif
        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('reports.reset') }}
            </button>
        </div>
    </section>

    {{-- ── Tab ─────────────────────────────────────────────────────────── --}}
    <nav class="rp-tabs" role="tablist">
        @foreach (LeadConversion::TABS as $key)
            <button type="button" role="tab" wire:click="setTab('{{ $key }}')"
                class="rp-tab {{ $tab === $key ? 'is-active' : '' }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}">
                {{ $L('tab.'.$key) }}
            </button>
        @endforeach
    </nav>

    @if (! $hasData)
        <section class="vc-card rp-empty">{{ __('reports.no_data') }}</section>
    @else
        @switch($tab)
            {{-- ══ 1. TỔNG QUAN PHỄU ══════════════════════════════════════ --}}
            @case('overview')
                @php
                    $o = $this->overview;
                    $ps = $prev['summary'] ?? null;
                    $kpis = [
                        [$seesAll ? 'leads' : 'assigned', 'layers', $num($s['leads']),
                            $seesAll ? $L('kpi.leads_hint', ['count' => $num($s['new'])]) : $L('kpi.assigned_hint', ['count' => $num($s['open'])]),
                            $delta($s['leads'], $ps['leads'] ?? null)],
                        $seesAll
                            ? ['conversion', 'share', $pct($s['conversion_rate']), $L('kpi.conversion_hint', ['count' => $num($s['opportunities'])]),
                                $delta($s['conversion_rate'], $ps['conversion_rate'] ?? null, 1, true)]
                            : ['contact', 'phone', $pct($s['contact_rate']), $L('kpi.contact_hint', ['count' => $num($s['contacted'])]),
                                $delta($s['contact_rate'], $ps['contact_rate'] ?? null, 1, true)],
                        ['win', 'check-circle', $pct($s['win_rate']),
                            $L('kpi.win_hint', ['count' => $num($s['won']), 'rate' => $pct($s['close_rate'])]),
                            $delta($s['win_rate'], $ps['win_rate'] ?? null, 1, true)],
                        ['students', 'user-check', $num($s['students']), $L('kpi.students_hint', ['rate' => $pct($s['student_rate'])]),
                            $delta($s['students'], $ps['students'] ?? null)],
                        ['revenue', 'cash', $money($this->revenue), $L('kpi.revenue_hint', ['value' => $money($s['order_avg'])]),
                            $delta($this->revenue, $prev['revenue'] ?? null)],
                    ];
                @endphp

                @include('livewire.reports.partials.lc-kpis', ['kpis' => $kpis, 'prevLabel' => $prev['label'] ?? null])

                <div class="rp-grid">
                    <section class="vc-card rp-card">
                        <h3 class="rp-card__title">{{ $L('overview.funnel') }}</h3>
                        <p class="rp-card__sub">{{ $L('overview.funnel_sub') }}</p>
                        <div class="rp-chart" style="height: {{ max(220, count($o['funnel']) * 46 + 40) }}px"
                            wire:key="lc-funnel-{{ md5(json_encode($o['charts']['funnel'])) }}" wire:ignore
                            x-data="odinChart(@js($o['charts']['funnel']))">
                            <canvas x-ref="canvas" role="img" aria-label="{{ $L('overview.funnel') }}"></canvas>
                        </div>
                        <ol class="rp-steps">
                            @foreach (array_slice($o['funnel'], 1) as $step)
                                <li>
                                    <span>{{ $step['prev'] }} → {{ $step['name'] }}</span>
                                    <strong>{{ $pct($step['rate']) }} <span class="rp-sub rp-sub--inline">{{ $L('overview.of_total', ['rate' => $pct($step['overall'])]) }}</span></strong>
                                </li>
                            @endforeach
                        </ol>
                    </section>

                    @include('livewire.reports.partials.chart-card', ['id' => 'lc-outcomes', 'spec' => $o['charts']['outcomes'],
                        'title' => $L('overview.outcomes'), 'sub' => $L('overview.outcomes_sub'), 'height' => 320])
                    @include('livewire.reports.partials.chart-card', ['id' => 'lc-trend-count', 'spec' => $o['charts']['trend_count'],
                        'title' => $L('overview.trend_count', ['period' => $L('period.'.$this->period)]), 'sub' => $L('overview.trend_count_sub')])
                    @include('livewire.reports.partials.chart-card', ['id' => 'lc-trend-rate', 'spec' => $o['charts']['trend_rate'],
                        'title' => $L('overview.trend_rate', ['period' => $L('period.'.$this->period)]), 'sub' => $L('overview.trend_rate_sub')])
                    @include('livewire.reports.partials.chart-card', ['id' => 'lc-revenue', 'spec' => $o['charts']['revenue'], 'wide' => true,
                        'title' => $L('overview.revenue', ['period' => $L('period.'.$this->period)]), 'sub' => $L('overview.revenue_sub')])
                </div>
                @break

            {{-- ══ 2. HIỆU QUẢ THEO NGUỒN ═════════════════════════════════ --}}
            @case('sources')
                @php $d = $this->bySource; @endphp

                <section class="vc-card rp-card">
                    <h3 class="rp-card__title">{{ $L('sources.table') }}</h3>
                    <p class="rp-card__sub">{{ $L('sources.table_sub') }}</p>
                    <div class="table-responsive">
                        <table class="rp-table">
                            <thead>
                                <tr>
                                    <th>{{ __('reports.dimension.source') }}</th>
                                    <th class="rp-num">{{ $L('metric.leads') }}</th>
                                    @if ($seesAll)
                                        <th class="rp-num">{{ $L('metric.conversion_rate') }}</th>
                                    @endif
                                    <th class="rp-num">{{ $L('metric.contact_rate') }}</th>
                                    <th class="rp-num">{{ $L('metric.won') }}</th>
                                    <th class="rp-num">{{ $L('metric.win_rate') }}</th>
                                    <th class="rp-num">{{ $L('metric.close_rate') }}</th>
                                    <th class="rp-num">{{ $L('metric.close_median') }}</th>
                                    <th class="rp-num">{{ $L('metric.revenue') }}</th>
                                    <th class="rp-num">{{ $L('metric.revenue_per_lead') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($d['rows'] as $row)
                                    <tr wire:key="src-{{ md5($row['name']) }}">
                                        <th scope="row">{{ $row['name'] }}</th>
                                        <td class="rp-num">{{ $num($row['leads']) }}</td>
                                        @if ($seesAll)
                                            <td class="rp-num">
                                                {{ $pct($row['conversion_rate']) }}
                                                <span class="rp-sub">{{ $num($row['opportunities']) }}</span>
                                            </td>
                                        @endif
                                        <td class="rp-num">{{ $pct($row['contact_rate']) }}</td>
                                        <td class="rp-num">{{ $num($row['won']) }}</td>
                                        <td class="rp-num"><span class="rp-bar" style="--w: {{ min(100, $row['win_rate'] ?? 0) }}%">{{ $pct($row['win_rate']) }}</span></td>
                                        <td class="rp-num">{{ $pct($row['close_rate']) }}</td>
                                        <td class="rp-num">{{ $days($row['close_median']) }}</td>
                                        <td class="rp-num">{{ $money($row['revenue']) }}</td>
                                        <td class="rp-num">{{ $money($row['revenue_per_lead']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            @php $t = $d['total']; @endphp
                            <tfoot>
                                <tr>
                                    <th scope="row">{{ $L('total') }}</th>
                                    <td class="rp-num">{{ $num($t['leads']) }}</td>
                                    @if ($seesAll)
                                        <td class="rp-num">{{ $pct($t['conversion_rate']) }}</td>
                                    @endif
                                    <td class="rp-num">{{ $pct($t['contact_rate']) }}</td>
                                    <td class="rp-num">{{ $num($t['won']) }}</td>
                                    <td class="rp-num">{{ $pct($t['win_rate']) }}</td>
                                    <td class="rp-num">{{ $pct($t['close_rate']) }}</td>
                                    <td class="rp-num">{{ $days($t['close_median']) }}</td>
                                    <td class="rp-num">{{ $money($t['revenue']) }}</td>
                                    <td class="rp-num">{{ $money($t['revenue_per_lead']) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <div class="rp-grid">
                    @foreach (['scatter', 'rates', 'revenue', 'methods'] as $key)
                        @include('livewire.reports.partials.chart-card', ['id' => 'lc-src-'.$key, 'spec' => $d['charts'][$key],
                            'title' => $L('sources.chart.'.$key), 'sub' => $L('sources.chart.'.$key.'_sub'),
                            'height' => $key === 'revenue' ? max(220, count($d['charts'][$key]['labels']) * 40 + 40) : 320])
                    @endforeach
                </div>
                @break

            {{-- ══ 3. TỐC ĐỘ CHUYỂN ĐỔI ═══════════════════════════════════ --}}
            @case('velocity')
                @php
                    $v = $this->velocity;
                    $kpis = [
                        ['response', 'clock', $dur($s['response_median']), $L('kpi.response_hint', ['value' => $dur($s['response_avg'])]), null],
                        ['within24', 'check', $pct($v['within24']), $L('kpi.within24_hint', ['count' => $num($v['assigned'])]), null],
                        ['close_time', 'hourglass', $days($s['close_median']), $L('kpi.close_time_hint', ['value' => $days($s['close_avg'])]), null],
                        ['open', 'layers', $num($v['open']), $L('kpi.open_hint', ['count' => $num($v['untouched'])]), null],
                        ['stuck', 'alert', $num($v['stuck_count']), $L('kpi.stuck_hint'), null],
                    ];
                @endphp

                @include('livewire.reports.partials.lc-kpis', ['kpis' => $kpis, 'prevLabel' => null])

                <section class="vc-card rp-card">
                    <h3 class="rp-card__title">{{ $L('velocity.steps') }}</h3>
                    <p class="rp-card__sub">{{ $L('velocity.steps_sub') }}</p>
                    <div class="table-responsive">
                        <table class="rp-table">
                            <thead>
                                <tr>
                                    <th>{{ $L('velocity.step') }}</th>
                                    <th class="rp-num">{{ $L('velocity.count') }}</th>
                                    <th class="rp-num">{{ $L('velocity.median') }}</th>
                                    <th class="rp-num">{{ $L('velocity.avg') }}</th>
                                    <th class="rp-num">{{ $L('velocity.p90') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($v['steps'] as $step)
                                    <tr wire:key="step-{{ $step['key'] }}" class="{{ $step['key'] === 'total' ? 'rp-row--total' : '' }}">
                                        <th scope="row">{{ $step['name'] }}</th>
                                        <td class="rp-num">{{ $num($step['count']) }}</td>
                                        <td class="rp-num"><strong>{{ $dur($step['median']) }}</strong></td>
                                        <td class="rp-num">{{ $dur($step['avg']) }}</td>
                                        <td class="rp-num">{{ $dur($step['p90']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="rp-grid">
                    @foreach (['response_count', 'response_rate', 'close'] as $key)
                        @include('livewire.reports.partials.chart-card', ['id' => 'lc-vel-'.$key, 'spec' => $v['charts'][$key],
                            'title' => $L('velocity.chart.'.$key), 'sub' => $L('velocity.chart.'.$key.'_sub')])
                    @endforeach
                </div>

                <section class="vc-card rp-card">
                    <h3 class="rp-card__title">{{ $L('velocity.stuck') }}</h3>
                    <p class="rp-card__sub">
                        {{ $L('velocity.stuck_sub', collect(LeadConversionReport::STUCK_DAYS)->mapWithKeys(fn ($d, $id) => [strtolower($id) => $d])->all()) }}
                        @if ($v['stuck_count'] > count($v['stuck']))
                            {{ $L('velocity.stuck_more', ['shown' => count($v['stuck']), 'count' => $v['stuck_count']]) }}
                        @endif
                    </p>
                    @if (! $v['stuck'])
                        <p class="rp-empty rp-empty--inline">{{ $L('velocity.stuck_none') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="rp-table">
                                <thead>
                                    <tr>
                                        <th>{{ $L('velocity.lead') }}</th>
                                        <th>{{ $L('velocity.stage') }}</th>
                                        <th>{{ __('reports.sales_performance.employee') }}</th>
                                        <th class="rp-num">{{ $L('velocity.in_stage') }}</th>
                                        <th class="rp-num">{{ $L('velocity.contacts') }}</th>
                                        <th class="rp-num">{{ $L('velocity.last_contact') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($v['stuck'] as $row)
                                        @php $url = $this->opportunityUrl($row['opportunity']); @endphp
                                        <tr wire:key="stuck-{{ $row['id'] }}">
                                            <th scope="row">
                                                @if ($url)
                                                    <a href="{{ $url }}" wire:navigate>{{ $row['name'] }}</a>
                                                @else
                                                    {{ $row['name'] }}
                                                @endif
                                                <span class="rp-sub">{{ $row['source'] }}</span>
                                            </th>
                                            <td>{{ $row['stage_name'] }}</td>
                                            <td>{{ $row['employee_name'] ?? '—' }}</td>
                                            <td class="rp-num rp-cmp--down">
                                                {{ $L('days', ['value' => $dec($row['days'])]) }}
                                                <span class="rp-sub">{{ $L('velocity.limit', ['days' => $row['limit']]) }}</span>
                                            </td>
                                            <td class="rp-num">{{ $num($row['contacts']) }}</td>
                                            <td class="rp-num">{{ $row['last_contact'] ? date('d/m/Y H:i', $row['last_contact']) : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
                @break

            {{-- ══ 4. CHẤT LƯỢNG CHĂM SÓC ═════════════════════════════════ --}}
            @case('care')
                @php
                    $c = $this->care;
                    $kpis = [
                        ['contacts', 'phone', $num($c['total']), $L('kpi.contacts_hint', ['rate' => $pct($c['success_rate'])]), null],
                        ['unreachable', 'alert', $pct($c['unreachable_rate']), $L('kpi.unreachable_hint'), null],
                        ['per_won', 'check-circle', $c['per_won'] === null ? '—' : $dec($c['per_won']), $L('kpi.per_won_hint'), null],
                        ['per_lost', 'x', $c['per_lost'] === null ? '—' : $dec($c['per_lost']), $L('kpi.per_lost_hint'), null],
                        ['appointments', 'calendar', $pct($c['appointment_rate']), $L('kpi.appointments_hint'), null],
                    ];
                    $weekdays = range(1, 7);
                @endphp

                @include('livewire.reports.partials.lc-kpis', ['kpis' => $kpis, 'prevLabel' => null])

                <div class="rp-grid">
                    @include('livewire.reports.partials.chart-card', ['id' => 'lc-care-activities', 'spec' => $c['charts']['activities'],
                        'title' => $L('care.activities'), 'sub' => $L('care.activities_sub'),
                        'height' => max(200, count($c['activities']) * 56 + 60)])
                    @include('livewire.reports.partials.chart-card', ['id' => 'lc-care-touches', 'spec' => $c['charts']['touches'],
                        'title' => $L('care.touches'), 'sub' => $L('care.touches_sub')])
                </div>

                <div class="rp-grid">
                    <section class="vc-card rp-card">
                        <h3 class="rp-card__title">{{ $L('care.activity_table') }}</h3>
                        <p class="rp-card__sub">{{ $L('care.activity_table_sub') }}</p>
                        <div class="table-responsive">
                            <table class="rp-table">
                                <thead>
                                    <tr>
                                        <th>{{ $L('care.activity') }}</th>
                                        <th class="rp-num">{{ $L('care.total') }}</th>
                                        @foreach (\App\Models\CareResult::RESULTS as $r)
                                            <th class="rp-num">{{ $L('care.result.'.$r) }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($c['activities'] as $a)
                                        <tr>
                                            <th scope="row">{{ $a['name'] }}</th>
                                            <td class="rp-num">{{ $num($a['total']) }}</td>
                                            @foreach (\App\Models\CareResult::RESULTS as $r)
                                                <td class="rp-num">{{ $pct($a['rate'][$r]) }}<span class="rp-sub">{{ $num($a[$r]) }}</span></td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="rp-empty">{{ __('reports.no_data') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="vc-card rp-card">
                        <h3 class="rp-card__title">{{ $L('care.appointments') }}</h3>
                        <p class="rp-card__sub">{{ $L('care.appointments_sub') }}</p>
                        <div class="table-responsive">
                            <table class="rp-table">
                                <thead>
                                    <tr>
                                        <th>{{ $L('care.type') }}</th>
                                        <th class="rp-num">{{ $L('care.total') }}</th>
                                        <th class="rp-num">{{ __('appointments.status.Success') }}</th>
                                        <th class="rp-num">{{ __('appointments.status.Failed') }}</th>
                                        <th class="rp-num">{{ __('appointments.status.Scheduled') }}</th>
                                        <th class="rp-num">{{ $L('care.show_rate') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($c['types'] as $type => $t)
                                        <tr>
                                            <th scope="row">{{ __('opportunities.appointment_type_name.'.$type) }}</th>
                                            <td class="rp-num">{{ $num($t['total']) }}</td>
                                            <td class="rp-num">{{ $num($t['Success']) }}</td>
                                            <td class="rp-num">{{ $num($t['Failed']) }}</td>
                                            <td class="rp-num">{{ $num($t['Scheduled']) }}</td>
                                            <td class="rp-num"><strong>{{ $pct($t['rate']) }}</strong></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="rp-empty">{{ __('reports.no_data') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <section class="vc-card rp-card">
                    <h3 class="rp-card__title">{{ $L('care.heatmap') }}</h3>
                    <p class="rp-card__sub">{{ $L('care.heatmap_sub') }}</p>
                    @if (! $c['total'])
                        <p class="rp-empty rp-empty--inline">{{ __('reports.no_data') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="rp-heat">
                                <thead>
                                    <tr>
                                        <th></th>
                                        @foreach ($c['heat_hours'] as $hour)
                                            <th>{{ $hour }}h</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($weekdays as $day)
                                        <tr>
                                            <th scope="row">{{ $L('weekday.'.$day) }}</th>
                                            @foreach ($c['heat_hours'] as $hour)
                                                @php $cell = $c['heat'][$day][$hour] ?? null; @endphp
                                                @if ($cell)
                                                    <td style="--a: {{ round(8 + 32 * $cell['total'] / max(1, $c['heat_max'])) }}%"
                                                        title="{{ $L('care.heat_cell', ['day' => $L('weekday.'.$day), 'hour' => $hour, 'count' => $cell['total'], 'rate' => $pct($cell['rate'])]) }}">
                                                        {{ $pct($cell['rate']) }}<span>{{ $cell['total'] }}</span>
                                                    </td>
                                                @else
                                                    <td class="is-empty"></td>
                                                @endif
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
                @break

            {{-- ══ 5. COHORT THEO THÁNG ═══════════════════════════════════ --}}
            @case('cohort')
                @php $co = $this->cohort; @endphp

                <section class="vc-card rp-card">
                    <div class="rp-card__head">
                        <div>
                            <h3 class="rp-card__title">{{ $L('cohort.title_'.$co['metric']) }}</h3>
                            <p class="rp-card__sub">{{ $L('cohort.sub') }}</p>
                        </div>
                        <label class="vc-field">
                            <span class="vc-field__label">{{ $L('cohort.metric') }}</span>
                            <select wire:model.live="cohortMetric" class="vc-control">
                                @foreach (LeadConversion::COHORT_METRICS as $metric)
                                    @continue($metric === 'converted' && ! $seesAll)
                                    <option value="{{ $metric }}" @selected($metric === $co['metric'])>{{ $L('cohort.metric_'.$metric) }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    @if (! $co['rows'])
                        <p class="rp-empty rp-empty--inline">{{ __('reports.no_data') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="rp-table rp-cohort">
                                <thead>
                                    <tr>
                                        <th>{{ $L('cohort.month') }}</th>
                                        <th class="rp-num">{{ $L('metric.leads') }}</th>
                                        @for ($m = 0; $m <= $co['width']; $m++)
                                            <th class="rp-num" title="{{ $L('cohort.offset', ['m' => $m]) }}">M{{ $m }}</th>
                                        @endfor
                                        <th class="rp-num">{{ $L('cohort.to_date') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($co['rows'] as $row)
                                        <tr wire:key="cohort-{{ $row['month'] }}">
                                            <th scope="row">{{ $row['label'] }}</th>
                                            <td class="rp-num">{{ $num($row['leads']) }}</td>
                                            @foreach ($row['cells'] as $cell)
                                                @if ($cell === null)
                                                    <td class="is-empty"></td>
                                                @else
                                                    <td class="rp-num rp-cohort__cell" style="--a: {{ round(min(100, $cell)) }}%">{{ $pct($cell) }}</td>
                                                @endif
                                            @endforeach
                                            <td class="rp-num"><strong>{{ $pct($row['total']) }}</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                @if ($co['rows'])
                    @include('livewire.reports.partials.chart-card', ['id' => 'lc-cohort', 'spec' => $co['chart'],
                        'title' => $L('cohort.chart'), 'sub' => $L('cohort.chart_sub')])
                @endif
                @break
        @endswitch
    @endif
</div>
