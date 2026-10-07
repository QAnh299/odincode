@php
    use App\Models\Course;

    $counts = $this->counts;

    // Thẻ thống kê: [key trạng thái, icon]
    $cards = [
        ['', 'layers'],
        [Course::STATE_ACTIVE, 'check-circle'],
        [Course::STATE_INACTIVE, 'pause'],
    ];
@endphp

<div class="vc cs">
    {{-- Dùng lại giao diện trang voucher (resources/css/voucher.css) + phần riêng resources/css/course.css --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/course.css'])
    @endassets

    {{-- ── Tiêu đề ──────────────────────────────────────────────────── --}}
    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ __('courses.title') }}</h1>
            <p class="vc-head__sub">{{ __('courses.subtitle') }}</p>
        </div>
    </header>

    {{-- ── Thống kê nhanh (bấm để lọc theo trạng thái) ─────────────── --}}
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

    {{-- ── Tra cứu: tìm kiếm + lọc trạng thái ───────────────────────── --}}
    <section class="vc-card cs-filters" aria-label="{{ __('courses.lookup') }}">
        <label class="vc-field">
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

        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('courses.reset') }}
            </button>
        </div>
    </section>

    {{-- ── Kết quả ──────────────────────────────────────────────────── --}}
    <section class="vc-card vc-results">
        <div class="vc-results__bar">
            <span>
                {!! __('courses.result_count', ['count' => '<strong>'.$courses->total().'</strong>']) !!}
            </span>
            <span class="vc-results__loading" wire:loading.delay>
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('courses.loading') }}
            </span>
        </div>

        @if ($courses->isEmpty())
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
        @else
            <div class="vc-table-wrap" wire:loading.class="is-loading">
                <table class="vc-table">
                    <thead>
                        <tr>
                            <th>{{ __('courses.code') }}</th>
                            <th>{{ __('courses.name') }}</th>
                            <th class="text-lg-end">{{ __('courses.price') }}</th>
                            <th class="text-lg-center">{{ __('courses.vat') }}</th>
                            <th>{{ __('courses.duration') }}</th>
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
                            {{-- Bấm cả dòng hoặc nút "Xem chi tiết" đều mở trang chi tiết (giống danh sách voucher) --}}
                            <tr wire:key="course-{{ $course->course_id }}" onclick="window.location='{{ $url }}'">
                                <td data-label="{{ __('courses.code') }}">
                                    <a href="{{ $url }}" class="vc-mono" onclick="event.stopPropagation()">{{ $course->course_id }}</a>
                                </td>
                                <td data-label="{{ __('courses.name') }}">
                                    <span class="cs-name">{{ $course->course_name }}</span>
                                </td>
                                <td data-label="{{ __('courses.price') }}" class="text-lg-end">
                                    <span class="vc-value">{{ $course->displayPrice() }}</span>
                                </td>
                                <td data-label="{{ __('courses.vat') }}" class="text-lg-center">
                                    {{ $course->displayVat() }}
                                </td>
                                <td data-label="{{ __('courses.duration') }}">
                                    {{ $course->duration ?: '—' }}
                                </td>
                                <td data-label="{{ __('courses.status') }}">
                                    <span class="vc-badge vc-tone--{{ $courseState }}">
                                        <span class="vc-badge__dot"></span>{{ __('courses.state.'.$courseState) }}
                                    </span>
                                </td>
                                <td data-label="{{ __('courses.actions') }}" class="text-lg-end">
                                    <a href="{{ $url }}" class="vc-btn vc-btn--ghost cs-view" onclick="event.stopPropagation()">
                                        <x-icon name="info" />{{ __('courses.view') }}
                                        <span class="visually-hidden">{{ $course->course_id }}</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($courses->hasPages())
                <div class="vc-pagination">{{ $courses->links() }}</div>
            @endif
        @endif
    </section>
</div>
