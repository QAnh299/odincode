@php
    $account = $this->account;
    $employee = $this->employee;
    $name = $employee?->full_name ?? $account->username;
    $active = $account->canSignIn();

    // Chỉ Sale Leader và Salesperson thuộc chi nhánh / nhóm kinh doanh
    $inSalesTeam = (bool) $employee?->hasRole(\App\Models\Role::SALE_LEADER, \App\Models\Role::SALESPERSON);

    // Chữ cái đầu của tên (từ cuối trong họ tên tiếng Việt)
    $parts = preg_split('/\s+/u', trim($name));
    $initial = mb_strtoupper(mb_substr(end($parts) ?: '?', 0, 1));

    $date = fn ($value) => $value?->format('d/m/Y') ?? '—';
    $text = fn ($value) => filled($value) ? $value : '—';

    // Thâm niên tính từ ngày vào làm, VD: "5 năm 4 tháng"
    $seniority = $employee?->hire_date?->diffForHumans([
        'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE,
        'parts'  => 2,
    ]);

    // Icon nét (stroke) dùng trong trang
    $icons = [
        'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'cake'     => '<path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8M4 16s.5-1 2-1 2.5 2 4 2 2.5-2 4-2 2.5 2 4 2 2-1 2-1M2 21h20M7 8v3M12 8v3M17 8v3M7 4h.01M12 4h.01M17 4h.01"/>',
        'mail'     => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'phone'    => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'id'       => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h2M15 12h2M7 16h10"/>',
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'briefcase'=> '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        'building' => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18zM6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2M10 6h4M10 10h4M10 14h4M10 18h4"/>',
        'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'crown'    => '<path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zM5 20h14"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'key'      => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/>',
        'lock'     => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'edit'     => '<path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'check'    => '<path d="M20 6 9 17l-5-5"/>',
        'x'        => '<path d="M18 6 6 18M6 6l12 12"/>',
        'chevron'  => '<path d="m9 18 6-6-6-6"/>',
    ];
    $icon = fn (string $name, string $class = 'pf-icon') => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none"'
        .' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        .$icons[$name].'</svg>';
@endphp

<div class="pf">
    {{-- CSS riêng của trang: resources/css/profile.css (chỉ nạp ở trang này) --}}
    @assets
        @vite('resources/css/profile.css')
    @endassets

    {{-- ── Thẻ tổng quan ────────────────────────────────────────────── --}}
    <section class="pf-hero">
        <div class="pf-hero__cover" aria-hidden="true"></div>

        <div class="pf-hero__body">
            <div class="pf-hero__avatar" aria-hidden="true">
                {{ $initial }}
                <span class="pf-hero__presence {{ $active ? 'is-on' : 'is-off' }}"></span>
            </div>

            <div class="pf-hero__info">
                <h1 class="pf-hero__name">{{ $name }}</h1>
                <div class="pf-hero__meta">
                    @if ($employee?->role_name)
                        <span class="pf-chip pf-chip--primary">
                            {!! $icon('shield') !!}{{ __('roles.'.$employee->role_name) }}
                        </span>
                    @endif
                    @if ($employee?->job_title)
                        <span class="pf-chip">{!! $icon('briefcase') !!}{{ $employee->job_title }}</span>
                    @endif
                    <span class="pf-chip {{ $active ? 'pf-chip--success' : 'pf-chip--danger' }}">
                        <span class="pf-dot"></span>
                        {{ $active ? __('profile.status_active') : __('profile.status_inactive') }}
                    </span>
                </div>
            </div>

            <div class="pf-hero__actions">
                @if ($employee && ! $editing)
                    <button type="button" class="pf-btn pf-btn--primary" wire:click="edit">
                        {!! $icon('edit') !!}{{ __('profile.edit_contact') }}
                    </button>
                @endif
                {{-- Chưa có trang: thay '#' bằng route khi làm chức năng đổi mật khẩu --}}
                <a href="#" class="pf-btn pf-btn--ghost">{!! $icon('lock') !!}{{ __('topbar.change_password') }}</a>
            </div>
        </div>

        {{-- Số liệu nhanh --}}
        @if ($employee)
            <dl class="pf-stats">
                <div class="pf-stat">
                    <span class="pf-stat__icon">{!! $icon('id') !!}</span>
                    <div>
                        <dt>{{ __('profile.employee_id') }}</dt>
                        <dd>{{ $employee->employee_id }}</dd>
                    </div>
                </div>
                @if ($inSalesTeam)
                    <div class="pf-stat">
                        <span class="pf-stat__icon">{!! $icon('building') !!}</span>
                        <div>
                            <dt>{{ __('profile.branch') }}</dt>
                            <dd>{{ $text($employee->branch?->branch_name) }}</dd>
                        </div>
                    </div>
                    <div class="pf-stat">
                        <span class="pf-stat__icon">{!! $icon('users') !!}</span>
                        <div>
                            <dt>{{ __('profile.team') }}</dt>
                            <dd>{{ $text($employee->team?->team_name) }}</dd>
                        </div>
                    </div>
                @else
                    <div class="pf-stat">
                        <span class="pf-stat__icon">{!! $icon('shield') !!}</span>
                        <div>
                            <dt>{{ __('profile.role') }}</dt>
                            <dd>{{ $employee->role_name ? __('roles.'.$employee->role_name) : '—' }}</dd>
                        </div>
                    </div>
                    <div class="pf-stat">
                        <span class="pf-stat__icon">{!! $icon('calendar') !!}</span>
                        <div>
                            <dt>{{ __('profile.hire_date') }}</dt>
                            <dd>{{ $date($employee->hire_date) }}</dd>
                        </div>
                    </div>
                @endif
                <div class="pf-stat">
                    <span class="pf-stat__icon">{!! $icon('clock') !!}</span>
                    <div>
                        <dt>{{ __('profile.seniority') }}</dt>
                        <dd>{{ $text($seniority) }}</dd>
                    </div>
                </div>
            </dl>
        @endif
    </section>

    @if ($saved)
        <div class="pf-toast" role="status" x-data="{ show: true }" x-show="show" x-transition.opacity
            x-init="setTimeout(() => show = false, 3500)">
            <span class="pf-toast__icon">{!! $icon('check') !!}</span>
            {{ __('profile.saved') }}
        </div>
    @endif

    <div class="pf-grid">
        <div class="pf-col">
            {{-- ── Thông tin cá nhân ────────────────────────────────── --}}
            <section class="pf-card {{ $editing ? 'is-editing' : '' }}">
                <header class="pf-card__head">
                    <span class="pf-card__icon">{!! $icon('user') !!}</span>
                    <div>
                        <h2 class="pf-card__title">{{ __('profile.personal') }}</h2>
                        <p class="pf-card__sub">
                            {{ $editing ? __('profile.editing_hint') : __('profile.personal_sub') }}
                        </p>
                    </div>
                </header>

                @if ($editing)
                    <form wire:submit="save" novalidate>
                        <div class="pf-fields">
                            <div class="pf-field">
                                <span class="pf-field__icon">{!! $icon('user') !!}</span>
                                <div class="pf-field__body">
                                    <span class="pf-field__label">{{ __('profile.full_name') }}</span>
                                    <span class="pf-field__value">{{ $name }}</span>
                                </div>
                            </div>
                            <div class="pf-field">
                                <span class="pf-field__icon">{!! $icon('cake') !!}</span>
                                <div class="pf-field__body">
                                    <span class="pf-field__label">{{ __('profile.date_of_birth') }}</span>
                                    <span class="pf-field__value">{{ $date($employee?->date_of_birth) }}</span>
                                </div>
                            </div>

                            <div class="pf-input">
                                <label for="profile-email" class="pf-field__label">{{ __('profile.email') }}</label>
                                <div class="pf-input__control @error('email') is-invalid @enderror">
                                    {!! $icon('mail') !!}
                                    <input type="email" id="profile-email" wire:model="email" autocomplete="email"
                                        placeholder="name@odin.example">
                                </div>
                                @error('email')
                                    <div class="pf-input__error">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="pf-input">
                                <label for="profile-phone" class="pf-field__label">{{ __('profile.phone') }}</label>
                                <div class="pf-input__control @error('phone') is-invalid @enderror">
                                    {!! $icon('phone') !!}
                                    <input type="tel" id="profile-phone" wire:model="phone" autocomplete="tel"
                                        placeholder="0901 234 567">
                                </div>
                                @error('phone')
                                    <div class="pf-input__error">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="pf-card__foot">
                            <button type="button" class="pf-btn pf-btn--light" wire:click="cancel">
                                {!! $icon('x') !!}{{ __('profile.cancel') }}
                            </button>
                            <button type="submit" class="pf-btn pf-btn--primary" wire:loading.attr="disabled"
                                wire:target="save">
                                <span wire:loading.remove wire:target="save">{!! $icon('check') !!}</span>
                                <span wire:loading wire:target="save" class="spinner-border spinner-border-sm"
                                    aria-hidden="true"></span>
                                {{ __('profile.save') }}
                            </button>
                        </div>
                    </form>
                @else
                    <div class="pf-fields">
                        @foreach ([
                            ['user', 'full_name', $name],
                            ['cake', 'date_of_birth', $date($employee?->date_of_birth)],
                            ['mail', 'email', $text($employee?->email)],
                            ['phone', 'phone', $text($employee?->phone)],
                        ] as [$ico, $label, $value])
                            <div class="pf-field">
                                <span class="pf-field__icon">{!! $icon($ico) !!}</span>
                                <div class="pf-field__body">
                                    <span class="pf-field__label">{{ __('profile.'.$label) }}</span>
                                    <span class="pf-field__value">{{ $value }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- ── Công việc ────────────────────────────────────────── --}}
            <section class="pf-card">
                <header class="pf-card__head">
                    <span class="pf-card__icon">{!! $icon('briefcase') !!}</span>
                    <div>
                        <h2 class="pf-card__title">{{ __('profile.work') }}</h2>
                        <p class="pf-card__sub">
                            {{ $inSalesTeam ? __('profile.work_sub') : __('profile.work_sub_basic') }}
                        </p>
                    </div>
                </header>

                @if ($employee)
                    <div class="pf-fields">
                        @foreach (array_filter([
                            ['id', 'employee_id', $employee->employee_id],
                            ['shield', 'role', $employee->role_name ? __('roles.'.$employee->role_name) : '—'],
                            ['briefcase', 'job_title', $text($employee->job_title)],
                            $inSalesTeam ? ['building', 'branch', $text($employee->branch?->branch_name)] : null,
                            $inSalesTeam ? ['users', 'team', $text($employee->team?->team_name)] : null,
                            ['calendar', 'hire_date', $date($employee->hire_date)],
                        ]) as [$ico, $label, $value])
                            <div class="pf-field">
                                <span class="pf-field__icon">{!! $icon($ico) !!}</span>
                                <div class="pf-field__body">
                                    <span class="pf-field__label">{{ __('profile.'.$label) }}</span>
                                    <span class="pf-field__value">{{ $value }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($inSalesTeam && ($leader = $employee->team?->leader))
                        <div class="pf-leader">
                            <span class="pf-leader__avatar" aria-hidden="true">
                                {{ mb_strtoupper(mb_substr(\Illuminate\Support\Str::afterLast(trim($leader->full_name), ' '), 0, 1)) }}
                            </span>
                            <div class="pf-leader__body">
                                <span class="pf-field__label">{!! $icon('crown', 'pf-icon pf-icon--xs') !!}{{ __('profile.team_leader') }}</span>
                                <span class="pf-leader__name">{{ $leader->full_name }}</span>
                            </div>
                            @if ($leader->email)
                                <a class="pf-leader__mail" href="mailto:{{ $leader->email }}"
                                    title="{{ $leader->email }}">{!! $icon('mail') !!}</a>
                            @endif
                        </div>
                    @endif
                @else
                    <div class="pf-empty">{!! $icon('user') !!}{{ __('profile.no_employee') }}</div>
                @endif
            </section>
        </div>

        <div class="pf-col">
            {{-- ── Tài khoản đăng nhập ──────────────────────────────── --}}
            <section class="pf-card">
                <header class="pf-card__head">
                    <span class="pf-card__icon">{!! $icon('key') !!}</span>
                    <div>
                        <h2 class="pf-card__title">{{ __('profile.account') }}</h2>
                        <p class="pf-card__sub">{{ __('profile.account_sub') }}</p>
                    </div>
                </header>

                <ul class="pf-rows">
                    <li>
                        <span class="pf-rows__label">{{ __('profile.account_id') }}</span>
                        <span class="pf-rows__value pf-mono">{{ $account->account_id }}</span>
                    </li>
                    <li>
                        <span class="pf-rows__label">{{ __('profile.username') }}</span>
                        <span class="pf-rows__value pf-mono">{{ $account->username }}</span>
                    </li>
                    <li>
                        <span class="pf-rows__label">{{ __('profile.account_status') }}</span>
                        <span class="pf-chip {{ $account->isActive() ? 'pf-chip--success' : 'pf-chip--danger' }}">
                            <span class="pf-dot"></span>
                            {{ $account->isActive() ? __('profile.status_active') : __('profile.status_inactive') }}
                        </span>
                    </li>
                    <li>
                        <span class="pf-rows__label">{{ __('profile.created_at') }}</span>
                        <span class="pf-rows__value">{{ $account->created_at?->format('d/m/Y H:i') ?? '—' }}</span>
                    </li>
                </ul>
            </section>

            {{-- ── Bảo mật ──────────────────────────────────────────── --}}
            <section class="pf-card pf-security">
                <span class="pf-security__icon">{!! $icon('shield') !!}</span>
                <div class="pf-security__body">
                    <h2 class="pf-card__title">{{ __('profile.security') }}</h2>
                    <p class="pf-card__sub">{{ __('profile.security_sub') }}</p>
                </div>
                {{-- Chưa có trang: thay '#' bằng route khi làm chức năng đổi mật khẩu --}}
                <a href="#" class="pf-security__link">
                    {{ __('topbar.change_password') }}{!! $icon('chevron') !!}
                </a>
            </section>
        </div>
    </div>
</div>
