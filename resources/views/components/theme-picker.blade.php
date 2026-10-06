@php
    // Màu có sẵn (màu đầu tiên là màu mặc định của ODIN, khớp với resources/js/theme.js)
    $presets = [
        'green'  => '#005e12',
        'teal'   => '#0f766e',
        'blue'   => '#1d4ed8',
        'indigo' => '#4338ca',
        'purple' => '#7e22ce',
        'pink'   => '#be185d',
        'red'    => '#b91c1c',
        'orange' => '#c2410c',
        'brown'  => '#7c4a1e',
        'slate'  => '#334155',
    ];
@endphp

{{-- Chọn màu giao diện, xử lý trong resources/js/theme.js --}}
<div class="dropdown">
    <button class="odin-icon-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
        aria-expanded="false" aria-label="{{ __('theme.title') }}" title="{{ __('theme.title') }}">
        <span class="odin-theme-dot" aria-hidden="true"></span>
    </button>

    <div class="dropdown-menu dropdown-menu-end odin-theme-menu">
        <div class="odin-theme-menu__title">{{ __('theme.title') }}</div>

        <div class="odin-theme-swatches">
            @foreach ($presets as $name => $color)
                <button type="button" class="odin-theme-swatch" style="--swatch: {{ $color }}"
                    data-odin-theme-color="{{ $color }}" aria-pressed="false"
                    title="{{ __("theme.$name") }}" aria-label="{{ __("theme.$name") }}"></button>
            @endforeach
        </div>

        <label class="odin-theme-custom">
            <input type="color" value="#005e12" data-odin-theme-custom>
            <span>{{ __('theme.custom') }}</span>
        </label>

        <button type="button" class="btn btn-sm btn-light w-100 mt-2" data-odin-theme-reset>
            {{ __('theme.reset') }}
        </button>
    </div>
</div>
