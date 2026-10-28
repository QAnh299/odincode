{{-- Thẻ KPI báo cáo doanh thu & thanh toán (cùng giao diện lc-kpis): $kpis = [[key, icon, giá trị, gợi ý, [so sánh, class] | null], ...]; $prevLabel = kỳ so sánh.
     Thẻ có liên kết (Revenue::kpiUrl) bấm được để mở danh sách hóa đơn / đơn hàng tương ứng. --}}
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
