@php
    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
@endphp

<div class="vc ok">
    {{-- Dùng lại giao diện trang voucher (resources/css/voucher.css) + phần riêng resources/css/opportunity.css --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/opportunity.css'])
    @endassets

    {{-- ── Tiêu đề ──────────────────────────────────────────────────── --}}
    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ __('opportunities.title') }}</h1>
            <p class="vc-head__sub">{{ __('opportunities.subtitle') }}</p>
        </div>
        <p class="ok-total">
            {!! __('opportunities.result_count', ['count' => '<strong>'.$total.'</strong>']) !!}
        </p>
    </header>

    {{-- ── Bộ lọc theo ngày chuyển đổi (ngày Lead được chia) ────────── --}}
    @php $activePreset = $this->activePreset(); @endphp
    <section class="vc-card ok-filter" aria-label="{{ __('opportunities.filter_title') }}">
        <div class="vc-field">
            <span class="vc-field__label">{{ __('opportunities.conversion_date') }}</span>
            <div class="ok-presets" role="group">
                @foreach (['', ...\App\Livewire\Opportunities\Kanban::PRESETS] as $preset)
                    <button type="button" wire:click="applyPreset('{{ $preset }}')"
                        class="ok-preset {{ $activePreset === $preset ? 'is-active' : '' }}"
                        aria-pressed="{{ $activePreset === $preset ? 'true' : 'false' }}">
                        {{ __('opportunities.preset.'.($preset ?: 'all')) }}
                    </button>
                @endforeach
            </div>
        </div>

        <label class="vc-field">
            <span class="vc-field__label">{{ __('opportunities.from') }}</span>
            <input type="date" wire:model.live="from" class="vc-control" max="{{ $to ?: '' }}">
        </label>

        <label class="vc-field">
            <span class="vc-field__label">{{ __('opportunities.to') }}</span>
            <input type="date" wire:model.live="to" class="vc-control" min="{{ $from ?: '' }}">
        </label>

        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('opportunities.reset') }}
            </button>
        </div>
    </section>

    {{-- ── Bảng Kanban: mỗi cột một Stage ───────────────────────────── --}}
    <div class="ok-board" wire:loading.class="is-loading">
        @foreach ($this->stages as $stage)
            @php $cards = $columns->get($stage->stage_id, collect()); @endphp

            {{-- Màu viền theo thứ tự stage: .ok-col--1 … .ok-col--5 (opportunity.css) --}}
            <section class="ok-col ok-col--{{ $stage->sort_order }}" wire:key="stage-{{ $stage->stage_id }}" aria-labelledby="stage-{{ $stage->stage_id }}">
                <header class="ok-col__head" title="{{ $stage->description }}">
                    <div class="ok-col__title">
                        <h2 id="stage-{{ $stage->stage_id }}">{{ $stage->stage_name }}</h2>
                        <span class="ok-col__count">{{ $cards->count() }}</span>
                    </div>

                    <span class="ok-col__sum">{{ $money($cards->sum('expected_value')) }}</span>
                </header>

                <div class="ok-col__body">
                    @forelse ($cards as $opp)
                        <a href="{{ route('salesperson.opportunities.show', $opp) }}" class="ok-card" wire:key="opp-{{ $opp->opportunity_id }}">
                            <span class="ok-card__name">{{ $opp->lead?->full_name ?? '—' }}</span>

                            <span class="ok-card__value">
                                {{ $opp->displayValue() ?? __('opportunities.no_value') }}
                            </span>

                            <ul class="ok-card__meta">
                                @if ($opp->lead?->phone)
                                    <li><x-icon name="phone" />{{ $opp->lead->phone }}</li>
                                @endif
                                @if ($opp->lead)
                                    <li>
                                        <x-icon name="message" />
                                        {{ __('leads.method.'.$opp->lead->contact_method) }} · {{ $opp->lead->source_name }}
                                    </li>
                                @endif
                            </ul>

                            <div class="ok-card__foot">
                                <span class="vc-mono">{{ $opp->opportunity_id }}</span>
                                <span class="ok-card__date" title="{{ __('opportunities.conversion_date') }}">
                                    <x-icon name="calendar" />{{ $opp->conversion_date?->format('d/m/Y') }}
                                </span>
                            </div>
                        </a>
                    @empty
                        <p class="ok-col__empty">{{ __('opportunities.empty_stage') }}</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</div>
