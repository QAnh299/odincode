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
    $languages = ['vi' => __('general.vietnamese'), 'en' => __('general.english')];
@endphp

{{-- Popup Cài đặt: ngôn ngữ và màu giao diện (xử lý trong resources/js/theme.js).
     Mở bằng: data-bs-toggle="modal" data-bs-target="#settingsModal" --}}
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="h5 fw-bold mb-0" id="settingsModalLabel">{{ __('topbar.settings') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('auth.cancel') }}"></button>
            </div>

            <div class="modal-body">
                <section class="odin-settings-modal__section" role="radiogroup" aria-labelledby="settingsLanguage">
                    <div class="odin-theme-menu__title" id="settingsLanguage">{{ __('topbar.language') }}</div>
                    <div class="odin-settings-modal__languages">
                        @foreach ($languages as $code => $label)
                            <label class="odin-settings-modal__language">
                                <input type="radio" name="odin-language" value="{{ $code }}" @checked(app()->getLocale() === $code)
                                    data-odin-language="{{ route('lang.switch', $code) }}">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </section>

                <section class="odin-settings-modal__section">
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
                </section>
            </div>
        </div>
    </div>
</div>
