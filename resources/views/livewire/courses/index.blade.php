@php
    use App\Livewire\Courses\Index;
    use App\Models\Course;

    $counts = $this->counts;

    $cards = [
        ['', 'layers'],
        [Course::STATE_ACTIVE, 'check-circle'],
        [Course::STATE_INACTIVE, 'pause'],
    ];

    $views = [['grid', 'grid'], ['table', 'list']];
@endphp

<div class="vc cs">
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/course.css'])
    @endassets

    <h1 class="visually-hidden">{{ __('courses.title') }}</h1>

    <div class="vc-summary">
        @foreach ($cards as [$key, $icon])
            <button type="button" wire:click="filterState('{{ $key }}')"
                class="vc-summary__item vc-tone--{{ $key ?: 'all' }} {{ $state === $key ? 'is-current' : '' }}"
                aria-pressed="{{ $state === $key ? 'true' : 'false' }}">
                <span class="vc-summary__icon"><x-icon :name="$icon" /></span>
                <span class="vc-summary__text">
                    <span class="vc-summary__label">{{ __('courses.state.'.($key ?: 'all')) }}</span>
                    <span class="vc-summary__value">{{ $counts[$key ?: 'all'] }}</span>
                </span>
            </button>
        @endforeach
    </div>

    <section class="vc-card cs-filters" aria-label="{{ __('courses.lookup') }}">
        <label class="vc-field cs-filters__search">
            <span class="vc-field__label">{{ __('courses.search') }}</span>
            <span class="vc-search">
                <x-icon name="search" />
                <input type="search" wire:model.live.debounce.350ms="search"
                    placeholder="{{ __('courses.search_placeholder') }}">
                <span class="vc-search__spin" wire:loading.delay wire:target="search">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                </span>
            </span>
        </label>

        <label class="vc-field">
            <span class="vc-field__label">{{ __('courses.status') }}</span>
            <select wire:model.live="state" class="vc-control">
                <option value="">{{ __('courses.all') }}</option>
                @foreach (Course::STATES as $option)
                    <option value="{{ $option }}">{{ __('courses.state.'.$option) }}</option>
                @endforeach
            </select>
        </label>

        <label class="vc-field">
            <span class="vc-field__label">{{ __('courses.sort') }}</span>
            <select wire:model.live="sort" class="vc-control">
                @foreach (Index::SORTS as $option)
                    <option value="{{ $option }}">{{ __('courses.sorts.'.$option) }}</option>
                @endforeach
            </select>
        </label>

        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('courses.reset') }}
            </button>
        </div>
    </section>

    <div class="cs-bar">
        <span class="cs-bar__count">
            {!! __('courses.result_count', ['count' => '<strong>'.$courses->total().'</strong>']) !!}
            <span class="cs-bar__loading" wire:loading.delay>
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('courses.loading') }}
            </span>
        </span>

        <div class="cs-switch" role="group" aria-label="{{ __('courses.view_mode') }}">
            @foreach ($views as [$value, $icon])
                <button type="button" wire:click="setView('{{ $value }}')"
                    class="cs-switch__btn {{ $view === $value ? 'is-active' : '' }}"
                    aria-pressed="{{ $view === $value ? 'true' : 'false' }}">
                    <x-icon :name="$icon" /><span>{{ __('courses.view_'.$value) }}</span>
                </button>
            @endforeach
        </div>
    </div>

    @if ($courses->isEmpty())
        <section class="vc-card">
            <div class="vc-empty">
                <span class="vc-empty__icon"><x-icon name="inbox" /></span>
                <p class="vc-empty__title">{{ __('courses.empty') }}</p>
                <p class="vc-empty__sub">{{ __('courses.empty_sub') }}</p>
                @if ($this->hasFilters())
                    <button type="button" class="vc-btn vc-btn--primary" wire:click="resetFilters">
                        <x-icon name="refresh" />{{ __('courses.reset') }}
                    </button>
                @endif
            </div>
        </section>
    @elseif ($view === 'grid')
        <div class="cs-grid" wire:loading.class="is-loading">
            @foreach ($courses as $course)
                @php $courseState = $course->state(); @endphp
                <a href="{{ route($this->routePrefix.'.courses.show', $course) }}" wire:key="course-{{ $course->course_id }}"
                    class="cs-course vc-tone--{{ $courseState }}">
                    <div class="cs-course__top">
                        <span class="cs-course__icon"><x-icon name="book" /></span>
                        <span class="vc-badge">
                            <span class="vc-badge__dot"></span>{{ __('courses.state.'.$courseState) }}
                        </span>
                    </div>

                    <span class="cs-course__code">{{ $course->course_id }}</span>
                    <h2 class="cs-course__name">{{ $course->course_name }}</h2>
                    <p class="cs-course__desc">{{ $course->description ?: __('courses.no_description') }}</p>

                    <ul class="cs-course__meta">
                        <li title="{{ __('courses.duration') }}"><x-icon name="clock" />{{ $course->duration ?: '-' }}</li>
                        <li title="{{ __('courses.vat') }}"><x-icon name="percent" />{{ __('courses.vat') }} {{ $course->displayVat() }}</li>
                    </ul>

                    <div class="cs-course__foot">
                        <span class="cs-course__price">
                            <span class="cs-course__price-label">{{ __('courses.price') }}</span>
                            <span class="cs-course__price-value">{{ $course->displayPrice() }}</span>
                        </span>
                        <span class="cs-course__go" title="{{ __('courses.view') }}">
                            <span class="visually-hidden">{{ __('courses.view') }}</span>
                            <x-icon name="arrow-right" />
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <section class="vc-card vc-results">
            <div class="vc-table-wrap" wire:loading.class="is-loading">
                <table class="vc-table">
                    <thead>
                        <tr>
                            <th>{{ __('courses.code') }}</th>
                            <th>{{ __('courses.name') }}</th>
                            <th>{{ __('courses.duration') }}</th>
                            <th class="text-lg-center">{{ __('courses.vat') }}</th>
                            <th class="text-lg-end">{{ __('courses.price') }}</th>
                            <th>{{ __('courses.status') }}</th>
                            <th class="text-lg-end">{{ __('courses.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($courses as $course)
                            @php
                                $url = route($this->routePrefix.'.courses.show', $course);
                                $courseState = $course->state();
                            @endphp
                            <tr wire:key="course-row-{{ $course->course_id }}" onclick="window.location='{{ $url }}'">
                                <td data-label="{{ __('courses.code') }}">
                                    <a href="{{ $url }}" class="vc-mono" onclick="event.stopPropagation()">{{ $course->course_id }}</a>
                                </td>
                                <td data-label="{{ __('courses.name') }}">
                                    <span class="cs-name">{{ $course->course_name }}</span>
                                    @if ($course->description)
                                        <span class="cs-name__desc">{{ $course->description }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ __('courses.duration') }}">{{ $course->duration ?: '-' }}</td>
                                <td data-label="{{ __('courses.vat') }}" class="text-lg-center">{{ $course->displayVat() }}</td>
                                <td data-label="{{ __('courses.price') }}" class="text-lg-end">
                                    <span class="vc-value cs-nowrap">{{ $course->displayPrice() }}</span>
                                </td>
                                <td data-label="{{ __('courses.status') }}">
                                    <span class="vc-badge vc-tone--{{ $courseState }}">
                                        <span class="vc-badge__dot"></span>{{ __('courses.state.'.$courseState) }}
                                    </span>
                                </td>
                                <td data-label="{{ __('courses.actions') }}" class="text-lg-end">
                                    <a href="{{ $url }}" class="vc-icon-btn" onclick="event.stopPropagation()"
                                        title="{{ __('courses.view') }}" aria-label="{{ __('courses.view') }} {{ $course->course_id }}">
                                        <x-icon name="eye" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($courses->hasPages())
        <div class="vc-pagination cs-pagination">{{ $courses->links() }}</div>
    @endif
</div>
