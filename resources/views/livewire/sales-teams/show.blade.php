@php
    $team = $this->team;
    $leader = $team->leader;
    $members = $this->members;
    $branch = $this->branch;

    $date = fn ($value) => $value?->format('d/m/Y') ?? '—';
    $listUrl = route($this->routePrefix.'.sales-teams');
@endphp

<div class="ed">
    {{-- Dùng chung giao diện trang chi tiết nhân viên: resources/css/employee-detail.css --}}
    @assets
        @vite('resources/css/employee-detail.css')
    @endassets

    <a href="{{ $listUrl }}" class="ed-back">
        <x-icon name="arrow-left" />{{ __('employees.teams.back_to_list') }}
    </a>

    {{-- ── Thẻ đội ───────────────────────────────────────────────────── --}}
    <header class="ed-hero">
        <div class="ed-hero__banner" aria-hidden="true"></div>
        <div class="ed-hero__body">
            <span class="ed-hero__avatar ed-hero__avatar--team" aria-hidden="true"><x-icon name="users" /></span>

            <div class="ed-hero__main">
                <div class="ed-hero__title">
                    <h1 class="ed-hero__name">{{ $team->team_name }}</h1>
                </div>

                <ul class="ed-chips" aria-label="{{ __('employees.teams.team') }}">
                    <li class="ed-chip ed-chip--code">{{ $team->team_id }}</li>
                    <li class="ed-chip ed-chip--accent">
                        <x-icon name="users" />{{ __('employees.teams.member_count', ['count' => $members->count()]) }}
                    </li>
                    @if ($branch)
                        <li class="ed-chip"><x-icon name="layers" />{{ $branch->branch_name }}</li>
                    @endif
                    <li class="ed-chip"><x-icon name="calendar" />{{ __('employees.team_since', ['date' => $date($team->established_date)]) }}</li>
                </ul>
            </div>

            {{-- Trưởng nhóm --}}
            <div class="ed-hero__leader">
                <span class="ed-team__label">{{ __('employees.team_leader') }}</span>
                @if ($leader)
                    <a href="{{ route($this->routePrefix.'.employees.show', $leader) }}" class="ed-person is-link">
                        <span class="ed-person__avatar" aria-hidden="true">{{ $leader->initial() }}</span>
                        <span class="ed-person__text">
                            <span class="ed-person__name">{{ $leader->full_name }}</span>
                            <span class="ed-person__meta">{{ $leader->employee_id }} · {{ $leader->phone ?: $leader->email }}</span>
                        </span>
                    </a>
                @else
                    <span class="ed-muted">{{ __('employees.teams.no_leader') }}</span>
                @endif
            </div>
        </div>
    </header>

    {{-- ── Kết quả kinh doanh của đội ─────────────────────────────────── --}}
    @include('livewire.sales-teams.partials.performance')

    {{-- ── Thành viên ─────────────────────────────────────────────────── --}}
    <section class="ed-card ed-members" aria-labelledby="ed-members-title">
        <div class="ed-card__head">
            <h2 class="ed-card__title mb-0" id="ed-members-title">
                {{ __('employees.team_members') }}
                <span class="ed-muted fw-normal">({{ $members->count() }})</span>
            </h2>
            <a href="{{ route($this->routePrefix.'.employees', ['team' => $team->team_id]) }}" class="ed-btn">
                {{ __('employees.teams.open_in_list') }}<x-icon name="chevron" />
            </a>
        </div>

        @if ($members->isEmpty())
            <div class="ed-empty">
                <span class="ed-empty__icon"><x-icon name="users" /></span>
                <p>{{ __('employees.no_teammates') }}</p>
            </div>
        @else
            <ul class="ed-people ed-people--grid">
                @foreach ($members as $member)
                    <li wire:key="member-{{ $member->employee_id }}">
                        <a href="{{ route($this->routePrefix.'.employees.show', $member) }}" class="ed-person is-link">
                            <span class="ed-person__avatar" aria-hidden="true">{{ $member->initial() }}</span>
                            <span class="ed-person__text">
                                <span class="ed-person__name">{{ $member->full_name }}</span>
                                <span class="ed-person__meta">
                                    {{-- Trưởng nhóm: nhãn nằm ở dòng phụ để tên không bị cắt trong ô hẹp --}}
                                    @if ($member->employee_id === $team->team_leader_id)
                                        <span class="ed-tag">{{ __('employees.leader_tag') }}</span> {{ $member->employee_id }}
                                    @else
                                        {{ $member->employee_id }} · {{ $member->role_name ? __('roles.'.$member->role_name) : '—' }}
                                    @endif
                                </span>
                            </span>
                            <x-icon name="chevron" class="ed-person__go" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
