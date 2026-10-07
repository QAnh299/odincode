@php
    use App\Models\Voucher;

    $counts = $this->counts;

    // Thẻ thống kê: [key tình trạng, icon]
    $cards = [
        ['', 'layers'],
        [Voucher::STATE_ACTIVE, 'check-circle'],
        [Voucher::STATE_INACTIVE, 'pause'],
    ];
@endphp

<div class="vc">
    {{-- CSS riêng của trang voucher: resources/css/voucher.css (chỉ nạp ở trang này) --}}
    @assets
        @vite('resources/css/voucher.css')
    @endassets

    {{-- ── Tiêu đề ──────────────────────────────────────────────────── --}}
    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ __('vouchers.title') }}</h1>
            <p class="vc-head__sub">{{ __('vouchers.subtitle') }}</p>
        </div>
    </header>

    {{-- ── Thống kê nhanh (bấm để lọc theo tình trạng) ─────────────── --}}
    <div class="vc-summary">
        @foreach ($cards as [$key, $icon])
            <button type="button" wire:click="filterState('{{ $key }}')"
                class="vc-summary__item vc-tone--{{ $key ?: 'all' }} {{ $state === $key ? 'is-current' : '' }}"
                aria-pressed="{{ $state === $key ? 'true' : 'false' }}">
                <span class="vc-summary__icon"><x-icon :name="$icon" /></span>
                <span class="vc-summary__text">
                    <span class="vc-summary__label">{{ __('vouchers.state.'.($key ?: 'all')) }}</span>
                    <span class="vc-summary__value">{{ $counts[$key ?: 'all'] }}</span>
                </span>
            </button>
        @endforeach
    </div>

    {{-- ── Tra cứu: tìm kiếm + bộ lọc ───────────────────────────────── --}}
    <section class="vc-card vc-filters" aria-label="{{ __('vouchers.lookup') }}">
        <div class="vc-search">
            <x-icon name="search" />
            <input type="search" wire:model.live.debounce.350ms="search" placeholder="{{ __('vouchers.search_placeholder') }}"
                aria-label="{{ __('vouchers.search') }}">
            <span class="vc-search__spin" wire:loading.delay wire:target="search">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            </span>
        </div>

        <div class="vc-filters__grid">
            <label class="vc-field">
                <span class="vc-field__label">{{ __('vouchers.discount_type') }}</span>
                <select wire:model.live="type" class="vc-control">
                    <option value="">{{ __('vouchers.all') }}</option>
                    <option value="{{ Voucher::TYPE_PERCENTAGE }}">{{ __('vouchers.type.percentage') }}</option>
                    <option value="{{ Voucher::TYPE_FIXED }}">{{ __('vouchers.type.fixed') }}</option>
                </select>
            </label>

            <label class="vc-field">
                <span class="vc-field__label">{{ __('vouchers.status') }}</span>
                <select wire:model.live="state" class="vc-control">
                    <option value="">{{ __('vouchers.all') }}</option>
                    @foreach (Voucher::STATES as $option)
                        <option value="{{ $option }}">{{ __('vouchers.state.'.$option) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="vc-field">
                <span class="vc-field__label">{{ __('vouchers.valid_from') }}</span>
                <input type="date" wire:model.live="from" class="vc-control" max="{{ $to ?: '' }}">
            </label>

            <label class="vc-field">
                <span class="vc-field__label">{{ __('vouchers.valid_to') }}</span>
                <input type="date" wire:model.live="to" class="vc-control" min="{{ $from ?: '' }}">
            </label>

            <label class="vc-field">
                <span class="vc-field__label">{{ __('vouchers.sort_by') }}</span>
                <select wire:model.live="sort" class="vc-control">
                    @foreach (\App\Livewire\Vouchers\Index::SORTS as $option)
                        <option value="{{ $option }}">{{ __('vouchers.sort.'.$option) }}</option>
                    @endforeach
                </select>
            </label>

            <div class="vc-field vc-field--action">
                <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                    <x-icon name="refresh" />{{ __('vouchers.reset') }}
                </button>
            </div>
        </div>
    </section>

    {{-- ── Kết quả ──────────────────────────────────────────────────── --}}
    <section class="vc-card vc-results">
        <div class="vc-results__bar">
            <span>
                {!! __('vouchers.result_count', ['count' => '<strong>'.$vouchers->total().'</strong>']) !!}
            </span>
            <span class="vc-results__loading" wire:loading.delay>
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('vouchers.loading') }}
            </span>
        </div>

        @if ($vouchers->isEmpty())
            <div class="vc-empty">
                <span class="vc-empty__icon"><x-icon name="inbox" /></span>
                <p class="vc-empty__title">{{ __('vouchers.empty') }}</p>
                <p class="vc-empty__sub">{{ __('vouchers.empty_sub') }}</p>
                @if ($this->hasFilters())
                    <button type="button" class="vc-btn vc-btn--primary" wire:click="resetFilters">
                        <x-icon name="refresh" />{{ __('vouchers.reset') }}
                    </button>
                @endif
            </div>
        @else
            <div class="vc-table-wrap" wire:loading.class="is-loading">
                <table class="vc-table">
                    <thead>
                        <tr>
                            <th>{{ __('vouchers.code') }}</th>
                            <th>{{ __('vouchers.value') }}</th>
                            <th>{{ __('vouchers.validity') }}</th>
                            <th>{{ __('vouchers.status') }}</th>
                            <th class="text-center">{{ __('vouchers.used') }}</th>
                            <th><span class="visually-hidden">{{ __('vouchers.view') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vouchers as $voucher)
                            @php
                                $url = route($this->routePrefix.'.vouchers.show', $voucher);
                                $voucherState = $voucher->state();
                                $total = max(1, $voucher->start_date->diffInDays($voucher->end_date));
                                $progress = (int) round(min(100, max(0,
                                    $voucher->start_date->diffInDays(today(), false) / $total * 100)));
                            @endphp
                            <tr wire:key="voucher-{{ $voucher->voucher_id }}" onclick="window.location='{{ $url }}'">
                                <td data-label="{{ __('vouchers.code') }}">
                                    <a href="{{ $url }}" class="vc-code" onclick="event.stopPropagation()">
                                        <span class="vc-code__icon vc-type--{{ $voucher->discount_type }}">
                                            <x-icon :name="$voucher->isPercentage() ? 'percent' : 'cash'" />
                                        </span>
                                        <span>
                                            <span class="vc-code__id">{{ $voucher->voucher_id }}</span>
                                            <span class="vc-code__type">{{ __('vouchers.type.'.$voucher->discount_type) }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td data-label="{{ __('vouchers.value') }}">
                                    <span class="vc-value">{{ $voucher->displayValue() }}</span>
                                </td>
                                <td data-label="{{ __('vouchers.validity') }}">
                                    <div class="vc-period">
                                        <span>{{ $voucher->start_date->format('d/m/Y') }}</span>
                                        <x-icon name="chevron" class="vc-period__sep" />
                                        <span>{{ $voucher->end_date->format('d/m/Y') }}</span>
                                    </div>
                                    <div class="vc-meter vc-tone--{{ $voucherState }}"
                                        title="{{ __('vouchers.progress', ['percent' => $progress]) }}">
                                        <span style="width: {{ $progress }}%"></span>
                                    </div>
                                </td>
                                <td data-label="{{ __('vouchers.status') }}">
                                    <span class="vc-badge vc-tone--{{ $voucherState }}">
                                        <span class="vc-badge__dot"></span>{{ __('vouchers.state.'.$voucherState) }}
                                    </span>
                                </td>
                                <td data-label="{{ __('vouchers.used') }}" class="text-lg-center">
                                    <span class="vc-count {{ $voucher->used_count ? 'is-used' : '' }}">
                                        {{ $voucher->used_count }}
                                    </span>
                                </td>
                                <td class="vc-table__action">
                                    <a href="{{ $url }}" class="vc-icon-btn" title="{{ __('vouchers.view') }}"
                                        onclick="event.stopPropagation()">
                                        <x-icon name="chevron" />
                                        <span class="visually-hidden">{{ __('vouchers.view') }} {{ $voucher->voucher_id }}</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($vouchers->hasPages())
                <div class="vc-pagination">{{ $vouchers->links() }}</div>
            @endif
        @endif
    </section>
</div>
