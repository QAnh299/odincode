@php
    $money   = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
    $me      = $this->me;
    $sel     = $this->selected;
    $check   = $sel ? $sel['check'] : null;
    $lead    = $sel ? $sel['lead']  : null;
@endphp

<div class="vc ok-create">
    @assets
        @vite(['resources/css/opportunity.css'])
    @endassets

    {{-- ── Breadcrumb ────────────────────────────────────────────── --}}
    <nav class="vc-crumb" aria-label="breadcrumb">
        <a href="{{ route('salesperson.opportunities') }}" class="odin-back">
            <x-icon name="arrow-left" />{{ __('opportunities.create.back') }}
        </a>
        <span class="vc-crumb__path">
            <a href="{{ route('salesperson.opportunities') }}">{{ __('opportunities.title') }}</a>
            <x-icon name="chevron" />
            <span aria-current="page">{{ __('opportunities.create.title') }}</span>
        </span>
    </nav>

    <h1 class="vc-page-title">{{ __('opportunities.create.title') }}</h1>
    <p class="vc-page-sub">{{ __('opportunities.create.subtitle') }}</p>

    {{-- ── Lỗi chung (chặn khi confirm) ──────────────────────────── --}}
    @error('general')
        <div class="ok-create__alert ok-create__alert--danger" role="alert">
            <x-icon name="alert" />
            <span>{{ $message }}</span>
        </div>
    @enderror

    <div class="ok-create__layout">

        {{-- ── Cột trái: tìm kiếm ─────────────────────────────────── --}}
        <section class="vc-card ok-create__search-panel">
            <label class="vc-field">
                <span class="vc-field__label">{{ __('opportunities.create.search_label') }}</span>
                <div class="vc-search">
                    <x-icon name="search" />
                    <input type="search"
                        wire:model.live.debounce.350ms="search"
                        placeholder="{{ __('opportunities.create.search_placeholder') }}"
                        aria-label="{{ __('opportunities.create.search_label') }}"
                        autofocus>
                    <span class="vc-search__spin" wire:loading.delay wire:target="search">
                        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    </span>
                </div>
            </label>

            @php $results = $this->results; @endphp

            @if (trim($search) !== '')
                @if ($results->isEmpty())
                    <p class="ok-create__empty">{{ __('opportunities.create.no_results') }}</p>
                    <p class="ok-create__empty-hint">{{ __('opportunities.create.no_results_hint') }}</p>
                @else
                    <p class="ok-create__count">{{ __('opportunities.create.result_count', ['count' => $results->count()]) }}</p>

                    <div class="vc-table-wrap">
                        <table class="vc-table">
                            <thead>
                                <tr>
                                    <th>{{ __('opportunities.create.col_customer') }}</th>
                                    <th>{{ __('opportunities.create.col_phone') }}</th>
                                    <th>{{ __('opportunities.create.col_status') }}</th>
                                    <th>{{ __('opportunities.create.col_action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($results as $row)
                                    @php
                                        $rowCheck  = $row['check'];
                                        $rowState  = $rowCheck['state'];      // ok | warning | blocked
                                        $rowBlock  = $rowCheck['blocks'][0] ?? null;
                                        $isSelected = $selectedLeadId === $row['lead_id'];
                                    @endphp
                                    <tr wire:key="row-{{ $row['lead_id'] }}" class="{{ $isSelected ? 'is-selected' : '' }}">
                                        <td>
                                            <span class="ok-create__name">{{ $row['full_name'] }}</span>
                                        </td>
                                        <td class="text-nowrap">{{ $row['phone'] ?? '-' }}</td>
                                        <td>
                                            @if ($rowState === 'blocked')
                                                @php $b = $rowBlock; @endphp
                                                <span class="ok-create__badge ok-create__badge--blocked">
                                                    @if ($b && $b['type'] === 'own_in_progress')
                                                        {{ __('opportunities.create.status_own_in_progress') }}
                                                    @elseif ($b && $b['type'] === 'other_in_progress')
                                                        {{ __('opportunities.create.status_other_in_progress', ['name' => $b['owner_name']]) }}
                                                    @else
                                                        {{ __('opportunities.create.status_in_queue') }}
                                                    @endif
                                                </span>
                                            @elseif ($rowState === 'warning')
                                                <span class="ok-create__badge ok-create__badge--warning">
                                                    {{ __('opportunities.create.status_warning') }}
                                                </span>
                                            @else
                                                <span class="ok-create__badge ok-create__badge--ok">
                                                    {{ __('opportunities.create.status_ok') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <button type="button"
                                                class="vc-btn vc-btn--sm {{ $isSelected ? 'vc-btn--ghost' : 'vc-btn--outline' }}"
                                                wire:click="selectLead('{{ $row['lead_id'] }}')">
                                                {{ __('opportunities.create.btn_select') }}
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </section>

        {{-- ── Cột phải: thông tin khách đang chọn ───────────────── --}}
        @if ($sel && $lead)
            <section class="vc-card ok-create__confirm-panel" wire:key="confirm-{{ $lead->lead_id }}">
                <h2 class="ok-create__confirm-title">
                    {{ __('opportunities.create.selected_title', ['name' => $lead->full_name]) }}
                </h2>
                <p class="ok-create__confirm-phone">{{ $lead->phone }}</p>

                {{-- Thông báo chặn --}}
                @foreach ($check['blocks'] as $block)
                    <div class="ok-create__alert ok-create__alert--danger" role="alert">
                        <x-icon name="alert" />
                        <span>
                            @if ($block['type'] === 'own_in_progress')
                                {{ __('opportunities.create.blocked_own_in_progress', ['opp' => $block['opportunity_id']]) }}
                                <a href="{{ route('salesperson.opportunities.show', $block['opportunity_id']) }}"
                                    class="ok-create__link">
                                    {{ __('opportunities.create.view_active_opp') }}
                                </a>
                            @elseif ($block['type'] === 'other_in_progress')
                                {{ __('opportunities.create.blocked_other_in_progress', [
                                    'name'  => $block['owner_name'],
                                    'phone' => $block['owner_phone'] ?? __('opportunities.create.no_phone'),
                                ]) }}
                            @else
                                {{ __('opportunities.create.blocked_in_queue') }}
                            @endif
                        </span>
                    </div>
                @endforeach

                {{-- Cảnh báo không chặn --}}
                @foreach ($check['warnings'] as $warning)
                    <div class="ok-create__alert ok-create__alert--warning" role="alert">
                        <x-icon name="alert" />
                        <div>
                            @if ($warning['type'] === 'studying')
                                <span>{{ __('opportunities.create.warning_studying', ['courses' => implode(', ', $warning['courses'])]) }}</span>
                            @elseif ($warning['type'] === 'debt')
                                <span>{{ __('opportunities.create.warning_debt') }}</span>
                                <ul class="ok-create__debt-list">
                                    @foreach ($warning['items'] as $item)
                                        <li>{{ __('opportunities.create.warning_debt_item', [
                                            'course'  => $item['course'],
                                            'amount'  => $money($item['remaining']),
                                        ]) }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endforeach

                {{-- Ghi chú "vẫn có thể tạo" khi có cảnh báo nhưng không bị chặn --}}
                @if ($check['state'] === 'warning')
                    <p class="ok-create__warning-note">{{ __('opportunities.create.warning_note') }}</p>
                @endif

                {{-- Form cập nhật thông tin liên hệ (chỉ hiện khi không bị chặn) --}}
                @if ($check['state'] !== 'blocked')
                    <div class="ok-create__contact">
                        <p class="ok-create__contact-title">
                            {{ __('opportunities.create.contact_title') }}
                            <span class="ok-create__contact-hint">{{ __('opportunities.create.contact_hint') }}</span>
                        </p>

                        <label class="vc-field">
                            <span class="vc-field__label">{{ __('opportunities.create.contact_phone') }}</span>
                            <input type="text" wire:model="contactPhone" maxlength="10" inputmode="numeric"
                                placeholder="{{ $lead->phone ?? '' }}"
                                class="vc-control @error('contactPhone') is-invalid @enderror">
                            @error('contactPhone') <span class="ld-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="vc-field">
                            <span class="vc-field__label">{{ __('opportunities.create.contact_email') }}</span>
                            <input type="email" wire:model="contactEmail" maxlength="254"
                                placeholder="{{ $lead->email ?? __('opportunities.create.contact_unchanged') }}"
                                class="vc-control @error('contactEmail') is-invalid @enderror">
                            @error('contactEmail') <span class="ld-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="vc-field">
                            <span class="vc-field__label">{{ __('opportunities.create.contact_source_url') }}</span>
                            <input type="url" wire:model="contactSourceUrl" maxlength="2000"
                                placeholder="{{ $lead->source_url ?? __('opportunities.create.contact_unchanged') }}"
                                class="vc-control @error('contactSourceUrl') is-invalid @enderror">
                            @error('contactSourceUrl') <span class="ld-error">{{ $message }}</span> @enderror
                        </label>
                    </div>
                @endif

                {{-- Nút hành động --}}
                <div class="ok-create__actions">
                    <button type="button" class="vc-btn vc-btn--ghost" wire:click="clearSelection">
                        {{ __('opportunities.create.btn_change') }}
                    </button>

                    @if ($check['state'] !== 'blocked')
                        <button type="button"
                            class="vc-btn vc-btn--primary"
                            wire:click="confirm"
                            wire:loading.attr="disabled"
                            wire:target="confirm">
                            <span wire:loading.remove wire:target="confirm">
                                <x-icon name="check" />{{ __('opportunities.create.btn_confirm') }}
                            </span>
                            <span wire:loading wire:target="confirm">
                                {{ __('opportunities.create.btn_confirming') }}
                            </span>
                        </button>
                    @endif
                </div>
            </section>
        @endif

    </div>{{-- /.ok-create__layout --}}
</div>
