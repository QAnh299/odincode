@php
    use App\Livewire\Reports\LeadConversion;
    use App\Services\LeadConversionReport;

    $tab = $this->activeTab();
    $s = $this->summary;
    $prev = $this->previous;
    $seesAll = $this->seesAll;

    $L = fn (string $key, array $params = []) => __('reports.lead_conversion.'.$key, $params);
    $num = fn ($v) => number_format((float) $v, 0, ',', '.');
    $dec = fn ($v) => number_format((float) $v, 1, ',', '.');
    $pct = fn ($v) => $v === null ? '-' : $dec($v).'%';

    $dur = fn ($h) => $h === null ? '-' : ($h < 48 ? $L('hours', ['value' => $dec($h)]) : $L('days', ['value' => $dec($h / 24)]));
    $days = fn ($d) => $d === null ? '-' : $dur($d * 24);

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

    $hasData = $s['leads'] > 0;
@endphp

<div class="vc rp rp--lc">
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/lead.css', 'resources/css/report.css', 'resources/js/charts.js'])
    @endassets

    <header class="vc-head">
        <div>
            <h1 class="visually-hidden">{{ $L('title') }}</h1>
            <p class="vc-head__sub"><strong>{{ $this->scopeLabel() }}</strong></p>
        </div>
        <div class="rp-head__actions">
            <span class="vc-results__loading" wire:loading.delay wire:target.except="export">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('reports.loading') }}
            </span>
            @if ($this->canExport)
                <button type="button" class="vc-btn vc-btn--primary" wire:click="openExport">
                    <x-icon name="file" />
                    {{ $L('export.button') }}
                </button>
            @endif
        </div>
    </header>

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
            @case('overview')
                @php
                    $o = $this->overview;
                    $ps = $prev['summary'] ?? null;
                    $kpis = [
                        [$seesAll ? 'leads' : 'assigned', 'layers', $num($s['leads']),
                            $seesAll ? $L('kpi.leads_hint', ['count' => $num($s['new'])]) : $L('kpi.assigned_hint', ['count' => $num($s['open'])]),
                            $delta($s['leads'], $ps['leads'] ?? null), ['kind' => 'leads']],
                        $seesAll
                            ? ['conversion', 'share', $pct($s['conversion_rate']), $L('kpi.conversion_hint', ['count' => $num($s['opportunities'])]),
                                $delta($s['conversion_rate'], $ps['conversion_rate'] ?? null, 1, true), ['kind' => 'leads', 'set' => 'opportunities']]
                            : ['contact', 'phone', $pct($s['contact_rate']), $L('kpi.contact_hint', ['count' => $num($s['contacted'])]),
                                $delta($s['contact_rate'], $ps['contact_rate'] ?? null, 1, true), ['kind' => 'leads', 'set' => 'contacted']],
                        ['win', 'check-circle', $pct($s['win_rate']),
                            $L('kpi.win_hint', ['count' => $num($s['won']), 'rate' => $pct($s['close_rate'])]),
                            $delta($s['win_rate'], $ps['win_rate'] ?? null, 1, true), ['kind' => 'leads', 'set' => 'won']],
                        ['students', 'user-check', $num($s['students']), $L('kpi.students_hint', ['rate' => $pct($s['student_rate'])]),
                            $delta($s['students'], $ps['students'] ?? null), ['kind' => 'leads', 'set' => 'students']],
                    ];
                @endphp

                @include('livewire.reports.partials.lc-kpis', ['kpis' => $kpis, 'prevLabel' => $prev['label'] ?? null])

                @php
                    $fTop = max(array_column($o['funnel'], 'value') ?: [0]);
                    $fRows = count($o['funnel']);
                    $fWidth = fn ($v) => $fTop ? max(0.16, $v / $fTop) * 400 : 0;
                @endphp
                <div class="rp-grid rp-grid--panels">
                    <x-report-panel id="lc-funnel" :title="$L('overview.funnel')" :sub="$L('overview.funnel_sub')" :filter="$this->panelFilter('funnel')">
                        @if (! $fTop)
                            <p class="rp-empty rp-empty--inline">{{ __('reports.no_data') }}</p>
                        @else
                            <svg class="rp-funnel" viewBox="0 0 720 {{ $fRows * 58 }}" role="img" aria-label="{{ $L('overview.funnel') }}">
                                @foreach ($o['funnel'] as $i => $step)
                                    @php
                                        $top = $fWidth($step['value']);
                                        $bottom = isset($o['funnel'][$i + 1]) ? $fWidth($o['funnel'][$i + 1]['value']) : $top * 0.8;
                                        $y = $i * 58;
                                    @endphp
                                    <g class="dr-svg" role="button" tabindex="0" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget($step['drill'])) }})"
                                        x-on:keydown.enter="$dispatch('report-drill', {{ Js::from($this->drillTarget($step['drill'])) }})">
                                    <title>{{ __('reports.drill.hint') }}</title>
                                    <polygon points="{{ 200 - $top / 2 }},{{ $y }} {{ 200 + $top / 2 }},{{ $y }} {{ 200 + $bottom / 2 }},{{ $y + 55 }} {{ 200 - $bottom / 2 }},{{ $y + 55 }}"
                                        fill="{{ LeadConversion::PALETTE[$i % count(LeadConversion::PALETTE)] }}" />
                                    <text class="rp-funnel__value" x="200" y="{{ $y + 34 }}">{{ $num($step['value']) }}</text>
                                    </g>
                                    <text class="rp-funnel__name" x="425" y="{{ $y + 24 }}">{{ $step['name'] }}</text>
                                    <text class="rp-funnel__rate" x="425" y="{{ $y + 43 }}">
                                        {{ $step['rate'] === null ? $L('overview.funnel_start') : $L('overview.funnel_rate', ['rate' => $pct($step['rate']), 'total' => $pct($step['overall'])]) }}
                                    </text>
                                @endforeach
                            </svg>
                        @endif
                    </x-report-panel>

                    <x-report-panel id="lc-share" :title="$L('overview.share')" :sub="$L('overview.share_sub')" :filter="$this->panelFilter('share')"
                        :spec="$this->drillTargets($o['charts']['share'])" :height="340" />
                    <x-report-panel id="lc-trend-count" :title="$L('overview.trend_count', ['period' => $L('period.'.$o['trend_period'])])" :sub="$L('overview.trend_count_sub')"
                        :filter="$this->panelFilter('trend_count')" :spec="$this->drillTargets($o['charts']['trend_count'])" />
                    <x-report-panel id="lc-trend-rate" :title="$L('overview.trend_rate', ['period' => $L('period.'.$this->period)])" :sub="$L('overview.trend_rate_sub')"
                        :filter="$this->panelFilter('trend_rate')" :spec="$this->drillTargets($o['charts']['trend_rate'])" />
                    <x-report-panel id="lc-outcomes" :title="$L('overview.outcomes')" :sub="$L('overview.outcomes_sub')" :filter="$this->panelFilter('outcomes')"
                        :spec="$this->drillTargets($o['charts']['outcomes'])" :height="300" wide />
                </div>
                @break

            @case('sources')
                @php $d = $this->bySource; @endphp

                <x-report-panel id="lc-sources-table" :title="$L('sources.table')" :sub="$L('sources.table_sub')">
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
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($d['rows'] as $row)
                                    <tr wire:key="src-{{ md5($row['name']) }}">
                                        <th scope="row">{{ $row['name'] }}</th>
                                        <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'leads', 'source' => $row['name']])) }})">{{ $num($row['leads']) }}</button></td>
                                        @if ($seesAll)
                                            <td class="rp-num">
                                                <button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'leads', 'set' => 'opportunities', 'source' => $row['name']])) }})">{{ $pct($row['conversion_rate']) }}</button>
                                                <span class="rp-sub">{{ $num($row['opportunities']) }}</span>
                                            </td>
                                        @endif
                                        <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'leads', 'set' => 'contacted', 'source' => $row['name']])) }})">{{ $pct($row['contact_rate']) }}</button></td>
                                        <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'leads', 'set' => 'won', 'source' => $row['name']])) }})">{{ $num($row['won']) }}</button></td>
                                        <td class="rp-num"><span class="rp-bar" style="--w: {{ min(100, $row['win_rate'] ?? 0) }}%"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'leads', 'set' => 'won', 'source' => $row['name']])) }})">{{ $pct($row['win_rate']) }}</button></span></td>
                                        <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'leads', 'set' => 'closed', 'source' => $row['name']])) }})">{{ $pct($row['close_rate']) }}</button></td>
                                        <td class="rp-num">{{ $days($row['close_median']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            @php $t = $d['total']; @endphp
                            <tfoot>
                                <tr>
                                    <th scope="row">{{ $L('total') }}</th>
                                    <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'leads'])) }})">{{ $num($t['leads']) }}</button></td>
                                    @if ($seesAll)
                                        <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'leads', 'set' => 'opportunities'])) }})">{{ $pct($t['conversion_rate']) }}</button></td>
                                    @endif
                                    <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'leads', 'set' => 'contacted'])) }})">{{ $pct($t['contact_rate']) }}</button></td>
                                    <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'leads', 'set' => 'won'])) }})">{{ $num($t['won']) }}</button></td>
                                    <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'leads', 'set' => 'won'])) }})">{{ $pct($t['win_rate']) }}</button></td>
                                    <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'leads', 'set' => 'closed'])) }})">{{ $pct($t['close_rate']) }}</button></td>
                                    <td class="rp-num">{{ $days($t['close_median']) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </x-report-panel>

                <div class="rp-grid rp-grid--panels">
                    @foreach (['scatter', 'rates', 'methods'] as $key)
                        <x-report-panel :id="'lc-src-'.$key" :title="$L('sources.chart.'.$key)" :sub="$L('sources.chart.'.$key.'_sub')"
                            :filter="$this->panelFilter($key)" :spec="$this->drillTargets($d['charts'][$key])" :height="320" :wide="$key === 'methods'" />
                    @endforeach
                </div>
                @break

            @case('velocity')
                @php
                    $v = $this->velocity;
                    $kpis = [
                        ['response', 'clock', $dur($s['response_median']), $L('kpi.response_hint', ['value' => $dur($s['response_avg'])]), null, ['kind' => 'leads', 'set' => 'contacted']],
                        ['within24', 'check', $pct($v['within24']), $L('kpi.within24_hint', ['count' => $num($v['assigned'])]), null, ['kind' => 'leads', 'set' => 'within24']],
                        ['close_time', 'hourglass', $days($s['close_median']), $L('kpi.close_time_hint', ['value' => $days($s['close_avg'])]), null, ['kind' => 'leads', 'set' => 'won']],
                        ['open', 'layers', $num($v['open']), $L('kpi.open_hint', ['count' => $num($v['untouched'])]), null, ['kind' => 'leads', 'set' => 'open']],
                        ['stuck', 'alert', $num($v['stuck_count']), $L('kpi.stuck_hint'), null, ['kind' => 'leads', 'set' => 'stuck']],
                    ];
                @endphp

                @include('livewire.reports.partials.lc-kpis', ['kpis' => $kpis, 'prevLabel' => null])

                <x-report-panel id="lc-velocity-steps" :title="$L('velocity.steps')" :sub="$L('velocity.steps_sub')">
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
                                        <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'leads', 'step' => $step['key']])) }})">{{ $num($step['count']) }}</button></td>
                                        <td class="rp-num"><strong>{{ $dur($step['median']) }}</strong></td>
                                        <td class="rp-num">{{ $dur($step['avg']) }}</td>
                                        <td class="rp-num">{{ $dur($step['p90']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-report-panel>

                <div class="rp-grid rp-grid--panels">
                    @foreach (['response_count', 'response_rate', 'close'] as $key)
                        <x-report-panel :id="'lc-vel-'.$key" :title="$L('velocity.chart.'.$key)" :sub="$L('velocity.chart.'.$key.'_sub')"
                            :filter="$this->panelFilter($key)" :spec="$this->drillTargets($v['charts'][$key])" :wide="$key === 'close'" />
                    @endforeach
                </div>

                @php
                    $stuckSub = $L('velocity.stuck_sub', collect(LeadConversionReport::STUCK_DAYS)->mapWithKeys(fn ($d, $id) => [strtolower($id) => $d])->all())
                        .($v['stuck_count'] > count($v['stuck']) ? ' '.$L('velocity.stuck_more', ['shown' => count($v['stuck']), 'count' => $v['stuck_count']]) : '');
                @endphp
                <x-report-panel id="lc-stuck" :title="$L('velocity.stuck')" :sub="$stuckSub">
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
                                            <td>{{ $row['employee_name'] ?? '-' }}</td>
                                            <td class="rp-num rp-cmp--down">
                                                {{ $L('days', ['value' => $dec($row['days'])]) }}
                                                <span class="rp-sub">{{ $L('velocity.limit', ['days' => $row['limit']]) }}</span>
                                            </td>
                                            <td class="rp-num">{{ $num($row['contacts']) }}</td>
                                            <td class="rp-num">{{ $row['last_contact'] ? date('d/m/Y H:i', $row['last_contact']) : '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-report-panel>
                @break

            @case('care')
                @php
                    $c = $this->care;
                    $kpis = [
                        ['contacts', 'phone', $num($c['total']), $L('kpi.contacts_hint', ['rate' => $pct($c['success_rate'])]), null, ['kind' => 'contacts']],
                        ['unreachable', 'alert', $pct($c['unreachable_rate']), $L('kpi.unreachable_hint'), null, ['kind' => 'contacts', 'result' => 'Unreachable']],
                        ['per_won', 'check-circle', $c['per_won'] === null ? '-' : $dec($c['per_won']), $L('kpi.per_won_hint'), null, ['kind' => 'leads', 'set' => 'won']],
                        ['per_lost', 'x', $c['per_lost'] === null ? '-' : $dec($c['per_lost']), $L('kpi.per_lost_hint'), null, ['kind' => 'leads', 'set' => 'failed']],
                        ['appointments', 'calendar', $pct($c['appointment_rate']), $L('kpi.appointments_hint'), null, ['kind' => 'appointments', 'status' => 'done']],
                    ];
                    $weekdays = range(1, 7);
                @endphp

                @include('livewire.reports.partials.lc-kpis', ['kpis' => $kpis, 'prevLabel' => null])

                <div class="rp-grid rp-grid--panels">
                    <x-report-panel id="lc-care-activities" :title="$L('care.activities')" :sub="$L('care.activities_sub')"
                        :filter="$this->panelFilter('activities')" :spec="$this->drillTargets($c['charts']['activities'])" />
                    <x-report-panel id="lc-care-touches" :title="$L('care.touches')" :sub="$L('care.touches_sub')"
                        :filter="$this->panelFilter('touches')" :spec="$this->drillTargets($c['charts']['touches'])" />
                </div>

                <div class="rp-grid rp-grid--panels">
                    <x-report-panel id="lc-care-activity-table" :title="$L('care.activity_table')" :sub="$L('care.activity_table_sub')">
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
                                            <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'contacts', 'activity' => $a['id']])) }})">{{ $num($a['total']) }}</button></td>
                                            @foreach (\App\Models\CareResult::RESULTS as $r)
                                                <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'contacts', 'activity' => $a['id'], 'result' => $r])) }})">{{ $pct($a['rate'][$r]) }}<span class="rp-sub">{{ $num($a[$r]) }}</span></button></td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="rp-empty">{{ __('reports.no_data') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-report-panel>

                    <x-report-panel id="lc-care-appointments" :title="$L('care.appointments')" :sub="$L('care.appointments_sub')">
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
                                            <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'appointments', 'type' => $type])) }})">{{ $num($t['total']) }}</button></td>
                                            <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'appointments', 'type' => $type, 'status' => 'Success'])) }})">{{ $num($t['Success']) }}</button></td>
                                            <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'appointments', 'type' => $type, 'status' => 'Failed'])) }})">{{ $num($t['Failed']) }}</button></td>
                                            <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'appointments', 'type' => $type, 'status' => 'Scheduled'])) }})">{{ $num($t['Scheduled']) }}</button></td>
                                            <td class="rp-num"><strong><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'appointments', 'type' => $type, 'status' => 'done'])) }})">{{ $pct($t['rate']) }}</button></strong></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="rp-empty">{{ __('reports.no_data') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-report-panel>
                </div>

                <x-report-panel id="lc-care-heatmap" :title="$L('care.heatmap')" :sub="$L('care.heatmap_sub')">
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
                                                    <td class="dr-card" style="--a: {{ round(8 + 32 * $cell['total'] / max(1, $c['heat_max'])) }}%"
                                                        x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'contacts', 'day' => $day, 'hour' => $hour])) }})"
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
                </x-report-panel>
                @break

            @case('cohort')
                @php $co = $this->cohort; @endphp

                @php
                    $cohortOptions = [];
                    foreach (LeadConversion::COHORT_METRICS as $metric) {
                        if ($metric !== 'converted' || $seesAll) {
                            $cohortOptions[$metric] = $L('cohort.metric_'.$metric);
                        }
                    }
                @endphp
                <x-report-panel id="lc-cohort-table" :title="$L('cohort.title_'.$co['metric'])" :sub="$L('cohort.sub')"
                    :filter="['model' => 'cohortMetric', 'label' => $L('cohort.metric'), 'options' => $cohortOptions, 'value' => $co['metric']]">
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
                                            <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'leads', 'cohort' => $row['month']])) }})">{{ $num($row['leads']) }}</button></td>
                                            @foreach ($row['cells'] as $m => $cell)
                                                @if ($cell === null)
                                                    <td class="is-empty"></td>
                                                @else
                                                    <td class="rp-num rp-cohort__cell" style="--a: {{ round(min(100, $cell)) }}%"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'leads', 'set' => 'cohort', 'cohort' => $row['month'], 'offset' => $m])) }})">{{ $pct($cell) }}</button></td>
                                                @endif
                                            @endforeach
                                            <td class="rp-num"><strong><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'leads', 'set' => 'cohort', 'cohort' => $row['month']])) }})">{{ $pct($row['total']) }}</button></strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-report-panel>

                @if ($co['rows'])
                    <x-report-panel id="lc-cohort" :title="$L('cohort.chart')" :sub="$L('cohort.chart_sub')" :spec="$this->drillTargets($co['chart'])" />
                @endif
                @break
        @endswitch
    @endif

    @include('livewire.reports.partials.report-drill')

    @if ($this->canExport)
        @include('livewire.reports.partials.lc-export')
    @endif
</div>
