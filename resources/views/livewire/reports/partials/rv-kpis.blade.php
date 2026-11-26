<div class="rp-kpis rp-kpis--fit" style="--n: {{ count($kpis) }}">
    @foreach ($kpis as [$key, $icon, $value, $hint, $change])
        @php $url = $this->kpiUrl($key); @endphp
        <{{ $url ? 'a' : 'div' }} @class(['rp-stat', 'rv-stat-link' => $url]) @if ($url) href="{{ $url }}" title="{{ __('reports.revenue.kpi_open') }}" @endif>
            <div class="rp-stat__head">
                <span class="rp-stat__icon"><x-icon :name="$icon" /></span>
                <span>{{ __('reports.revenue.kpi.'.$key) }}</span>
            </div>
            <div class="rp-stat__value">{{ $value }}</div>
            @if ($change)
                <div class="rp-delta {{ $change[1] ? 'rp-delta--'.$change[1] : '' }}">
                    {{ $change[0] }} {{ __('reports.revenue.delta_vs', ['period' => $prevLabel]) }}
                </div>
            @endif
            <div class="rp-stat__hint">{{ $hint }}</div>
        </{{ $url ? 'a' : 'div' }}>
    @endforeach
</div>
