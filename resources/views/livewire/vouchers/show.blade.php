@php
    $voucher = $this->voucher;
    $quotations = $this->quotations;
    $state = $voucher->state();

    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';

    // Tiến độ thời gian hiệu lực
    $totalDays = max(1, $voucher->start_date->diffInDays($voucher->end_date));
    $elapsed = $voucher->start_date->diffInDays(today(), false);
    $progress = (int) round(min(100, max(0, $elapsed / $totalDays * 100)));
    $daysLeft = (int) today()->diffInDays($voucher->end_date, false);
    $daysToStart = (int) today()->diffInDays($voucher->start_date, false);

    // Thông tin thời gian (chỉ để tham khảo, không phải trạng thái voucher)
    $inPeriod = $daysToStart <= 0 && $daysLeft >= 0;
    $timeNote = match (true) {
        $daysToStart > 0 => __('vouchers.starts_in', ['days' => $daysToStart]),
        $daysLeft < 0    => __('vouchers.ended_ago', ['days' => abs($daysLeft)]),
        $daysLeft === 0  => __('vouchers.ends_today'),
        default          => __('vouchers.days_left', ['days' => $daysLeft]),
    };

    $quotationStatus = fn (string $status) => \Illuminate\Support\Facades\Lang::has('vouchers.quotation_status.'.$status)
        ? __('vouchers.quotation_status.'.$status) : $status;
@endphp

<div class="vc">
    {{-- CSS riêng của trang voucher: resources/css/voucher.css (chỉ nạp ở trang này) --}}
    @assets
        @vite('resources/css/voucher.css')
    @endassets

    {{-- ── Điều hướng ───────────────────────────────────────────────── --}}
    <nav class="vc-crumb" aria-label="breadcrumb">
        <a href="{{ route($this->routePrefix.'.vouchers') }}" class="vc-crumb__back">
            <x-icon name="arrow-left" />{{ __('vouchers.back') }}
        </a>
        <span class="vc-crumb__path">
            <a href="{{ route($this->routePrefix.'.vouchers') }}">{{ __('vouchers.title') }}</a>
            <x-icon name="chevron" />
            <span aria-current="page">{{ $voucher->voucher_id }}</span>
        </span>
    </nav>

    {{-- ── Thẻ voucher ──────────────────────────────────────────────── --}}
    <section class="vc-ticket vc-tone--{{ $state }}">
        <div class="vc-ticket__value">
            <span class="vc-ticket__type">
                <x-icon :name="$voucher->isPercentage() ? 'percent' : 'cash'" />
                {{ __('vouchers.type.'.$voucher->discount_type) }}
            </span>
            <span class="vc-ticket__amount">{{ $voucher->displayValue() }}</span>
            <span class="vc-ticket__caption">
                {{ $voucher->isPercentage() ? __('vouchers.caption_percentage') : __('vouchers.caption_fixed') }}
            </span>
        </div>

        <div class="vc-ticket__body">
            <div class="vc-ticket__top">
                <div x-data="{ copied: false }" class="vc-ticket__code">
                    <span class="vc-ticket__label">{{ __('vouchers.code') }}</span>
                    <span class="vc-ticket__id">{{ $voucher->voucher_id }}</span>
                    <button type="button" class="vc-icon-btn"
                        @click="navigator.clipboard?.writeText(@js($voucher->voucher_id)); copied = true; setTimeout(() => copied = false, 1500)"
                        :title="copied ? @js(__('vouchers.copied')) : @js(__('vouchers.copy'))">
                        <x-icon name="copy" x-show="!copied" />
                        <x-icon name="check" x-show="copied" x-cloak />
                        <span class="visually-hidden">{{ __('vouchers.copy') }}</span>
                    </button>
                </div>
                <span class="vc-badge vc-badge--lg vc-tone--{{ $state }}">
                    <span class="vc-badge__dot"></span>{{ __('vouchers.state.'.$state) }}
                </span>
            </div>

            <div class="vc-timeline">
                <div class="vc-timeline__dates">
                    <span>
                        <span class="vc-ticket__label">{{ __('vouchers.start_date') }}</span>
                        <strong>{{ $voucher->start_date->format('d/m/Y') }}</strong>
                    </span>
                    <span class="text-end">
                        <span class="vc-ticket__label">{{ __('vouchers.end_date') }}</span>
                        <strong>{{ $voucher->end_date->format('d/m/Y') }}</strong>
                    </span>
                </div>
                <div class="vc-meter vc-meter--lg vc-tone--{{ $state }}">
                    <span style="width: {{ $progress }}%"></span>
                    @if ($inPeriod)
                        <i class="vc-meter__today" style="left: {{ $progress }}%"
                            title="{{ __('vouchers.today') }}"></i>
                    @endif
                </div>
                <div class="vc-timeline__note">
                    <x-icon name="clock" />{{ $timeNote }}
                    <span class="ms-auto">{{ __('vouchers.total_days', ['days' => $totalDays + 1]) }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Số liệu áp dụng ──────────────────────────────────────────── --}}
    @php
        $subtotal = $quotations->sum('subtotal');
        $discount = $quotations->sum('discount');
    @endphp
    <div class="vc-stats">
        <div class="vc-stat">
            <span class="vc-stat__icon"><x-icon name="file" /></span>
            <span class="vc-stat__label">{{ __('vouchers.used') }}</span>
            <span class="vc-stat__value">{{ $quotations->count() }}</span>
        </div>
        <div class="vc-stat">
            <span class="vc-stat__icon"><x-icon name="wallet" /></span>
            <span class="vc-stat__label">{{ __('vouchers.subtotal_sum') }}</span>
            <span class="vc-stat__value">{{ $money($subtotal) }}</span>
        </div>
        <div class="vc-stat">
            <span class="vc-stat__icon"><x-icon name="tag" /></span>
            <span class="vc-stat__label">{{ __('vouchers.discount_sum') }}</span>
            <span class="vc-stat__value vc-text-discount">− {{ $money($discount) }}</span>
        </div>
        <div class="vc-stat">
            <span class="vc-stat__icon"><x-icon name="calendar" /></span>
            <span class="vc-stat__label">{{ __('vouchers.validity') }}</span>
            <span class="vc-stat__value vc-stat__value--sm">{{ $timeNote }}</span>
        </div>
    </div>

    {{-- ── Báo giá đã áp dụng ───────────────────────────────────────── --}}
    <section class="vc-card vc-results">
        <header class="vc-section-head">
            <div>
                <h2 class="vc-section-head__title">{{ __('vouchers.quotations') }}</h2>
                <p class="vc-section-head__sub">
                    @if ($this->limitedScope)
                        <x-icon name="shield" />{{ __('vouchers.scope_note') }}
                    @else
                        {{ __('vouchers.quotations_sub') }}
                    @endif
                </p>
            </div>
        </header>

        @if ($quotations->isEmpty())
            <div class="vc-empty">
                <span class="vc-empty__icon"><x-icon name="file" /></span>
                <p class="vc-empty__title">{{ __('vouchers.no_quotations') }}</p>
            </div>
        @else
            <div class="vc-table-wrap">
                <table class="vc-table vc-table--static">
                    <thead>
                        <tr>
                            <th>{{ __('vouchers.quotation_id') }}</th>
                            <th>{{ __('vouchers.customer') }}</th>
                            <th>{{ __('vouchers.employee') }}</th>
                            <th>{{ __('vouchers.created_at') }}</th>
                            <th>{{ __('vouchers.status') }}</th>
                            <th class="text-lg-end">{{ __('vouchers.subtotal') }}</th>
                            <th class="text-lg-end">{{ __('vouchers.discount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quotations as $quotation)
                            <tr wire:key="quotation-{{ $quotation->quotation_id }}">
                                <td data-label="{{ __('vouchers.quotation_id') }}">
                                    <span class="vc-mono">{{ $quotation->quotation_id }}</span>
                                </td>
                                <td data-label="{{ __('vouchers.customer') }}">
                                    {{ $quotation->opportunity?->lead?->full_name ?? '—' }}
                                </td>
                                <td data-label="{{ __('vouchers.employee') }}">
                                    {{ $quotation->employee?->full_name ?? '—' }}
                                </td>
                                <td data-label="{{ __('vouchers.created_at') }}">
                                    {{ $quotation->created_at?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td data-label="{{ __('vouchers.status') }}">
                                    <span class="vc-badge vc-q--{{ \Illuminate\Support\Str::lower($quotation->status) }}">
                                        {{ $quotationStatus($quotation->status) }}
                                    </span>
                                </td>
                                <td data-label="{{ __('vouchers.subtotal') }}" class="text-lg-end">
                                    {{ $money($quotation->subtotal) }}
                                </td>
                                <td data-label="{{ __('vouchers.discount') }}" class="text-lg-end vc-text-discount">
                                    − {{ $money($quotation->discount) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
