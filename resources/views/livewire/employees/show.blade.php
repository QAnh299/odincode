@php
    $employee = $this->employee;
    $team = $employee->team;
    $account = $employee->account;
    $state = $employee->state();
    $tone = $employee->isActive() ? 'success' : 'secondary';

    $date = fn ($value) => $value?->format('d/m/Y') ?? '—';
    $number = fn ($value) => number_format((int) $value, 0, ',', '.');

    // Thâm niên tính từ ngày vào làm: "3 năm 4 tháng"
    $months = $employee->hire_date ? max(0, (int) $employee->hire_date->diffInMonths(today())) : null;
    $seniority = match (true) {
        $months === null   => null,
        $months < 1        => __('employees.seniority_new'),
        $months < 12       => __('employees.seniority_months', ['months' => $months]),
        $months % 12 === 0 => __('employees.seniority_years', ['years' => intdiv($months, 12)]),
        default            => __('employees.seniority', ['years' => intdiv($months, 12), 'months' => $months % 12]),
    };

    $isLeader = $team && $team->team_leader_id === $employee->employee_id;
    $listUrl = route($this->routePrefix.'.employees');
@endphp

<div class="cd em-detail">
    {{-- Dùng lại khung trang chi tiết khóa học (resources/css/course.css) + phần riêng resources/css/employee.css --}}
    @assets
        @vite(['resources/css/course.css', 'resources/css/employee.css'])
    @endassets

    {{-- ── Đầu trang ────────────────────────────────────────────────── --}}
    <header class="cd-head">
        <a href="{{ $listUrl }}" class="btn btn-sm btn-outline-secondary cd-back">
            <x-icon name="arrow-left" />{{ __('employees.back_to_list') }}
        </a>

        <div class="cd-head__title">
            <div class="em-hero">
                <span class="odin-avatar em-hero__avatar" aria-hidden="true">{{ $employee->initial() }}</span>
                <div>
                    <h1 class="h3 mb-1">{{ $employee->full_name }}</h1>
                    <span class="cd-code">{{ $employee->employee_id }}</span>
                    <span class="em-hero__role">
                        {{ $employee->role_name ? __('roles.'.$employee->role_name) : '—' }}
                        @if ($isLeader)
                            · {{ __('employees.leader_of', ['team' => $team->team_name]) }}
                        @endif
                    </span>
                </div>
            </div>
            <span class="badge rounded-pill cd-badge bg-{{ $tone }}-subtle text-{{ $tone }}-emphasis border border-{{ $tone }}-subtle">
                <span class="cd-dot bg-{{ $tone }}" aria-hidden="true"></span>{{ __('employees.state.'.$state) }}
            </span>
        </div>
    </header>

    <div class="em-layout">
        <div class="em-col">
            {{-- Thông tin cá nhân --}}
            <section class="card cd-card">
                <div class="card-body">
                    <h2 class="cd-card__title">{{ __('employees.personal_info') }}</h2>
                    <dl class="cd-facts mb-0">
                        <div class="cd-fact">
                            <dt><x-icon name="calendar" />{{ __('employees.date_of_birth') }}</dt>
                            <dd>{{ $date($employee->date_of_birth) }}</dd>
                        </div>
                        <div class="cd-fact">
                            <dt><x-icon name="message" />{{ __('employees.email') }}</dt>
                            <dd>
                                @if (filled($employee->email))
                                    <a href="mailto:{{ $employee->email }}" class="em-link">{{ $employee->email }}</a>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div class="cd-fact">
                            <dt><x-icon name="phone" />{{ __('employees.phone') }}</dt>
                            <dd>
                                @if (filled($employee->phone))
                                    <a href="tel:{{ $employee->phone }}" class="em-link">{{ $employee->phone }}</a>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            {{-- Thông tin công việc --}}
            <section class="card cd-card">
                <div class="card-body">
                    <h2 class="cd-card__title">{{ __('employees.work_info') }}</h2>
                    <dl class="cd-facts">
                        <div class="cd-fact">
                            <dt><x-icon name="tag" />{{ __('employees.job_title') }}</dt>
                            <dd>{{ $employee->job_title ?: '—' }}</dd>
                        </div>
                        <div class="cd-fact">
                            <dt><x-icon name="shield" />{{ __('employees.role') }}</dt>
                            <dd>{{ $employee->role_name ? __('roles.'.$employee->role_name) : '—' }}</dd>
                        </div>
                        <div class="cd-fact">
                            <dt><x-icon name="calendar" />{{ __('employees.hire_date') }}</dt>
                            <dd>{{ $date($employee->hire_date) }}</dd>
                        </div>
                        <div class="cd-fact">
                            <dt><x-icon name="users" />{{ __('employees.team') }}</dt>
                            <dd>{{ $team?->team_name ?? __('employees.no_team') }}</dd>
                        </div>
                        <div class="cd-fact">
                            <dt><x-icon name="layers" />{{ __('employees.branch') }}</dt>
                            <dd>{{ $employee->branch?->branch_name ?? '—' }}</dd>
                        </div>
                        <div class="cd-fact">
                            <dt><x-icon name="clock" />{{ __('employees.seniority_label') }}</dt>
                            <dd>{{ $employee->isActive() ? ($seniority ?? '—') : '—' }}</dd>
                        </div>
                    </dl>

                    @if ($employee->branch?->address)
                        <p class="cd-note mb-0"><x-icon name="info" />{{ $employee->branch->address }}</p>
                    @endif
                </div>
            </section>

            {{-- Đội kinh doanh --}}
            <section class="card cd-card">
                <div class="card-body">
                    <div class="cd-list-head mb-3">
                        <h2 class="cd-card__title mb-0">
                            {{ __('employees.team_section') }}
                            @if ($team)
                                <span class="text-body-secondary fw-normal">· {{ $team->team_name }}</span>
                            @endif
                        </h2>
                        @if ($team && $this->canViewTeams)
                            <a href="{{ route($this->routePrefix.'.sales-teams', ['q' => $team->team_id]) }}"
                                class="btn btn-sm btn-outline-secondary">
                                <x-icon name="users" />{{ __('employees.view_team') }}
                            </a>
                        @endif
                    </div>

                    @if (! $team)
                        <div class="cd-empty">
                            <span class="cd-empty__icon"><x-icon name="users" /></span>
                            <p class="mb-0">{{ __('employees.no_team_note') }}</p>
                        </div>
                    @else
                        <dl class="cd-facts">
                            <div class="cd-fact">
                                <dt><x-icon name="user" />{{ __('employees.team_leader') }}</dt>
                                <dd>{{ $team->leader?->full_name ?? '—' }}</dd>
                            </div>
                            <div class="cd-fact">
                                <dt><x-icon name="calendar" />{{ __('employees.established_date') }}</dt>
                                <dd>{{ $date($team->established_date) }}</dd>
                            </div>
                            <div class="cd-fact">
                                <dt><x-icon name="users" />{{ __('employees.members') }}</dt>
                                <dd>{{ $this->teammates->count() + ($employee->isActive() ? 1 : 0) }}</dd>
                            </div>
                        </dl>

                        <h3 class="cd-label">{{ __('employees.teammates') }}</h3>
                        @if ($this->teammates->isEmpty())
                            <p class="text-body-secondary mb-0">{{ __('employees.no_teammates') }}</p>
                        @else
                            <ul class="em-members">
                                @foreach ($this->teammates as $member)
                                    <li wire:key="member-{{ $member->employee_id }}">
                                        <a href="{{ route($this->routePrefix.'.employees.show', $member) }}" class="em-person">
                                            <span class="odin-avatar em-avatar" aria-hidden="true">{{ $member->initial() }}</span>
                                            <span>
                                                <span class="em-person__name">{{ $member->full_name }}</span>
                                                <span class="em-person__id">
                                                    {{ $member->employee_id }} · {{ $member->role_name ? __('roles.'.$member->role_name) : '—' }}
                                                </span>
                                            </span>
                                        </a>
                                        @if ($team->team_leader_id === $member->employee_id)
                                            <span class="em-leader-tag">{{ __('employees.leader_tag') }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </div>
            </section>
        </div>

        <div class="em-col">
            {{-- Tài khoản đăng nhập --}}
            <section class="card cd-card">
                <div class="card-body">
                    <h2 class="cd-card__title">{{ __('employees.account') }}</h2>
                    @if ($account)
                        @php $accountTone = $account->isActive() ? 'success' : 'secondary'; @endphp
                        <dl class="em-rows mb-0">
                            <div>
                                <dt>{{ __('employees.username') }}</dt>
                                <dd><span class="cd-code">{{ $account->username }}</span></dd>
                            </div>
                            <div>
                                <dt>{{ __('employees.account_status') }}</dt>
                                <dd>
                                    <span class="badge rounded-pill cd-badge bg-{{ $accountTone }}-subtle text-{{ $accountTone }}-emphasis border border-{{ $accountTone }}-subtle">
                                        <span class="cd-dot bg-{{ $accountTone }}" aria-hidden="true"></span>
                                        {{ $account->isActive() ? __('employees.account_active') : __('employees.account_inactive') }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt>{{ __('employees.account_created') }}</dt>
                                <dd>{{ $account->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                            </div>
                        </dl>
                    @else
                        <p class="text-body-secondary mb-0">{{ __('employees.no_account') }}</p>
                    @endif
                </div>
            </section>

            {{-- Kết quả kinh doanh (chỉ với Sale Leader / Salesperson) --}}
            @if ($this->isSales)
                @php
                    $stats = $this->stats;
                    $rate = $stats['win_rate'];
                    $rateText = $rate === null ? '—'
                        : rtrim(rtrim(number_format($rate, 1, ',', '.'), '0'), ',').'%';
                @endphp
                <section class="card cd-card">
                    <div class="card-body">
                        <h2 class="cd-card__title">{{ __('employees.performance') }}</h2>
                        <div class="cd-stats">
                            <div class="cd-stat">
                                <span class="cd-stat__label">{{ __('employees.opportunities') }}</span>
                                <span class="cd-stat__value">{{ $number($stats['opportunities']) }}</span>
                                <span class="cd-stat__hint">{{ __('employees.in_progress', ['count' => $stats['in_progress']]) }}</span>
                            </div>
                            <div class="cd-stat">
                                <span class="cd-stat__label">{{ __('employees.won') }}</span>
                                <span class="cd-stat__value">{{ $number($stats['won']) }}</span>
                                <span class="cd-stat__hint">{{ __('employees.win_rate', ['rate' => $rateText]) }}</span>
                            </div>
                            <div class="cd-stat">
                                <span class="cd-stat__label">{{ __('employees.quotations') }}</span>
                                <span class="cd-stat__value">{{ $number($stats['quotations']) }}</span>
                                <span class="cd-stat__hint">{{ __('employees.confirmed', ['count' => $stats['confirmed']]) }}</span>
                            </div>
                            <div class="cd-stat">
                                <span class="cd-stat__label">{{ __('employees.appointments') }}</span>
                                <span class="cd-stat__value">{{ $number($stats['appointments']) }}</span>
                            </div>
                        </div>
                        <p class="cd-note cd-scope-note mb-0"><x-icon name="info" />{{ __('employees.performance_note') }}</p>
                    </div>
                </section>
            @endif
        </div>
    </div>
</div>
