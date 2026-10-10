@php
    use App\Livewire\Employees\Index;
    use App\Models\Employee;
    use App\Models\Role;

    $counts = $this->counts;

    // Thẻ thống kê: [key trạng thái, icon]
    $cards = [
        ['', 'users'],
        [Employee::STATE_WORKING, 'check-circle'],
        [Employee::STATE_RESIGNED, 'pause'],
    ];

    // Màu thẻ / nhãn theo trạng thái nhân viên (dùng lại tone của voucher)
    $tone = fn (string $state) => match ($state) {
        Employee::STATE_WORKING  => 'active',
        Employee::STATE_RESIGNED => 'inactive',
        default                  => 'all',
    };
@endphp

<div class="vc em">
    {{-- Dùng lại giao diện trang voucher (resources/css/voucher.css) + phần riêng resources/css/employee.css --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/employee.css'])
    @endassets

    {{-- ── Tiêu đề ──────────────────────────────────────────────────── --}}
    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ __('employees.title') }}</h1>
            <p class="vc-head__sub">
                @if ($this->routePrefix === Role::DIRECTOR)
                    {{ __('employees.subtitle') }}
                @elseif ($this->canFilterScope)
                    <x-icon name="shield" />{{ __('employees.subtitle_sale_admin') }}
                @else
                    <x-icon name="shield" />
                    {{ $this->ownTeam
                        ? __('employees.subtitle_team', ['team' => $this->ownTeam->team_name])
                        : __('employees.subtitle_no_team') }}
                @endif
            </p>
        </div>
    </header>

    {{-- ── Thống kê nhanh (bấm để lọc theo trạng thái) ─────────────── --}}
    <div class="vc-summary">
        @foreach ($cards as [$key, $icon])
            <button type="button" wire:click="filterState('{{ $key }}')"
                class="vc-summary__item vc-tone--{{ $tone($key) }} {{ $state === $key ? 'is-current' : '' }}"
                aria-pressed="{{ $state === $key ? 'true' : 'false' }}">
                <span class="vc-summary__icon"><x-icon :name="$icon" /></span>
                <span class="vc-summary__text">
                    <span class="vc-summary__label">{{ __('employees.state.'.($key ?: 'all')) }}</span>
                    <span class="vc-summary__value">{{ $counts[$key ?: 'all'] }}</span>
                </span>
            </button>
        @endforeach
    </div>

    {{-- ── Tra cứu: tìm kiếm + bộ lọc ───────────────────────────────── --}}
    <section class="vc-card vc-filters" aria-label="{{ __('employees.lookup') }}">
        <div class="vc-search">
            <x-icon name="search" />
            <input type="search" wire:model.live.debounce.350ms="search" placeholder="{{ __('employees.search_placeholder') }}"
                aria-label="{{ __('employees.search') }}">
            <span class="vc-search__spin" wire:loading.delay wire:target="search">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            </span>
        </div>

        <div class="vc-filters__grid {{ $this->canFilterScope ? '' : 'em-filters--compact' }}">
            @if ($this->canFilterRole)
                <label class="vc-field">
                    <span class="vc-field__label">{{ __('employees.role') }}</span>
                    <select wire:model.live="role" class="vc-control">
                        <option value="">{{ __('employees.all') }}</option>
                        @foreach ($this->roles as $option)
                            <option value="{{ $option->role_id }}">{{ __('roles.'.$option->role_name) }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @if ($this->canFilterScope)
                <label class="vc-field">
                    <span class="vc-field__label">{{ __('employees.team') }}</span>
                    <select wire:model.live="team" class="vc-control">
                        <option value="">{{ __('employees.all') }}</option>
                        @foreach ($this->teams as $option)
                            <option value="{{ $option->team_id }}">{{ $option->team_name }}</option>
                        @endforeach
                        <option value="{{ Index::NO_TEAM }}">{{ __('employees.no_team') }}</option>
                    </select>
                </label>

                <label class="vc-field">
                    <span class="vc-field__label">{{ __('employees.branch') }}</span>
                    <select wire:model.live="branch" class="vc-control">
                        <option value="">{{ __('employees.all') }}</option>
                        @foreach ($this->branches as $option)
                            <option value="{{ $option->branch_id }}">{{ $option->branch_name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="vc-field">
                <span class="vc-field__label">{{ __('employees.status') }}</span>
                <select wire:model.live="state" class="vc-control">
                    <option value="">{{ __('employees.all') }}</option>
                    @foreach (Employee::STATES as $option)
                        <option value="{{ $option }}">{{ __('employees.state.'.$option) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="vc-field">
                <span class="vc-field__label">{{ __('employees.sort_by') }}</span>
                <select wire:model.live="sort" class="vc-control">
                    @foreach (Index::SORTS as $option)
                        <option value="{{ $option }}">{{ __('employees.sort.'.$option) }}</option>
                    @endforeach
                </select>
            </label>

            <div class="vc-field vc-field--action">
                <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                    <x-icon name="refresh" />{{ __('employees.reset') }}
                </button>
            </div>
        </div>
    </section>

    {{-- ── Kết quả ──────────────────────────────────────────────────── --}}
    <section class="vc-card vc-results">
        <div class="vc-results__bar">
            <span>
                {!! __('employees.result_count', ['count' => '<strong>'.$employees->total().'</strong>']) !!}
            </span>
            <span class="vc-results__loading" wire:loading.delay>
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('employees.loading') }}
            </span>
        </div>

        @if ($employees->isEmpty())
            <div class="vc-empty">
                <span class="vc-empty__icon"><x-icon name="inbox" /></span>
                <p class="vc-empty__title">{{ __('employees.empty') }}</p>
                <p class="vc-empty__sub">{{ __('employees.empty_sub') }}</p>
                @if ($this->hasFilters())
                    <button type="button" class="vc-btn vc-btn--primary" wire:click="resetFilters">
                        <x-icon name="refresh" />{{ __('employees.reset') }}
                    </button>
                @endif
            </div>
        @else
            <div class="vc-table-wrap" wire:loading.class="is-loading">
                <table class="vc-table">
                    <thead>
                        <tr>
                            <th>{{ __('employees.employee') }}</th>
                            <th>{{ __('employees.role') }}</th>
                            @if ($this->canFilterScope)
                                <th>{{ __('employees.team') }}</th>
                                <th>{{ __('employees.branch') }}</th>
                            @endif
                            <th>{{ __('employees.contact') }}</th>
                            <th>{{ __('employees.hire_date') }}</th>
                            <th>{{ __('employees.status') }}</th>
                            <th class="text-lg-end">{{ __('employees.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $employee)
                            @php
                                $url = route($this->routePrefix.'.employees.show', $employee);
                                $employeeState = $employee->state();
                            @endphp
                            {{-- Bấm cả dòng hoặc nút "Xem chi tiết" đều mở trang chi tiết (giống danh sách voucher) --}}
                            <tr wire:key="employee-{{ $employee->employee_id }}" onclick="window.location='{{ $url }}'">
                                <td data-label="{{ __('employees.employee') }}">
                                    <a href="{{ $url }}" class="em-person" onclick="event.stopPropagation()">
                                        <span class="odin-avatar em-avatar" aria-hidden="true">{{ $employee->initial() }}</span>
                                        <span>
                                            <span class="em-person__name">{{ $employee->full_name }}</span>
                                            <span class="em-person__id">{{ $employee->employee_id }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td data-label="{{ __('employees.role') }}">
                                    <span class="em-role">{{ $employee->role_name ? __('roles.'.$employee->role_name) : '—' }}</span>
                                    @if (filled($employee->job_title))
                                        <span class="em-muted">{{ $employee->job_title }}</span>
                                    @endif
                                </td>
                                @if ($this->canFilterScope)
                                    <td data-label="{{ __('employees.team') }}">
                                        {{ $employee->team?->team_name ?? '—' }}
                                        @if ($employee->team && $employee->team->team_leader_id === $employee->employee_id)
                                            <span class="em-leader-tag">{{ __('employees.leader_tag') }}</span>
                                        @endif
                                    </td>
                                    <td data-label="{{ __('employees.branch') }}">
                                        {{ $employee->branch?->branch_name ?? '—' }}
                                    </td>
                                @endif
                                <td data-label="{{ __('employees.contact') }}">
                                    <span class="em-contact">{{ $employee->email ?: '—' }}</span>
                                    <span class="em-muted">{{ $employee->phone ?: '' }}</span>
                                </td>
                                <td data-label="{{ __('employees.hire_date') }}">
                                    {{ $employee->hire_date?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td data-label="{{ __('employees.status') }}">
                                    <span class="vc-badge vc-tone--{{ $tone($employeeState) }}">
                                        <span class="vc-badge__dot"></span>{{ __('employees.state.'.$employeeState) }}
                                    </span>
                                </td>
                                <td data-label="{{ __('employees.actions') }}" class="text-lg-end">
                                    <a href="{{ $url }}" class="vc-btn vc-btn--ghost em-view" onclick="event.stopPropagation()">
                                        <x-icon name="info" />{{ __('employees.view') }}
                                        <span class="visually-hidden">{{ $employee->employee_id }}</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($employees->hasPages())
                <div class="vc-pagination">{{ $employees->links() }}</div>
            @endif
        @endif
    </section>
</div>
