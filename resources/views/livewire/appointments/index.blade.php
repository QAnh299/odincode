@php
    $counts = $this->counts;
    $activeView = $this->activeView();
    $now = now();

    // Thẻ thống kê (bấm để lọc): [chế độ xem, icon, tone]
    $cards = [
        ['today', 'calendar', 'today'],
        ['tomorrow', 'clock', 'tomorrow'],
        ['week', 'layers', 'week'],
        ['overdue', 'alert', 'overdue'],
    ];
@endphp

<div class="vc ap">
    {{-- Dùng lại voucher.css (bảng, thẻ thống kê), opportunity.css (màu loại / trạng thái lịch hẹn) + appointment.css --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/opportunity.css', 'resources/css/appointment.css'])
    @endassets

    {{-- ── Tiêu đề ──────────────────────────────────────────────────── --}}
    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ __('appointments.title') }}</h1>
            <p class="vc-head__sub">{{ __('appointments.subtitle') }}</p>
        </div>
    </header>

    {{-- ── Thống kê nhanh (bấm để lọc) ─────────────────────────────── --}}
    <div class="vc-summary ap-summary">
        @foreach ($cards as [$key, $icon, $tone])
            <button type="button" wire:click="setView('{{ $key }}')"
                class="vc-summary__item ap-tone--{{ $tone }} {{ $activeView === $key ? 'is-current' : '' }}"
                aria-pressed="{{ $activeView === $key ? 'true' : 'false' }}">
                <span class="vc-summary__icon"><x-icon :name="$icon" /></span>
                <span class="vc-summary__text">
                    <span class="vc-summary__label">{{ __('appointments.stat.'.$key) }}</span>
                    <span class="vc-summary__value">{{ $counts[$key] }}</span>
                </span>
            </button>
        @endforeach
    </div>

    {{-- ── Lọc nhanh + tìm kiếm ─────────────────────────────────────── --}}
    <section class="vc-card ap-filters" aria-label="{{ __('appointments.filters') }}">
        <div class="vc-field ap-filters__views">
            <span class="vc-field__label">{{ __('appointments.view_label') }}</span>
            <div class="ok-presets" role="group">
                @foreach (\App\Livewire\Appointments\Index::VIEWS as $key)
                    <button type="button" wire:click="setView('{{ $key }}')"
                        class="ok-preset {{ $activeView === $key ? 'is-active' : '' }}"
                        aria-pressed="{{ $activeView === $key ? 'true' : 'false' }}">
                        {{ __('appointments.view.'.$key) }}
                    </button>
                @endforeach
            </div>
        </div>

        <label class="vc-field ap-filters__search">
            <span class="vc-field__label">{{ __('appointments.search') }}</span>
            <span class="vc-search">
                <x-icon name="search" />
                <input type="search" wire:model.live.debounce.350ms="search" placeholder="{{ __('appointments.search_placeholder') }}">
            </span>
        </label>

        <label class="vc-field ap-filters__type">
            <span class="vc-field__label">{{ __('opportunities.appointment_type') }}</span>
            <select wire:model.live="type" class="vc-control">
                <option value="">{{ __('appointments.all_types') }}</option>
                @foreach (\App\Models\Appointment::TYPES as $option)
                    <option value="{{ $option }}">{{ __('opportunities.appointment_type_name.'.$option) }}</option>
                @endforeach
            </select>
        </label>

        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('appointments.reset') }}
            </button>
        </div>
    </section>

    {{-- ── Danh sách ────────────────────────────────────────────────── --}}
    <section class="vc-card vc-results">
        <div class="vc-results__bar">
            <span>{!! __('appointments.result_count', ['count' => '<strong>'.$appointments->total().'</strong>']) !!}</span>
            <span class="vc-results__loading" wire:loading.delay>
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('appointments.loading') }}
            </span>
        </div>

        @if ($appointments->isEmpty())
            <div class="vc-empty">
                <span class="vc-empty__icon"><x-icon name="calendar" /></span>
                <p class="vc-empty__title">{{ __('appointments.empty') }}</p>
                <p class="vc-empty__sub">{{ __('appointments.empty_sub') }}</p>
            </div>
        @else
            <div class="vc-table-wrap" wire:loading.class="is-loading">
                <table class="vc-table">
                    <thead>
                        <tr>
                            <th>{{ __('appointments.time') }}</th>
                            <th>{{ __('opportunities.appointment_type') }}</th>
                            <th>{{ __('appointments.customer') }}</th>
                            <th>{{ __('appointments.opportunity') }}</th>
                            <th>{{ __('opportunities.location') }}</th>
                            <th>{{ __('opportunities.status') }}</th>
                            <th class="text-lg-end">{{ __('appointments.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($appointments as $apt)
                            @php
                                $opp = $apt->opportunity;
                                $lead = $opp?->lead;
                                $url = route('salesperson.opportunities.show', ['opportunity' => $apt->opportunity_id, 'tab' => 'appointments']);
                                $time = $apt->scheduled_time;
                                // Đã qua giờ hẹn mà chưa cập nhật kết quả
                                $status = $apt->status === 'Scheduled' && $time->lte($now) ? 'Overdue' : $apt->status;
                                $day = match (true) {
                                    $time->isToday()    => __('appointments.today'),
                                    $time->isTomorrow() => __('appointments.tomorrow'),
                                    default             => $time->format('d/m/Y'),
                                };
                            @endphp
                            <tr wire:key="apt-{{ $apt->appointment_id }}-{{ $apt->appointment_type }}" onclick="window.location='{{ $url }}'"
                                class="{{ $time->isToday() && $apt->status === 'Scheduled' ? 'ap-row--today' : '' }}">
                                <td data-label="{{ __('appointments.time') }}">
                                    <span class="ap-time">{{ $time->format('H:i') }}</span>
                                    <span class="ap-day">{{ $day }}</span>
                                </td>
                                <td data-label="{{ __('opportunities.appointment_type') }}">
                                    <span class="od-type od-type--{{ $apt->appointment_type }}">{{ __('opportunities.appointment_type_name.'.$apt->appointment_type) }}</span>
                                    <span class="ap-sub vc-mono">{{ $apt->appointment_id }}</span>
                                </td>
                                <td data-label="{{ __('appointments.customer') }}">
                                    <span class="ap-name">{{ $lead?->full_name ?? '—' }}</span>
                                    <span class="ap-sub">{{ $lead?->phone }}</span>
                                </td>
                                <td data-label="{{ __('appointments.opportunity') }}">
                                    <a href="{{ $url }}" class="vc-mono" onclick="event.stopPropagation()">{{ $apt->opportunity_id }}</a>
                                    <span class="ap-sub">{{ $opp?->stage?->stage_name }}</span>
                                </td>
                                <td data-label="{{ __('opportunities.location') }}">{{ $lead?->branch?->branch_name ?? '—' }}</td>
                                <td data-label="{{ __('opportunities.status') }}">
                                    <span class="vc-badge od-apt--{{ $status }}">
                                        <span class="vc-badge__dot"></span>{{ __('appointments.status.'.$status) }}
                                    </span>
                                </td>
                                <td data-label="{{ __('appointments.actions') }}" class="text-lg-end">
                                    <a href="{{ $url }}" class="vc-btn vc-btn--ghost ap-view" onclick="event.stopPropagation()">
                                        <x-icon name="info" />{{ __('appointments.view_detail') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($appointments->hasPages())
                <div class="vc-pagination">{{ $appointments->links() }}</div>
            @endif
        @endif
    </section>
</div>
