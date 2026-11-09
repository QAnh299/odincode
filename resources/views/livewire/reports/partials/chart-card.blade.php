{{-- Thẻ biểu đồ: $id (khoá duy nhất trên trang), $spec (cấu hình odinChart), $title, $sub, $height (px, mặc định 300), $wide.
     $spec['htmlLegend'] = true: chú thích vẽ bằng HTML ngay dưới tiêu đề (canh trái), không dùng chú thích của Chart.js. --}}
@php
    $hasData = ! empty($spec['series']) && collect($spec['series'])->contains(fn ($s) => collect($s['data'])->filter()->isNotEmpty());
    $htmlLegend = $hasData && ! empty($spec['htmlLegend']) && count($spec['series']) > 1;
@endphp
<section class="vc-card rp-card {{ ($wide ?? false) ? 'rp-card--wide' : '' }}">
    <h3 class="rp-card__title">{{ $title }}</h3>
    @if (! empty($sub))
        <p class="rp-card__sub">{{ $sub }}</p>
    @endif
    @if (! $hasData)
        <p class="rp-empty rp-empty--inline">{{ __('reports.no_data') }}</p>
    @else
        @if ($htmlLegend)
            <ul class="rp-legend-list">
                @foreach ($spec['series'] as $s)
                    <li>
                        <span class="rp-legend-list__{{ ($s['type'] ?? null) === 'line' && ($spec['type'] ?? '') !== 'line' ? 'line' : 'box' }}"
                            style="--c: {{ $s['color'] ?? '#9ca3af' }}"></span>{{ $s['label'] }}
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="rp-chart" style="height: {{ $height ?? 300 }}px"
            wire:key="{{ $id }}-{{ md5(json_encode($spec)) }}" wire:ignore
            x-data="odinChart(@js($spec))">
            <canvas x-ref="canvas" role="img" aria-label="{{ $title }}"></canvas>
        </div>
    @endif
</section>
