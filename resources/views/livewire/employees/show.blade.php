@php
    $employee = $this->employee;
    $team = $employee->team;
    $branch = $employee->branch;
    $state = $employee->state();
    $roleLabel = $employee->role_name ? __('roles.'.$employee->role_name) : '—';

    $date = fn ($value) => $value?->format('d/m/Y') ?? '—';

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

    // Thông tin nhân viên theo nhóm: [icon, nhãn, giá trị, dòng phụ, link, chiếm 2 cột]
    $groups = [
        'personal_info' => [
            ['calendar', __('employees.date_of_birth'), $date($employee->date_of_birth), null, null, false],
            ['message', __('employees.email'), $employee->email ?: '—', null, $employee->email ? 'mailto:'.$employee->email : null, false],
            ['phone', __('employees.phone'), $employee->phone ?: '—', null, $employee->phone ? 'tel:'.$employee->phone : null, false],
        ],
        'work_info' => array_values(array_filter([
            ['tag', __('employees.job_title'), $employee->job_title ?: '—', null, null, false],
            ['shield', __('employees.role'), $roleLabel, null, null, false],
            ['calendar', __('employees.hire_date'), $date($employee->hire_date),
                $employee->isActive() && $seniority ? __('employees.seniority_value', ['value' => $seniority]) : null, null, false],
            ['layers', __('employees.branch'), $branch?->branch_name ?? '—', $branch?->address, null, true],
            $this->isSales ? ['users', __('employees.team'), $team?->team_name ?? __('employees.no_team'), null, null, false] : null,
        ])),
    ];
@endphp

<div class="ed">
    {{-- CSS riêng của trang chi tiết nhân viên: resources/css/employee-detail.css --}}
    @assets
        @vite('resources/css/employee-detail.css')
    @endassets

    <a href="{{ $listUrl }}" class="ed-back">
        <x-icon name="arrow-left" />{{ __('employees.back_to_list') }}
    </a>

    {{-- ── Thẻ hồ sơ ─────────────────────────────────────────────────── --}}
    <header class="ed-hero">
        <div class="ed-hero__banner" aria-hidden="true"></div>
        <div class="ed-hero__body">
            <span class="ed-hero__avatar" aria-hidden="true">{{ $employee->initial() }}</span>

            <div class="ed-hero__main">
                <div class="ed-hero__title">
                    <h1 class="ed-hero__name">{{ $employee->full_name }}</h1>
                    <span class="ed-status ed-status--{{ $state }}">
                        <span class="ed-status__dot" aria-hidden="true"></span>{{ __('employees.state.'.$state) }}
                    </span>
                </div>

                <ul class="ed-chips" aria-label="{{ __('employees.work_info') }}">
                    <li class="ed-chip ed-chip--code">{{ $employee->employee_id }}</li>
                    <li class="ed-chip ed-chip--accent"><x-icon name="shield" />{{ $roleLabel }}</li>
                    @if ($isLeader)
                        <li class="ed-chip"><x-icon name="users" />{{ __('employees.leader_of', ['team' => $team->team_name]) }}</li>
                    @elseif ($this->isSales && $team)
                        <li class="ed-chip"><x-icon name="users" />{{ $team->team_name }}</li>
                    @endif
                    @if ($branch)
                        <li class="ed-chip"><x-icon name="layers" />{{ $branch->branch_name }}</li>
                    @endif
                </ul>
            </div>

            {{-- Liên hệ nhanh --}}
            <div class="ed-hero__contact">
                @if (filled($employee->email))
                    <a href="mailto:{{ $employee->email }}" class="ed-contact" title="{{ __('employees.email') }}">
                        <x-icon name="message" /><span>{{ $employee->email }}</span>
                    </a>
                @endif
                @if (filled($employee->phone))
                    <a href="tel:{{ $employee->phone }}" class="ed-contact" title="{{ __('employees.phone') }}">
                        <x-icon name="phone" /><span>{{ $employee->phone }}</span>
                    </a>
                @endif
            </div>
        </div>
    </header>

    {{-- Sale Leader / Salesperson: thông tin + Đội kinh doanh (2 cột) rồi Kết quả kinh doanh;
         Giám đốc, Sale Admin, Kế toán: chỉ thông tin nhân viên --}}
    <div class="ed-grid {{ $this->isSales ? '' : 'ed-grid--single' }}">
        {{-- ── Thông tin nhân viên ───────────────────────────────────── --}}
        <section class="ed-card" aria-labelledby="ed-info-title">
            <h2 class="ed-card__title" id="ed-info-title">{{ __('employees.profile_title') }}</h2>

            @foreach ($groups as $groupKey => $items)
                <div class="ed-group">
                    <h3 class="ed-group__title">{{ __('employees.'.$groupKey) }}</h3>
                    <dl class="ed-fields">
                        @foreach ($items as [$icon, $label, $value, $sub, $href, $wide])
                            <div class="ed-field {{ $wide ? 'ed-field--wide' : '' }}">
                                <dt><x-icon :name="$icon" />{{ $label }}</dt>
                                <dd>
                                    @if ($href)
                                        <a href="{{ $href }}" class="ed-link">{{ $value }}</a>
                                    @else
                                        {{ $value }}
                                    @endif
                                    @if ($sub)
                                        <span class="ed-field__sub">{{ $sub }}</span>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </section>

        {{-- ── Đội kinh doanh (chỉ với Sale Leader / Salesperson) ─────── --}}
        @if ($this->isSales)
            <section class="ed-card ed-team" aria-labelledby="ed-team-title">
                <div class="ed-card__head">
                    <h2 class="ed-card__title mb-0" id="ed-team-title">{{ __('employees.team_section') }}</h2>
                    @if ($team && $this->canViewTeams)
                        <a href="{{ route($this->routePrefix.'.sales-teams', ['q' => $team->team_id]) }}" class="ed-btn">
                            {{ __('employees.view_team') }}<x-icon name="chevron" />
                        </a>
                    @endif
                </div>

                @if (! $team)
                    <div class="ed-empty">
                        <span class="ed-empty__icon"><x-icon name="users" /></span>
                        <p>{{ __('employees.no_team_note') }}</p>
                    </div>
                @else
                    @php $memberCount = $this->teammates->count() + ($employee->isActive() ? 1 : 0); @endphp
                    <div class="ed-team__summary">
                        <span class="ed-team__icon" aria-hidden="true"><x-icon name="users" /></span>
                        <div class="ed-team__text">
                            <span class="ed-team__name">{{ $team->team_name }}</span>
                            <span class="ed-team__meta">
                                {{ $team->team_id }} · {{ __('employees.team_since', ['date' => $date($team->established_date)]) }}
                            </span>
                        </div>
                        <span class="ed-team__count">{{ __('employees.teams.member_count', ['count' => $memberCount]) }}</span>
                    </div>

                    @unless ($isLeader)
                        <div class="ed-team__leader">
                            <span class="ed-team__label">{{ __('employees.team_leader') }}</span>
                            <span class="ed-team__leader-name">{{ $team->leader?->full_name ?? __('employees.teams.no_leader') }}</span>
                        </div>
                    @endunless

                    <h3 class="ed-group__title">{{ $isLeader ? __('employees.team_members') : __('employees.teammates') }}</h3>
                    @if ($this->teammates->isEmpty())
                        <p class="ed-muted mb-0">{{ __('employees.no_teammates') }}</p>
                    @else
                        <ul class="ed-people">
                            @foreach ($this->teammates as $member)
                                @php $linkable = in_array($member->employee_id, $this->linkableIds, true); @endphp
                                <li wire:key="member-{{ $member->employee_id }}">
                                    {{-- Chỉ tạo link tới nhân viên mà người đang xem được mở chi tiết --}}
                                    <{{ $linkable ? 'a' : 'div' }} class="ed-person {{ $linkable ? 'is-link' : '' }}"
                                        @if ($linkable) href="{{ route($this->routePrefix.'.employees.show', $member) }}" @endif>
                                        <span class="ed-person__avatar" aria-hidden="true">{{ $member->initial() }}</span>
                                        <span class="ed-person__text">
                                            <span class="ed-person__name">{{ $member->full_name }}</span>
                                            <span class="ed-person__meta">
                                                {{ $member->employee_id }} · {{ $member->role_name ? __('roles.'.$member->role_name) : '—' }}
                                            </span>
                                        </span>
                                        @if ($team->team_leader_id === $member->employee_id)
                                            <span class="ed-tag">{{ __('employees.leader_tag') }}</span>
                                        @elseif ($linkable)
                                            <x-icon name="chevron" class="ed-person__go" />
                                        @endif
                                    </{{ $linkable ? 'a' : 'div' }}>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </section>
        @endif
    </div>

    {{-- ── Kết quả kinh doanh (Sale Leader: cả đội phụ trách; Salesperson: của riêng mình) ── --}}
    @if ($this->isSales)
        @include('livewire.employees.partials.performance')
    @endif
</div>
