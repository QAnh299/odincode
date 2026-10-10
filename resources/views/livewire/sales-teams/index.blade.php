@php
    use App\Livewire\SalesTeams\Index;

    $summary = $this->summary;

    // Thẻ tổng quan (chỉ hiển thị, không lọc): [key, icon, tone]
    $cards = [
        ['teams', 'layers', 'all'],
        ['in_team', 'users', 'active'],
        ['without', 'user', 'inactive'],
    ];

    // Số avatar hiện tối đa trong cột Thành viên, còn lại gộp thành "+n"
    $maxAvatars = 5;
@endphp

<div class="vc em">
    {{-- Dùng lại giao diện trang voucher (resources/css/voucher.css) + phần riêng resources/css/employee.css --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/employee.css'])
    @endassets

    {{-- ── Tiêu đề ──────────────────────────────────────────────────── --}}
    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ __('employees.teams.title') }}</h1>
            <p class="vc-head__sub">{{ __('employees.teams.subtitle') }}</p>
        </div>
    </header>

    {{-- ── Tổng quan ────────────────────────────────────────────────── --}}
    <div class="vc-summary">
        @foreach ($cards as [$key, $icon, $tone])
            <div class="vc-summary__item em-summary-static vc-tone--{{ $tone }}">
                <span class="vc-summary__icon"><x-icon :name="$icon" /></span>
                <span class="vc-summary__text">
                    <span class="vc-summary__label">{{ __('employees.teams.summary.'.$key) }}</span>
                    <span class="vc-summary__value">{{ $summary[$key] }}</span>
                </span>
            </div>
        @endforeach
    </div>

    {{-- ── Tra cứu: tìm kiếm + sắp xếp ──────────────────────────────── --}}
    <section class="vc-card em-filters" aria-label="{{ __('employees.teams.lookup') }}">
        <label class="vc-field">
            <span class="vc-field__label">{{ __('employees.search') }}</span>
            <span class="vc-search">
                <x-icon name="search" />
                <input type="search" wire:model.live.debounce.350ms="search"
                    placeholder="{{ __('employees.teams.search_placeholder') }}">
                <span class="vc-search__spin" wire:loading.delay wire:target="search">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                </span>
            </span>
        </label>

        <label class="vc-field">
            <span class="vc-field__label">{{ __('employees.sort_by') }}</span>
            <select wire:model.live="sort" class="vc-control">
                @foreach (Index::SORTS as $option)
                    <option value="{{ $option }}">{{ __('employees.teams.sort.'.$option) }}</option>
                @endforeach
            </select>
        </label>

        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('employees.reset') }}
            </button>
        </div>
    </section>

    {{-- ── Kết quả ──────────────────────────────────────────────────── --}}
    <section class="vc-card vc-results">
        <div class="vc-results__bar">
            <span>
                {!! __('employees.teams.result_count', ['count' => '<strong>'.$teams->total().'</strong>']) !!}
            </span>
            <span class="vc-results__loading" wire:loading.delay>
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('employees.loading') }}
            </span>
        </div>

        @if ($teams->isEmpty())
            <div class="vc-empty">
                <span class="vc-empty__icon"><x-icon name="inbox" /></span>
                <p class="vc-empty__title">{{ __('employees.teams.empty') }}</p>
                <p class="vc-empty__sub">{{ __('employees.empty_sub') }}</p>
                @if ($this->hasFilters())
                    <button type="button" class="vc-btn vc-btn--primary" wire:click="resetFilters">
                        <x-icon name="refresh" />{{ __('employees.reset') }}
                    </button>
                @endif
            </div>
        @else
            <div class="vc-table-wrap" wire:loading.class="is-loading">
                <table class="vc-table vc-table--static">
                    <thead>
                        <tr>
                            <th>{{ __('employees.teams.team') }}</th>
                            <th>{{ __('employees.team_leader') }}</th>
                            <th>{{ __('employees.established_date') }}</th>
                            <th>{{ __('employees.members') }}</th>
                            <th class="text-lg-end">{{ __('employees.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teams as $team)
                            @php
                                $membersUrl = route($this->routePrefix.'.employees', ['team' => $team->team_id]);
                                $members = $team->employees;
                            @endphp
                            <tr wire:key="team-{{ $team->team_id }}">
                                <td data-label="{{ __('employees.teams.team') }}">
                                    <span class="em-person">
                                        <span class="em-team-icon" aria-hidden="true"><x-icon name="users" /></span>
                                        <span>
                                            <span class="em-person__name">{{ $team->team_name }}</span>
                                            <span class="em-person__id">{{ $team->team_id }}</span>
                                        </span>
                                    </span>
                                </td>
                                <td data-label="{{ __('employees.team_leader') }}">
                                    @if ($team->leader)
                                        <a href="{{ route($this->routePrefix.'.employees.show', $team->leader) }}" class="em-person">
                                            <span class="odin-avatar em-avatar" aria-hidden="true">{{ $team->leader->initial() }}</span>
                                            <span>
                                                <span class="em-person__name">{{ $team->leader->full_name }}</span>
                                                <span class="em-person__id">{{ $team->leader->employee_id }}</span>
                                            </span>
                                        </a>
                                    @else
                                        <span class="em-muted">{{ __('employees.teams.no_leader') }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ __('employees.established_date') }}">
                                    {{ $team->established_date?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td data-label="{{ __('employees.members') }}">
                                    <div class="em-stack">
                                        @foreach ($members->take($maxAvatars) as $member)
                                            <a href="{{ route($this->routePrefix.'.employees.show', $member) }}"
                                                class="odin-avatar em-avatar" title="{{ $member->full_name }}">
                                                {{ $member->initial() }}
                                                <span class="visually-hidden">{{ $member->full_name }}</span>
                                            </a>
                                        @endforeach
                                        @if ($members->count() > $maxAvatars)
                                            <span class="odin-avatar em-avatar em-avatar--more">+{{ $members->count() - $maxAvatars }}</span>
                                        @endif
                                        <span class="em-stack__count">
                                            {{ __('employees.teams.member_count', ['count' => $team->members_count]) }}
                                        </span>
                                    </div>
                                </td>
                                <td data-label="{{ __('employees.actions') }}" class="text-lg-end">
                                    <a href="{{ $membersUrl }}" class="vc-btn vc-btn--ghost em-view">
                                        <x-icon name="users" />{{ __('employees.teams.view_members') }}
                                        <span class="visually-hidden">{{ $team->team_name }}</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($teams->hasPages())
                <div class="vc-pagination">{{ $teams->links() }}</div>
            @endif
        @endif
    </section>
</div>
