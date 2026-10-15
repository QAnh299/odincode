{{-- Thẻ KPI báo cáo chuyển đổi Lead: $kpis = [[key, icon, giá trị, gợi ý, [so sánh, class] | null], ...]; $prevLabel = kỳ so sánh --}}
<div class="rp-kpis">
    @foreach ($kpis as [$key, $icon, $value, $hint, $change])
        <div class="vc-summary__item rp-kpi">
            <span class="vc-summary__icon"><x-icon :name="$icon" /></span>
            <span class="vc-summary__text">
                <span class="vc-summary__label">{{ __('reports.lead_conversion.kpi.'.$key) }}</span>
                <span class="vc-summary__value">{{ $value }}</span>
                <span class="rp-kpi__hint" title="{{ $hint }}">{{ $hint }}</span>
                @if ($change)
                    <span class="rp-delta {{ $change[1] ? 'rp-delta--'.$change[1] : '' }}"
                        title="{{ __('reports.lead_conversion.delta_vs', ['period' => $prevLabel]) }}">
                        {{ $change[0] }} {{ __('reports.lead_conversion.delta_vs', ['period' => $prevLabel]) }}
                    </span>
                @endif
            </span>
        </div>
    @endforeach
</div>
