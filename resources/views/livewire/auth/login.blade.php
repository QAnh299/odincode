<div class="auth-page">
    <div class="auth-blobs" aria-hidden="true"><span></span><span></span><span></span></div>

    {{-- ── Header ───────────────────────────────────────────────────── --}}
    <header class="auth-header">
        <div class="header-brand">
            <img src="{{ asset('images/favicon.png') }}" alt="">
            <div>
                <strong>ODIN</strong>
                <small>Language Center</small>
            </div>
        </div>
        <x-language-switcher />
    </header>

    <main class="auth-main">
        <div class="auth-shell">
            {{-- ── Cột trái: thương hiệu ────────────────────────────── --}}
            <aside class="auth-brand">
                <div>
                    <span class="brand-tag"><span class="dot"></span>ODIN CRM</span>
                    <h2 class="brand-title">
                        {{ __('auth.brand_title') }}<br>
                        <span>{{ __('auth.brand_highlight') }}</span>
                    </h2>
                    <p class="brand-sub">{{ __('auth.brand_sub') }}</p>
                </div>

                <div class="pipeline" aria-hidden="true">
                    @foreach ([['stage_new', 100], ['stage_consulting', 68], ['stage_won', 42]] as [$stage, $width])
                        <div class="pipeline-row">
                            <div class="pipeline-label"><span>{{ __('auth.'.$stage) }}</span></div>
                            <div class="pipeline-track"><span style="width: {{ $width }}%"></span></div>
                        </div>
                    @endforeach
                </div>

                <div class="brand-features">
                    <div>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                        {{ __('auth.feature_leads') }}
                    </div>
                    <div>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                        </svg>
                        {{ __('auth.feature_care') }}
                    </div>
                    <div>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 3v18h18" />
                            <path d="m19 9-5 5-4-4-3 3" />
                        </svg>
                        {{ __('auth.feature_revenue') }}
                    </div>
                </div>
            </aside>

            {{-- ── Cột phải: form đăng nhập ─────────────────────────── --}}
            <section class="auth-panel">
                <div class="panel-top">
                    <img src="{{ asset('images/logoodin-trim.png') }}" alt="ODIN Language Center" class="panel-logo">
                    <span class="secure-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        </svg>
                        {{ __('auth.secure') }}
                    </span>
                </div>

                <div class="auth-form">
                    <h1 class="auth-title">{{ __('auth.login') }}</h1>
                    <p class="auth-subtitle">{{ __('auth.login_sub') }}</p>

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

                    <form wire:submit="login" novalidate>
                        {{-- Tên đăng nhập --}}
                        <div class="mb-3">
                            <label for="username" class="field-label">{{ __('auth.username') }}</label>
                            <div class="input-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="16" rx="2" />
                                    <circle cx="9" cy="10" r="2" />
                                    <path d="M15 8h2M15 12h2M7 16h10" />
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
                        <div class="mb-4" x-data="{ show: false }">
                            <div class="d-flex justify-content-between align-items-center">
                                <label for="password" class="field-label">{{ __('auth.password_label') }}</label>
                                {{-- Chỉ là giao diện, chức năng quên mật khẩu sẽ làm sau --}}
                                <a href="#" class="forgot-link" @click.prevent>{{ __('auth.forgot_password') }}</a>
                            </div>
                            <div class="input-icon">
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

                        <button type="submit" class="btn btn-odin w-100" wire:loading.attr="disabled"
                            wire:target="login">
                            <span wire:loading.remove wire:target="login">
                                {{ __('auth.login_button') }}
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="ms-1"
                                    aria-hidden="true">
                                    <path d="M5 12h14M12 5l7 7-7 7" />
                                </svg>
                            </span>
                            <span wire:loading wire:target="login">
                                <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                                {{ __('auth.logging_in') }}
                            </span>
                        </button>
                    </form>

                    <div class="role-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            <path d="m9 12 2 2 4-4" />
                        </svg>
                        {{ __('auth.role_note') }}
                    </div>
                </div>

                <div class="panel-footer">© {{ date('Y') }} ODIN Language Center</div>
            </section>
        </div>
    </main>
</div>
