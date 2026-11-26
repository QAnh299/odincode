<div class="rp-kpis rp-kpis--fit" style="--n: {{ count($kpis) }}">
    @foreach ($kpis as $kpi)
        @php [$key, $icon, $value, $hint, $change, $drill] = $kpi + [5 => null]; @endphp
        <div @class(['rp-stat', 'dr-card' => $drill])
            @if ($drill) role="button" tabindex="0" title="{{ __('reports.drill.hint') }}"
                x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget($drill)) }})" x-on:keydown.enter="$dispatch('report-drill', {{ Js::from($this->drillTarget($drill)) }})" @endif>
            <div class="rp-stat__head">
                <span class="rp-stat__icon"><x-icon :name="$icon" /></span>
                <span>{{ __('reports.lead_conversion.kpi.'.$key) }}</span>
            </div>
            <div class="rp-stat__value">{{ $value }}</div>
            @if ($change)
                <div class="rp-delta {{ $change[1] ? 'rp-delta--'.$change[1] : '' }}">
                    {{ $change[0] }} {{ __('reports.lead_conversion.delta_vs', ['period' => $prevLabel]) }}
                </div>
            @endif
            <div class="rp-stat__hint">{{ $hint }}</div>
        </div>
    @endforeach
</div>
