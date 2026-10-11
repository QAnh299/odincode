@php
    // Icon nét (stroke) dùng trong trang
    $icons = [
        'lock'     => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'key'      => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/>',
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'eye'      => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'  => '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24M1 1l22 22"/>',
        'check'    => '<path d="M20 6 9 17l-5-5"/>',
        'dot'      => '<circle cx="12" cy="12" r="3"/>',
        'arrow'    => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'chevron'  => '<path d="m9 18 6-6-6-6"/>',
        'bulb'     => '<path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.74V17h8v-2.26A7 7 0 0 0 12 2z"/>',
        'alert'    => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>',
    ];
    $icon = fn (string $name, string $class = 'pw-icon') => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none"'
        .' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        .$icons[$name].'</svg>';

    // Ô nhập mật khẩu: [thuộc tính, nhãn, autocomplete, placeholder]
    $fields = [
        ['current_password', 'current', 'current-password', 'current_placeholder'],
        ['password', 'new', 'new-password', 'new_placeholder'],
        ['password_confirmation', 'confirm', 'new-password', 'confirm_placeholder'],
    ];

    // Đang dùng mật khẩu tạm: ẩn ô mật khẩu hiện tại
    if ($forced) {
        array_shift($fields);
    }
@endphp

<div class="pw">
    {{-- CSS riêng của trang: resources/css/password.css (chỉ nạp ở trang này) --}}
    @assets
        @vite('resources/css/password.css')
    @endassets

    @unless ($forced)
        <a href="{{ route('profile') }}" class="pw-back">{!! $icon('arrow') !!}{{ __('change_password.back') }}</a>
    @endunless

    <div class="pw-grid">
        {{-- ── Form đổi mật khẩu ────────────────────────────────────── --}}
        <section class="pw-card">
            <header class="pw-card__head">
                <span class="pw-card__icon">{!! $icon('key') !!}</span>
                <div>
                    <h1 class="pw-card__title">{{ __('change_password.title') }}</h1>
                    <p class="pw-card__sub">{{ __($forced ? 'change_password.forced_subtitle' : 'change_password.subtitle') }}</p>
                </div>
            </header>

            @if ($forced)
                <div class="pw-alert pw-alert--warning" role="alert">
                    <span class="pw-alert__icon">{!! $icon('alert') !!}</span>
                    <div>
                        <strong>{{ __('change_password.forced_title') }}</strong>
                        <p>{{ __('change_password.forced_message') }}</p>
                    </div>
                </div>
            @endif

            @if ($changed)
                <div class="pw-alert pw-alert--success" role="status">
                    <span class="pw-alert__icon">{!! $icon('check') !!}</span>
                    <div>
                        <strong>{{ __('change_password.success') }}</strong>
                        <p>{{ __('change_password.success_sub') }}</p>
                    </div>
                </div>
            @endif

            <form wire:submit="save" novalidate
                x-data="{
                    get pw() { return $wire.password ?? '' },
                    get confirm() { return $wire.password_confirmation ?? '' },
                    get rules() {
                        return {
                            length: this.pw.length >= 8,
                            letter: /\p{L}/u.test(this.pw),
                            number: /\d/.test(this.pw),
                            mixed: /\p{Ll}/u.test(this.pw) && /\p{Lu}/u.test(this.pw),
                            special: /[^\p{L}\d\s]/u.test(this.pw),
                        }
                    },
                    get score() {
                        if (! this.pw) return 0;
                        const r = this.rules;
                        let s = [r.length, r.letter && r.number, r.mixed, r.special].filter(Boolean).length;
                        if (this.pw.length >= 12) s++;
                        return Math.min(4, Math.max(1, s));
                    },
                    get matches() { return this.confirm !== '' && this.confirm === this.pw },
                }">

                @foreach ($fields as [$field, $label, $autocomplete, $placeholder])
                    <div class="pw-field" x-data="{ show: false }" wire:key="field-{{ $field }}">
                        <label for="pw-{{ $field }}" class="pw-field__label">{{ __('change_password.'.$label) }}</label>
                        <div class="pw-input @error($field) is-invalid @enderror">
                            {!! $icon('lock') !!}
                            <input :type="show ? 'text' : 'password'" type="password" id="pw-{{ $field }}"
                                wire:model="{{ $field }}" autocomplete="{{ $autocomplete }}"
                                placeholder="{{ __('change_password.'.$placeholder) }}"
                                @if ($loop->first) autofocus @endif
                                @error($field) aria-invalid="true" aria-describedby="pw-{{ $field }}-error" @enderror>

                            @if ($field === 'password_confirmation')
                                <span class="pw-input__match" x-show="matches" x-cloak
                                    title="{{ __('change_password.matched') }}">{!! $icon('check') !!}</span>
                            @endif

                            <button type="button" class="pw-input__toggle" @click="show = !show"
                                :aria-label="show ? @js(__('auth.hide_password')) : @js(__('auth.show_password'))">
                                <span x-show="!show">{!! $icon('eye-off') !!}</span>
                                <span x-show="show" x-cloak>{!! $icon('eye') !!}</span>
                            </button>
                        </div>
                        @error($field)
                            <div class="pw-field__error" id="pw-{{ $field }}-error">{!! $icon('alert') !!}{{ $message }}</div>
                        @enderror

                        {{-- Độ mạnh + yêu cầu cho mật khẩu mới --}}
                        @if ($field === 'password')
                            <div class="pw-strength" :data-score="score" x-show="pw" x-cloak>
                                <div class="pw-strength__bars" aria-hidden="true">
                                    <span></span><span></span><span></span><span></span>
                                </div>
                                <span class="pw-strength__label"
                                    x-text="[
                                        '',
                                        @js(__('change_password.strength.weak')),
                                        @js(__('change_password.strength.fair')),
                                        @js(__('change_password.strength.good')),
                                        @js(__('change_password.strength.strong')),
                                    ][score]"></span>
                            </div>

                            <ul class="pw-rules">
                                @foreach (['length', 'letter', 'number', 'mixed', 'special'] as $rule)
                                    <li :class="{ 'is-ok': rules.{{ $rule }} }"
                                        class="{{ in_array($rule, ['mixed', 'special']) ? 'is-optional' : '' }}">
                                        <span class="pw-rules__mark">
                                            <span x-show="rules.{{ $rule }}" x-cloak>{!! $icon('check') !!}</span>
                                            <span x-show="!rules.{{ $rule }}">{!! $icon('dot') !!}</span>
                                        </span>
                                        {{ __('change_password.rules.'.$rule) }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach

                <div class="pw-actions">
                    @unless ($forced)
                        <a href="{{ route('profile') }}" class="pw-btn pw-btn--light">{{ __('change_password.cancel') }}</a>
                    @endunless
                    <button type="submit" class="pw-btn pw-btn--primary" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{!! $icon('shield') !!}</span>
                        <span wire:loading wire:target="save" class="spinner-border spinner-border-sm"
                            aria-hidden="true"></span>
                        {{ __('change_password.submit') }}
                    </button>
                </div>
            </form>
        </section>

        {{-- ── Gợi ý bảo mật ────────────────────────────────────────── --}}
        <aside class="pw-card pw-tips">
            <span class="pw-tips__icon">{!! $icon('bulb') !!}</span>
            <h2 class="pw-tips__title">{{ __('change_password.tips_title') }}</h2>
            <ul class="pw-tips__list">
                @foreach (__('change_password.tips') as $tip)
                    <li>{!! $icon('chevron') !!}<span>{{ $tip }}</span></li>
                @endforeach
            </ul>
            <p class="pw-tips__note">{!! $icon('alert') !!}{{ __('change_password.api_note') }}</p>
        </aside>
    </div>
</div>
