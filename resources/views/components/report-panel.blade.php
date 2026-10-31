@props(['id', 'title', 'sub' => null, 'filter' => null, 'spec' => null, 'height' => 300, 'wide' => false])

{{-- Khung báo cáo: thanh tiêu đề + nút thu gọn, ô lọc riêng ($filter: model, label, options, value), rồi biểu đồ ($spec, odinChart) hoặc nội dung bên trong.
     $filter['model'] là thuộc tính Livewire của trang chứa khung. --}}
<section {{ $attributes->class(['rp-panel', 'rp-card--wide' => $wide]) }} wire:key="{{ $id }}" x-data="{ open: true }">
    <header class="rp-panel__head">
        <h3 class="rp-panel__title">{{ $title }}</h3>
        <button type="button" class="rp-panel__toggle" x-on:click="open = ! open" x-bind:class="{ 'is-closed': ! open }"
            x-bind:aria-expanded="open" aria-label="{{ $title }}">
            <x-icon name="chevron" />
        </button>
    </header>

    <div class="rp-panel__body" x-show="open">
        @if ($filter)
            <label class="rp-panel__filter">
                <span>{{ $filter['label'] }}</span>
                <select wire:model.live="{{ $filter['model'] }}" class="vc-control">
                    @foreach ($filter['options'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) $value === (string) $filter['value'])>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        @if ($sub)
            <p class="rp-card__sub">{{ $sub }}</p>
        @endif

        @if ($spec === null)
            {{ $slot }}
        @elseif (! collect($spec['series'])->contains(fn ($s) => collect($s['data'])->filter()->isNotEmpty()))
            <p class="rp-empty rp-empty--inline">{{ __('reports.no_data') }}</p>
        @else
            <div class="rp-chart" style="height: {{ $height }}px" wire:key="{{ $id }}-{{ md5(json_encode($spec)) }}" wire:ignore
                x-data="odinChart(@js($spec))">
                <canvas x-ref="canvas" role="img" aria-label="{{ $title }}"></canvas>
            </div>
        @endif
    </div>
</section>
