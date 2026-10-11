@php
    use App\Models\Student;

    $stats = $this->stats;
    $isSalesperson = $this->routePrefix === \App\Models\Role::SALESPERSON;

    // Thẻ thống kê (bấm để lọc): [trạng thái, icon]
    $cards = [
        ['', 'users'],
        [Student::STATUS_STUDYING, 'book'],
        [Student::STATUS_COMPLETED, 'check-circle'],
        [Student::STATUS_DROPPED, 'pause'],
    ];
@endphp

<div class="vc st">
    {{-- Dùng lại giao diện trang voucher (resources/css/voucher.css) + phần riêng resources/css/student.css --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/student.css'])
    @endassets

    {{-- ── Tiêu đề ──────────────────────────────────────────────────── --}}
    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ __('students.title') }}</h1>
            <p class="vc-head__sub">{{ __('students.subtitle') }}</p>
        </div>
    </header>

    {{-- ── Thống kê theo trạng thái (bấm để lọc) ───────────────────── --}}
    <div class="vc-summary st-summary">
        @foreach ($cards as [$key, $icon])
            <button type="button" wire:click="filterStatus('{{ $key }}')"
                class="vc-summary__item st-tone--{{ $key ?: 'all' }} {{ $status === $key ? 'is-current' : '' }}"
                aria-pressed="{{ $status === $key ? 'true' : 'false' }}">
                <span class="vc-summary__icon"><x-icon :name="$icon" /></span>
                <span class="vc-summary__text">
                    <span class="vc-summary__label">{{ __('students.stat.'.($key ?: 'all')) }}</span>
                    <span class="vc-summary__value">{{ $stats[$key ?: 'all'] }}</span>
                </span>
            </button>
        @endforeach
    </div>

    {{-- ── Tra cứu ──────────────────────────────────────────────────── --}}
    <section class="vc-card st-filters" aria-label="{{ __('students.lookup') }}">
        <label class="vc-field st-filters__search">
            <span class="vc-field__label">{{ __('students.search') }}</span>
            <span class="vc-search">
                <x-icon name="search" />
                <input type="search" wire:model.live.debounce.350ms="search" placeholder="{{ __('students.search_placeholder') }}">
                <span class="vc-search__spin" wire:loading.delay wire:target="search">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                </span>
            </span>
        </label>

        <label class="vc-field">
            <span class="vc-field__label">{{ __('students.status') }}</span>
            <select wire:model.live="status" class="vc-control">
                <option value="">{{ __('students.all') }}</option>
                @foreach (Student::STATUSES as $option)
                    <option value="{{ $option }}">{{ __('students.status_name.'.$option) }}</option>
                @endforeach
            </select>
        </label>

        <label class="vc-field">
            <span class="vc-field__label">{{ __('students.course') }}</span>
            <select wire:model.live="course" class="vc-control">
                <option value="">{{ __('students.all_courses') }}</option>
                @foreach ($this->courses as $option)
                    <option value="{{ $option->course_id }}">{{ $option->course_name }}</option>
                @endforeach
            </select>
        </label>

        @if ($this->canFilterTeam)
            <label class="vc-field">
                <span class="vc-field__label">{{ __('students.team') }}</span>
                <select wire:model.live="team" class="vc-control">
                    <option value="">{{ __('students.all_teams') }}</option>
                    @foreach ($this->teams as $option)
                        <option value="{{ $option->team_id }}">{{ $option->team_name }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @if ($this->canFilterEmployee)
            <label class="vc-field">
                <span class="vc-field__label">{{ __('students.salesperson') }}</span>
                <select wire:model.live="employee" class="vc-control" @disabled(! $this->activeTeam)>
                    <option value="">{{ $this->activeTeam ? __('students.all_salespeople') : __('students.choose_team_first') }}</option>
                    @foreach ($this->employeeOptions as $option)
                        <option value="{{ $option->employee_id }}">
                            {{ $option->full_name }}{{ $option->status === 'Resigned' ? ' ('.__('students.resigned').')' : '' }}
                        </option>
                    @endforeach
                </select>
            </label>
        @endif

        <label class="vc-field">
            <span class="vc-field__label">{{ __('students.from') }}</span>
            <input type="date" wire:model.live="from" class="vc-control" max="{{ $to ?: '' }}">
        </label>

        <label class="vc-field">
            <span class="vc-field__label">{{ __('students.to') }}</span>
            <input type="date" wire:model.live="to" class="vc-control" min="{{ $from ?: '' }}">
        </label>

        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('students.reset') }}
            </button>
        </div>
    </section>

    {{-- ── Danh sách ────────────────────────────────────────────────── --}}
    <section class="vc-card vc-results">
        <div class="vc-results__bar">
            <span>{!! __('students.result_count', ['count' => '<strong>'.$students->total().'</strong>']) !!}</span>
            <span class="vc-results__loading" wire:loading.delay>
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('students.loading') }}
            </span>
        </div>

        @if ($students->isEmpty())
            <div class="vc-empty">
                <span class="vc-empty__icon"><x-icon name="users" /></span>
                <p class="vc-empty__title">{{ __('students.empty') }}</p>
                <p class="vc-empty__sub">{{ __('students.empty_sub') }}</p>
                @if ($this->hasFilters())
                    <button type="button" class="vc-btn vc-btn--primary" wire:click="resetFilters">
                        <x-icon name="refresh" />{{ __('students.reset') }}
                    </button>
                @endif
            </div>
        @else
            <div class="vc-table-wrap" wire:loading.class="is-loading">
                <table class="vc-table">
                    <thead>
                        <tr>
                            <th>{{ __('students.code') }}</th>
                            <th>{{ __('students.student') }}</th>
                            <th>{{ __('students.phone') }}</th>
                            <th>{{ __('students.course') }}</th>
                            <th>{{ __('students.conversion_date') }}</th>
                            @unless ($isSalesperson)
                                <th>{{ __('students.owner') }}</th>
                            @endunless
                            <th>{{ __('students.status') }}</th>
                            <th class="text-lg-end">{{ __('students.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            @php
                                $url = route($this->routePrefix.'.students.show', $student);
                                $courseNames = $student->opportunity?->quotations
                                    ->flatMap(fn ($q) => $q->details)
                                    ->map(fn ($d) => $d->course?->course_name)
                                    ->filter()->unique()->values() ?? collect();
                            @endphp
                            <tr wire:key="student-{{ $student->student_id }}" onclick="window.location='{{ $url }}'">
                                <td data-label="{{ __('students.code') }}">
                                    <a href="{{ $url }}" class="vc-mono" onclick="event.stopPropagation()">{{ $student->student_id }}</a>
                                </td>
                                <td data-label="{{ __('students.student') }}">
                                    <span class="st-name">{{ $student->full_name }}</span>
                                    @if ($student->email)
                                        <span class="st-sub">{{ $student->email }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ __('students.phone') }}" class="st-nowrap">{{ $student->phone }}</td>
                                <td data-label="{{ __('students.course') }}">
                                    @forelse ($courseNames as $name)
                                        <span class="st-course">{{ $name }}</span>
                                    @empty
                                        <span class="st-sub">—</span>
                                    @endforelse
                                </td>
                                <td data-label="{{ __('students.conversion_date') }}" class="st-nowrap">
                                    {{ $student->conversion_date?->format('d/m/Y') }}
                                </td>
                                @unless ($isSalesperson)
                                    <td data-label="{{ __('students.owner') }}">{{ $student->opportunity?->employee?->full_name ?? '—' }}</td>
                                @endunless
                                <td data-label="{{ __('students.status') }}">
                                    <span class="vc-badge st-tone--{{ $student->status }}">
                                        <span class="vc-badge__dot"></span>{{ __('students.status_name.'.$student->status) }}
                                    </span>
                                </td>
                                <td data-label="{{ __('students.actions') }}" class="text-lg-end">
                                    <a href="{{ $url }}" class="vc-icon-btn" onclick="event.stopPropagation()"
                                        title="{{ __('students.view') }}" aria-label="{{ __('students.view') }} {{ $student->student_id }}">
                                        <x-icon name="eye" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($students->hasPages())
                <div class="vc-pagination">{{ $students->links() }}</div>
            @endif
        @endif
    </section>
</div>
