{{-- Thẻ KPI báo cáo chuyển đổi Lead: $kpis = [[key, icon, giá trị, gợi ý, [so sánh, class] | null], ...]; $prevLabel = kỳ so sánh.
     Bố cục dọc: icon + tên → số lớn → chú thích neo đáy thẻ (các thẻ cùng hàng cao bằng nhau, chú thích thẳng hàng). --}}
<div class="rp-kpis rp-kpis--fit" style="--n: {{ count($kpis) }}">
    @foreach ($kpis as [$key, $icon, $value, $hint, $change])
        <div class="rp-stat">
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
