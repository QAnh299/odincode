<div class="auth-page">
    @foreach (['tl', 'tr', 'bl', 'br'] as $corner)
        <img src="{{ asset('images/login-corner.svg') }}" alt="" class="auth-corner auth-corner--{{ $corner }}">
    @endforeach

    <div class="auth-lang">
        <x-language-switcher />
    </div>

    <main class="auth-main">
        <div class="auth-shell">
            {{-- ── Cột trái: thương hiệu ────────────────────────────── --}}
            <aside class="auth-brand">
                <div class="brand-center">
                    <img src="{{ asset('images/logoodin-sidebar.png') }}" alt="ODIN Language Center" class="brand-logo">
                    <span class="brand-divider" aria-hidden="true"></span>

                    <h2 class="brand-title">{{ __('auth.brand_title') }}</h2>
                    @php
                        // Icon nét (24×24) cho từng ý chính
                        $brandPoints = [
                            'brand_point_leads' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
                            'brand_point_classes' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/>',
                            'brand_point_care' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/>',
                        ];
                    @endphp
                    <ul class="brand-points">
                        @foreach ($brandPoints as $point => $icon)
                            <li>
                                <span class="brand-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg>
                                </span>
                                <span>{{ __('auth.'.$point) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="brand-copy">© {{ date('Y') }} ODIN Language Center. {{ __('auth.rights') }}</p>
            </aside>

            {{-- ── Cột phải: form đăng nhập ─────────────────────────── --}}
            <section class="auth-panel">
                <img src="{{ asset('images/logoodin-trim.png') }}" alt="ODIN Language Center" class="panel-logo">

                <div class="auth-form">
                    @if ($mode === 'forgot')
                        <h1 class="auth-title">{{ __('forgot_password.title') }}</h1>
                        <p class="auth-subtitle">{{ __('forgot_password.subtitle') }}</p>
                    @else
                        <h1 class="auth-title">{{ __('auth.login') }}</h1>
                    @endif

                    @error('auth')
                        <div class="auth-alert" role="alert">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" />
                                <path d="M12 8v4M12 16h.01" />
                            </svg>
                            <span>{{ $message }}</span>
                        </div>
                    @enderror

                    @if ($mode === 'forgot')
                        {{-- ── Form quên mật khẩu ───────────────────────── --}}
                        @if ($resetSent)
                            <div class="auth-alert auth-alert--success" role="status">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 4h16v16H4z" />
                                    <path d="m22 6-10 7L2 6" />
                                </svg>
                                <span>{{ __('forgot_password.sent') }}</span>
                            </div>
                        @endif

                        <form wire:submit="sendTemporaryPassword" novalidate>
                            <div class="mb-4">
                                <label for="identifier" class="field-label">{{ __('forgot_password.identifier') }}</label>
                                <div class="input-wrap">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="2" y="4" width="20" height="16" rx="2" />
                                        <path d="m22 7-10 6L2 7" />
                                    </svg>
                                    <input type="email" id="identifier" autocomplete="email" inputmode="email" autofocus
                                        class="form-control @error('identifier') is-invalid @enderror"
                                        wire:model="identifier"
                                        placeholder="{{ __('forgot_password.identifier_placeholder') }}">
                                </div>
                                @error('identifier')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-odin w-100" wire:loading.attr="disabled"
                                wire:target="sendTemporaryPassword">
                                <span wire:loading.remove wire:target="sendTemporaryPassword">
                                    {{ __('forgot_password.submit') }}
                                </span>
                                <span wire:loading wire:target="sendTemporaryPassword">
                                    <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                                    {{ __('forgot_password.sending') }}
                                </span>
                            </button>
                        </form>

                        <div class="auth-links auth-links--back">
                            <a href="#" class="forgot-link" wire:click.prevent="switchMode('login')">
                                &larr; {{ __('forgot_password.back_to_login') }}
                            </a>
                        </div>
                    @else
                    <form wire:submit="login" novalidate>
                        {{-- Tên đăng nhập --}}
                        <div class="mb-3">
                            <label for="username" class="field-label">{{ __('auth.username') }}</label>
                            <div class="input-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                        <circle cx="12" cy="7" r="4" />
                                    </svg>
                                <input type="text" id="username" autocomplete="username" autofocus
                                    class="form-control @error('username') is-invalid @enderror" wire:model="username"
                                    placeholder="{{ __('auth.username_placeholder') }}">
                            </div>
                            @error('username')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Mật khẩu --}}
                        <div class="mb-2" x-data="{ show: false }">
                            <label for="password" class="field-label">{{ __('auth.password_label') }}</label>
                            <div class="input-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="3" y="11" width="18" height="11" rx="2" />
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                    </svg>
                                <input :type="show ? 'text' : 'password'" type="password" id="password"
                                    autocomplete="current-password"
                                    class="form-control @error('password') is-invalid @enderror" wire:model="password"
                                    placeholder="{{ __('auth.password_placeholder') }}">
                                <button type="button" class="toggle-password" @click="show = !show"
                                    :aria-label="show ? @js(__('auth.hide_password')) : @js(__('auth.show_password'))">
                                    <svg x-show="!show" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        aria-hidden="true">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94" />
                                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19" />
                                        <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24M1 1l22 22" />
                                    </svg>
                                    <svg x-show="show" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        aria-hidden="true">
                                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                            </div>
                            @error('password')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="auth-links">
                            <label class="remember-check" for="remember">
                                <input type="checkbox" id="remember" wire:model="remember">
                                <span class="remember-box" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                                        stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                </span>
                                <span>{{ __('auth.remember_me') }}</span>
                            </label>
                            <a href="#" class="forgot-link" wire:click.prevent="switchMode('forgot')">{{ __('auth.forgot_password') }}</a>
                        </div>

                        <button type="submit" class="btn btn-odin w-100" wire:loading.attr="disabled"
                            wire:target="login">
                            <span wire:loading.remove wire:target="login" class="btn-odin__label">
                                {{ __('auth.login_button') }}
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M5 12h14M13 6l6 6-6 6" />
                                </svg>
                            </span>
                            <span wire:loading wire:target="login">
                                <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                                {{ __('auth.logging_in') }}
                            </span>
                        </button>
                    </form>

                    <p class="auth-note">{{ __('auth.no_account') }}</p>
                    @endif
                </div>
            </section>
        </div>
    </main>
</div>
