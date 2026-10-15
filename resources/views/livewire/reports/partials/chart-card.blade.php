{{-- Thẻ biểu đồ: $id (khoá duy nhất trên trang), $spec (cấu hình odinChart), $title, $sub, $height (px, mặc định 300), $wide --}}
<section class="vc-card rp-card {{ ($wide ?? false) ? 'rp-card--wide' : '' }}">
    <h3 class="rp-card__title">{{ $title }}</h3>
    @if (! empty($sub))
        <p class="rp-card__sub">{{ $sub }}</p>
    @endif
    @if (empty($spec['series']) || ! collect($spec['series'])->contains(fn ($s) => collect($s['data'])->filter()->isNotEmpty()))
        <p class="rp-empty rp-empty--inline">{{ __('reports.no_data') }}</p>
    @else
        <div class="rp-chart" style="height: {{ $height ?? 300 }}px"
            wire:key="{{ $id }}-{{ md5(json_encode($spec)) }}" wire:ignore
            x-data="odinChart(@js($spec))">
            <canvas x-ref="canvas" role="img" aria-label="{{ $title }}"></canvas>
        </div>
    @endif
</section>
