@php
    $course = $this->course;
    $state = $course->state();
    $overview = $this->overview;
    $monthly = $this->monthly;
    [$periodFrom, $periodTo] = $this->period;

    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';

    // Tiền rút gọn cho trục biểu đồ: 12 tr, 1,5 tỷ
    $compact = function ($value) {
        $value = (float) $value;
        [$divisor, $unit] = match (true) {
            $value >= 1e9 => [1e9, __('courses.unit_billion')],
            $value >= 1e6 => [1e6, __('courses.unit_million')],
            $value >= 1e3 => [1e3, __('courses.unit_thousand')],
            default       => [1, ''],
        };
        $number = rtrim(rtrim(number_format($value / $divisor, 1, ',', '.'), '0'), ',');

        return trim($number.' '.$unit);
    };

    // Các trường thông tin: [nhãn, icon, giá trị]; trạng thái (null) hiển thị bằng nhãn màu
    $fields = [
        [__('courses.code'), 'tag', $course->course_id],
        [__('courses.name'), 'book', $course->course_name],
        [__('courses.status'), 'check-circle', null],
        [__('courses.price'), 'wallet', $course->displayPrice()],
        [__('courses.vat'), 'percent', $course->displayVat()],
        [__('courses.duration'), 'clock', $course->duration ?: '—'],
        [__('courses.created_at'), 'calendar', $course->created_at?->format('d/m/Y H:i') ?? '—'],
    ];

    // Thẻ tổng quan kinh doanh: [nhãn, icon, giá trị, class giá trị]
    $collectedRate = $overview['revenue'] > 0 ? round($overview['paid'] / $overview['revenue'] * 100) : 0;
    $stats = [
        [__('courses.registrations'), 'file', number_format($overview['registrations'], 0, ',', '.'), ''],
        [__('courses.revenue'), 'wallet', $money($overview['revenue']), ''],
        [__('courses.paid'), 'check-circle', $money($overview['paid']), 'cs-text-paid'],
        [__('courses.remaining'), 'hourglass', $money($overview['remaining']), 'cs-text-remaining'],
        [__('courses.students'), 'layers', number_format($overview['students'], 0, ',', '.'), ''],
    ];

    // Dữ liệu biểu đồ (cũ → mới)
    $revenueBars = $monthly->map(fn ($m) => [
        'label'   => $m['month']->format('m/y'),
        'title'   => __('courses.month_label', ['month' => $m['month']->format('m/Y')]),
        'value'   => $m['revenue'],
        'display' => $money($m['revenue']),
    ])->all();

    $registrationBars = $monthly->map(fn ($m) => [
        'label'   => $m['month']->format('m/y'),
        'title'   => __('courses.month_label', ['month' => $m['month']->format('m/Y')]),
        'value'   => $m['registrations'],
        'display' => trans_choice('courses.registration_count', $m['registrations'], ['count' => $m['registrations']]),
    ])->all();
@endphp

<div class="vc cs">
    {{-- Dùng lại giao diện trang voucher (resources/css/voucher.css) + phần riêng resources/css/course.css --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/course.css'])
    @endassets

    {{-- ── Điều hướng ───────────────────────────────────────────────── --}}
    <nav class="vc-crumb" aria-label="breadcrumb">
        <a href="{{ route($this->routePrefix.'.courses') }}" class="vc-crumb__back">
            <x-icon name="arrow-left" />{{ __('courses.back') }}
        </a>
        <span class="vc-crumb__path">
            <a href="{{ route($this->routePrefix.'.courses') }}">{{ __('courses.title') }}</a>
            <x-icon name="chevron" />
            <span aria-current="page">{{ $course->course_id }}</span>
        </span>
    </nav>

    {{-- ── Tiêu đề khóa học ─────────────────────────────────────────── --}}
    <section class="vc-card cs-hero">
        <span class="cs-hero__icon"><x-icon name="book" /></span>
        <div class="cs-hero__text">
            <span class="vc-mono">{{ $course->course_id }}</span>
            <h1 class="cs-hero__title">{{ $course->course_name }}</h1>
        </div>
        <span class="vc-badge vc-badge--lg vc-tone--{{ $state }}">
            <span class="vc-badge__dot"></span>{{ __('courses.state.'.$state) }}
        </span>
    </section>

    {{-- ── 1. Thông tin khóa học (chỉ xem) ──────────────────────────── --}}
    <section class="vc-card vc-results">
        <header class="vc-section-head">
            <h2 class="vc-section-head__title">{{ __('courses.info') }}</h2>
            <p class="vc-section-head__sub"><x-icon name="shield" />{{ __('courses.read_only') }}</p>
        </header>

        <dl class="cs-info">
            @foreach ($fields as $i => [$label, $icon, $value])
                <div class="cs-info__item {{ $i === 1 ? 'cs-info__item--name' : '' }}">
                    <dt><x-icon :name="$icon" />{{ $label }}</dt>
                    <dd>
                        @if ($value === null)
                            <span class="vc-badge vc-tone--{{ $state }}">
                                <span class="vc-badge__dot"></span>{{ __('courses.state.'.$state) }}
                            </span>
                        @else
                            {{ $value }}
                        @endif
                    </dd>
                </div>
            @endforeach

            <div class="cs-info__item cs-info__item--wide">
                <dt><x-icon name="file" />{{ __('courses.description') }}</dt>
                <dd class="cs-info__desc">{{ $course->description ?: __('courses.no_description') }}</dd>
            </div>
        </dl>
    </section>

    {{-- ── 2. Tổng quan kinh doanh (toàn thời gian) ─────────────────── --}}
    <header class="cs-block-head">
        <div>
            <h2 class="vc-section-head__title">{{ __('courses.overview') }}</h2>
            <p class="vc-section-head__sub">
                @if ($this->limitedScope)
                    <x-icon name="shield" />{{ __('courses.scope_note') }}
                @else
                    {{ __('courses.overview_sub') }}
                @endif
            </p>
        </div>
    </header>

    <div class="vc-stats cs-stats">
        @foreach ($stats as [$label, $icon, $value, $class])
            <div class="vc-stat">
                <span class="vc-stat__icon"><x-icon :name="$icon" /></span>
                <span class="vc-stat__label">{{ $label }}</span>
                <span class="vc-stat__value vc-stat__value--sm {{ $class }}">{{ $value }}</span>
            </div>
        @endforeach
    </div>

    @if ($overview['revenue'] > 0)
        <div class="vc-card cs-collected">
            <div class="cs-collected__text">
                <span>{{ __('courses.collected_rate') }}</span>
                <strong>{{ $collectedRate }}%</strong>
            </div>
            <div class="cs-collected__bar" role="img"
                aria-label="{{ __('courses.collected_rate') }}: {{ $collectedRate }}%">
                <span style="width: {{ $collectedRate }}%"></span>
            </div>
        </div>
    @endif

    {{-- ── 3. Lịch sử kinh doanh theo tháng + 4. Biểu đồ ───────────── --}}
    <section class="vc-card vc-results">
        <header class="vc-section-head">
            <h2 class="vc-section-head__title">{{ __('courses.history') }}</h2>
            <p class="vc-section-head__sub">{{ __('courses.history_sub') }}</p>
        </header>

        {{-- Bộ lọc khoảng thời gian --}}
        <div class="cs-period">
            <label class="vc-field">
                <span class="vc-field__label">{{ __('courses.from_month') }}</span>
                <input type="month" wire:model.live="from" class="vc-control" placeholder="YYYY-MM"
                    max="{{ $to ?: '' }}">
            </label>
            <label class="vc-field">
                <span class="vc-field__label">{{ __('courses.to_month') }}</span>
                <input type="month" wire:model.live="to" class="vc-control" placeholder="YYYY-MM"
                    min="{{ $from ?: '' }}">
            </label>
            <div class="vc-field vc-field--action">
                <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetPeriod" @disabled(! $this->hasCustomPeriod())>
                    <x-icon name="refresh" />{{ __('courses.default_period') }}
                </button>
            </div>
            <p class="cs-period__note">
                <x-icon name="calendar" />
                {{ __('courses.period_note', ['from' => $periodFrom->format('m/Y'), 'to' => $periodTo->format('m/Y'), 'count' => $monthly->count()]) }}
                <span class="vc-results__loading" wire:loading.delay>
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    {{ __('courses.loading') }}
                </span>
            </p>
        </div>

        {{-- Biểu đồ --}}
        <div class="cs-charts" wire:loading.class="is-loading">
            <x-bar-chart :bars="$revenueBars" :title="__('courses.chart_revenue')" :format="$compact"
                :empty="__('courses.no_sales')" />
            <x-bar-chart :bars="$registrationBars" :title="__('courses.chart_registrations')" integer
                :empty="__('courses.no_sales')" />
        </div>

        {{-- Bảng theo tháng (mới → cũ) --}}
        <div class="vc-table-wrap" wire:loading.class="is-loading">
            <table class="vc-table vc-table--static cs-history">
                <thead>
                    <tr>
                        <th>{{ __('courses.month') }}</th>
                        <th class="text-lg-end">{{ __('courses.registrations') }}</th>
                        <th class="text-lg-end">{{ __('courses.revenue') }}</th>
                        <th class="text-lg-end">{{ __('courses.paid') }}</th>
                        <th class="text-lg-end">{{ __('courses.remaining') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($monthly->reverse() as $row)
                        <tr wire:key="month-{{ $row['month']->format('Y-m') }}" class="{{ $row['registrations'] ? '' : 'is-empty' }}">
                            <td data-label="{{ __('courses.month') }}">
                                <strong>{{ $row['month']->format('m/Y') }}</strong>
                            </td>
                            <td data-label="{{ __('courses.registrations') }}" class="text-lg-end">
                                {{ number_format($row['registrations'], 0, ',', '.') }}
                            </td>
                            <td data-label="{{ __('courses.revenue') }}" class="text-lg-end">
                                {{ $money($row['revenue']) }}
                            </td>
                            <td data-label="{{ __('courses.paid') }}" class="text-lg-end cs-text-paid">
                                {{ $money($row['paid']) }}
                            </td>
                            <td data-label="{{ __('courses.remaining') }}" class="text-lg-end cs-text-remaining">
                                {{ $money($row['remaining']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td data-label="{{ __('courses.month') }}"><strong>{{ __('courses.total') }}</strong></td>
                        <td data-label="{{ __('courses.registrations') }}" class="text-lg-end">
                            {{ number_format($monthly->sum('registrations'), 0, ',', '.') }}
                        </td>
                        <td data-label="{{ __('courses.revenue') }}" class="text-lg-end">{{ $money($monthly->sum('revenue')) }}</td>
                        <td data-label="{{ __('courses.paid') }}" class="text-lg-end cs-text-paid">{{ $money($monthly->sum('paid')) }}</td>
                        <td data-label="{{ __('courses.remaining') }}" class="text-lg-end cs-text-remaining">{{ $money($monthly->sum('remaining')) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</div>
