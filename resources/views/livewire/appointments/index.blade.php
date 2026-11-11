@php
    $counts = $this->counts;

    // Thẻ thống kê (bấm để lọc theo trạng thái): [khoá đếm, giá trị lọc, tone]
    $cards = [
        ['total', '', 'total'],
        ['Scheduled', 'Scheduled', 'scheduled'],
        ['Success', 'Success', 'success'],
        ['Failed', 'Failed', 'failed'],
    ];

    // Bộ lọc + trang đang xem, gửi sang chi tiết cơ hội để nút Quay lại / breadcrumb "Lịch hẹn" về đúng danh sách
    $listQuery = http_build_query(array_filter([
        'q'      => $search,
        'status' => $status,
        'period' => $period,
        'page'   => $appointments->currentPage() > 1 ? $appointments->currentPage() : null,
    ]));
    $detailUrl = fn ($apt) => route('salesperson.opportunities.show', array_filter([
        'opportunity' => $apt->opportunity_id,
        'tab'         => 'appointments',
        'apt'         => $apt->appointment_id.'-'.$apt->appointment_type,
        'from'        => 'appointments',
        'list'        => $listQuery,
    ]));
    $dayLabel = fn ($time) => match (true) {
        $time->isToday()    => __('appointments.today'),
        $time->isTomorrow() => __('appointments.tomorrow'),
        default             => null,
    };
@endphp

<div class="vc ap">
    {{-- voucher.css (bảng, thẻ), lead.css (popup, thông báo), opportunity.css (màu loại / trạng thái lịch hẹn) + appointment.css --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/lead.css', 'resources/css/opportunity.css', 'resources/css/appointment.css'])
    @endassets

    <h1 class="visually-hidden">{{ __('appointments.title') }}</h1>

    {{-- ── Thống kê (bấm để lọc trạng thái) ─────────────────────────── --}}
    <div class="ap-stats">
        @foreach ($cards as [$key, $filter, $tone])
            <button type="button" wire:click="setStatus('{{ $filter }}')"
                class="ap-stat ap-stat--{{ $tone }} {{ $status === $filter ? 'is-current' : '' }}"
                aria-pressed="{{ $status === $filter ? 'true' : 'false' }}">
                <span class="ap-stat__label">{{ __('appointments.stat.'.$key) }}</span>
                <span class="ap-stat__value">{{ $counts[$key] }}</span>
            </button>
        @endforeach
    </div>

    {{-- ── Tìm kiếm + lọc ──────────────────────────────────────────── --}}
    <section class="ap-toolbar" aria-label="{{ __('appointments.filters') }}">
        <label class="ap-search">
            <x-icon name="search" />
            <input type="search" wire:model.live.debounce.350ms="search" placeholder="{{ __('appointments.search_placeholder') }}"
                aria-label="{{ __('appointments.search_placeholder') }}">
        </label>

        <select wire:model.live="status" class="vc-control ap-select" aria-label="{{ __('appointments.all_statuses') }}">
            <option value="">{{ __('appointments.all_statuses') }}</option>
            @foreach (\App\Livewire\Appointments\Index::STATUSES as $option)
                <option value="{{ $option }}">{{ __('appointments.status.'.$option) }}</option>
            @endforeach
        </select>

        <select wire:model.live="period" class="vc-control ap-select" aria-label="{{ __('appointments.all_periods') }}">
            <option value="">{{ __('appointments.all_periods') }}</option>
            @foreach (\App\Livewire\Appointments\Index::PERIODS as $option)
                <option value="{{ $option }}">{{ __('appointments.period.'.$option) }}</option>
            @endforeach
        </select>

        @if ($this->hasFilters())
            <button type="button" class="ap-reset" wire:click="resetFilters" title="{{ __('appointments.reset') }}" aria-label="{{ __('appointments.reset') }}">
                <x-icon name="refresh" />
            </button>
        @endif
    </section>

    {{-- ── Thông báo sau thao tác ─────────────────────────────────── --}}
    @if ($notice)
        <div class="ld-notice ld-notice--{{ $notice['tone'] }} ap-notice" role="status" wire:key="notice-{{ md5($notice['text']) }}">
            <x-icon :name="$notice['tone'] === 'danger' ? 'alert' : 'check-circle'" />
            <span class="ld-notice__text">{{ $notice['text'] }}</span>
            <button type="button" class="ld-notice__close" wire:click="dismissNotice" aria-label="{{ __('leads.close') }}">
                <x-icon name="x" />
            </button>
        </div>
    @endif

    {{-- ── Danh sách ────────────────────────────────────────────────── --}}
    <section class="vc-card ap-list">
        <span class="ap-loading" wire:loading.delay wire:target="search, status, period, setStatus, resetFilters, gotoPage, nextPage, previousPage">
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>{{ __('appointments.loading') }}
        </span>

        @if ($appointments->isEmpty())
            <div class="vc-empty">
                <span class="vc-empty__icon"><x-icon name="calendar" /></span>
                <p class="vc-empty__title">{{ __('appointments.empty') }}</p>
                <p class="vc-empty__sub">{{ __('appointments.empty_sub') }}</p>
            </div>
        @else
            <div class="vc-table-wrap" wire:loading.class="is-loading" wire:target="search, status, period, setStatus, resetFilters, gotoPage, nextPage, previousPage">
                <table class="vc-table ap-table">
                    <thead>
                        <tr>
                            <th>{{ __('appointments.customer') }}</th>
                            <th>{{ __('appointments.scheduled_time') }}</th>
                            <th>{{ __('appointments.type') }}</th>
                            <th>{{ __('appointments.location') }}</th>
                            <th>{{ __('opportunities.status') }}</th>
                            <th class="ap-table__actions">{{ __('appointments.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($appointments as $apt)
                            @php
                                $lead = $apt->opportunity?->lead;
                                $url = $detailUrl($apt);
                                $time = $apt->scheduled_time;
                                $day = $dayLabel($time);
                                $pending = $apt->status === 'Scheduled';
                                $args = "'{$apt->appointment_id}', '{$apt->appointment_type}'";
                            @endphp
                            <tr wire:key="apt-{{ $apt->appointment_id }}-{{ $apt->appointment_type }}"
                                class="ap-row {{ $pending && $time->isToday() ? 'ap-row--today' : '' }}"
                                onclick="window.location='{{ $url }}'" title="{{ __('appointments.open_detail') }}">
                                <td data-label="{{ __('appointments.customer') }}">
                                    <span class="ap-name">{{ $lead?->full_name ?? '-' }}</span>
                                    <a href="{{ $url }}" class="ap-opp" onclick="event.stopPropagation()">
                                        {{ __('appointments.opportunity_ref', ['code' => $apt->opportunity_id]) }}
                                    </a>
                                </td>
                                <td data-label="{{ __('appointments.scheduled_time') }}">
                                    <span class="ap-time">{{ $time->format('H:i') }}</span>
                                    <span class="ap-day">
                                        {{ $time->format('d/m/Y') }}@if ($day) · <strong>{{ $day }}</strong>@endif
                                    </span>
                                </td>
                                <td data-label="{{ __('appointments.type') }}">
                                    {{ __('opportunities.appointment_type_name.'.$apt->appointment_type) }}
                                </td>
                                <td data-label="{{ __('appointments.location') }}">{{ $lead?->branch?->branch_name ?? '-' }}</td>
                                <td data-label="{{ __('opportunities.status') }}">
                                    <span class="ap-badge ap-badge--{{ $apt->display_status }}">{{ __('appointments.badge.'.$apt->display_status) }}</span>
                                </td>
                                <td data-label="{{ __('appointments.actions') }}" class="ap-table__actions" onclick="event.stopPropagation()">
                                    <div class="ap-actions">
                                        @if ($apt->can_mark || $apt->can_change)
                                            {{-- Đổi lịch --}}
                                            @if ($apt->can_change)
                                                <button type="button" class="ap-act ap-act--reschedule" wire:click="openDialog('reschedule', {!! $args !!})"
                                                    title="{{ __('appointments.action.reschedule') }}" aria-label="{{ __('appointments.action.reschedule') }} {{ $apt->appointment_id }}">
                                                    <x-icon name="calendar" />
                                                </button>
                                            @else
                                                <span class="ap-act ap-act--blank" aria-hidden="true"></span>
                                            @endif

                                            {{-- Cập nhật kết quả: từ giờ hẹn trở đi --}}
                                            <button type="button" class="ap-act ap-act--result" wire:click="openDialog('result', {!! $args !!})"
                                                @disabled(! $apt->can_mark)
                                                title="{{ $apt->can_mark ? __('appointments.action.result') : __('appointments.action.wait') }}"
                                                aria-label="{{ __('appointments.action.result') }} {{ $apt->appointment_id }}">
                                                <x-icon name="check" />
                                            </button>

                                            {{-- Hủy lịch --}}
                                            @if ($apt->can_change)
                                                <button type="button" class="ap-act ap-act--cancel" wire:click="openDialog('cancel', {!! $args !!})"
                                                    title="{{ __('appointments.action.cancel') }}" aria-label="{{ __('appointments.action.cancel') }} {{ $apt->appointment_id }}">
                                                    <x-icon name="x-circle" />
                                                </button>
                                            @else
                                                <span class="ap-act ap-act--blank" aria-hidden="true"></span>
                                            @endif
                                        @else
                                            <span class="ap-act ap-act--blank" aria-hidden="true"></span>
                                            <a href="{{ $url }}" class="ap-act" title="{{ __('appointments.open_detail') }}" aria-label="{{ __('appointments.open_detail') }} {{ $apt->appointment_id }}">
                                                <x-icon name="eye" />
                                            </a>
                                            <span class="ap-act ap-act--blank" aria-hidden="true"></span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <footer class="ap-foot">
                <span class="ap-foot__count">
                    {{ __('appointments.showing', ['shown' => $appointments->count(), 'total' => $appointments->total()]) }}
                </span>
                @if ($appointments->hasPages())
                    @php
                        $current = $appointments->currentPage();
                        $last = $appointments->lastPage();
                        $from = max(1, min($current - 2, $last - 4));
                    @endphp
                    <nav class="ap-pager" aria-label="pagination">
                        <button type="button" class="ap-pager__btn" wire:click="previousPage" @disabled($appointments->onFirstPage())>
                            {{ __('appointments.prev') }}
                        </button>
                        @foreach (range($from, min($last, $from + 4)) as $page)
                            <button type="button" class="ap-pager__btn ap-pager__num {{ $page === $current ? 'is-active' : '' }}"
                                wire:click="gotoPage({{ $page }})" @if ($page === $current) aria-current="page" @endif>{{ $page }}</button>
                        @endforeach
                        <button type="button" class="ap-pager__btn" wire:click="nextPage" @disabled(! $appointments->hasMorePages())>
                            {{ __('appointments.next') }}
                        </button>
                    </nav>
                @endif
            </footer>
        @endif
    </section>

    {{-- ── Popup thao tác: cập nhật kết quả / đổi lịch / hủy lịch ─────── --}}
    @if ($dialog && ($row = $this->target))
        @php
            $lead = $row->opportunity?->lead;
            $typeName = __('opportunities.appointment_type_name.'.$row->appointment_type);
            $icon = ['result' => 'check-circle', 'reschedule' => 'calendar', 'cancel' => 'x-circle'][$dialog];
            $submit = ['result' => 'saveResult', 'reschedule' => 'saveReschedule', 'cancel' => 'saveCancel'][$dialog];
            $day = $dayLabel($row->scheduled_time);
        @endphp
        <div class="ld-modal" wire:key="ap-dialog-{{ $dialog }}-{{ $targetKey }}" x-data x-on:keydown.escape.window="$wire.closeDialog()">
            <div class="ld-modal__backdrop" wire:click="closeDialog"></div>
            <form class="ld-modal__dialog ap-dialog ap-dialog--{{ $dialog }}" role="dialog" aria-modal="true" aria-labelledby="ap-dialog-title" wire:submit="{{ $submit }}">
                <header class="ld-modal__head">
                    <span class="ld-modal__icon"><x-icon :name="$icon" /></span>
                    <div>
                        <h2 class="ld-modal__title" id="ap-dialog-title">{{ __('appointments.dialog.'.$dialog.'_title') }}</h2>
                        <p class="ld-modal__sub">
                            <span class="vc-mono">{{ $row->appointment_id }}</span> · {{ $typeName }}
                        </p>
                    </div>
                    <button type="button" class="ld-modal__close" wire:click="closeDialog" aria-label="{{ __('appointments.dialog.close') }}">
                        <x-icon name="x" />
                    </button>
                </header>

                <div class="ld-modal__body ap-dialog__body">
                    {{-- Tóm tắt lịch hẹn --}}
                    <dl class="ap-brief">
                        <div>
                            <dt>{{ __('appointments.customer') }}</dt>
                            <dd>
                                <strong>{{ $lead?->full_name ?? '-' }}</strong>
                                @if ($lead?->phone)<span class="ap-brief__sub">{{ $lead->phone }}</span>@endif
                            </dd>
                        </div>
                        <div>
                            <dt>{{ __('appointments.scheduled_time') }}</dt>
                            <dd>
                                <strong>{{ $row->scheduled_time->format('H:i') }}</strong>
                                <span class="ap-brief__sub">{{ $row->scheduled_time->format('d/m/Y') }}@if ($day) · {{ $day }}@endif</span>
                            </dd>
                        </div>
                        <div>
                            <dt>{{ __('appointments.location') }}</dt>
                            <dd>{{ $lead?->branch?->branch_name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('appointments.opportunity') }}</dt>
                            <dd>
                                <a href="{{ $detailUrl($row) }}" class="vc-mono">{{ $row->opportunity_id }}</a>
                                <span class="ap-brief__sub">{{ $row->opportunity?->stage?->stage_name }}</span>
                            </dd>
                        </div>
                    </dl>

                    @if ($dialog === 'result')
                        <fieldset class="ap-outcome">
                            <legend class="vc-field__label">{{ __('appointments.dialog.outcome') }} <span class="ld-req">*</span></legend>
                            <div class="ap-outcome__grid">
                                @foreach (['Success' => ['attended', 'check-circle'], 'Failed' => ['absent', 'x-circle']] as $value => [$key, $optIcon])
                                    <label class="ap-outcome__card ap-outcome__card--{{ $value }} {{ $outcome === $value ? 'is-active' : '' }}">
                                        <input type="radio" class="visually-hidden" wire:model.live="outcome" value="{{ $value }}">
                                        <span class="ap-outcome__icon"><x-icon :name="$optIcon" /></span>
                                        <span>
                                            <strong>{{ __('appointments.dialog.'.$key) }}</strong>
                                            <small>{{ __('appointments.dialog.'.$key.'_hint') }}</small>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        @if ($outcome === 'Success')
                            <label class="vc-field">
                                <span class="vc-field__label">{{ __('opportunities.apt_result') }} <span class="ld-req">*</span></span>
                                <textarea wire:model="resultNote" rows="3" maxlength="1000"
                                    class="vc-control od-textarea @error('resultNote') is-invalid @enderror"
                                    placeholder="{{ __('opportunities.apt_result_placeholder.'.$row->appointment_type) }}"></textarea>
                                @error('resultNote') <span class="ld-error">{{ $message }}</span> @enderror
                            </label>
                        @endif
                        @error('outcome') <span class="ld-error">{{ $message }}</span> @enderror
                    @elseif ($dialog === 'reschedule')
                        <label class="vc-field">
                            <span class="vc-field__label">{{ __('appointments.dialog.new_time') }} <span class="ld-req">*</span></span>
                            <input type="datetime-local" wire:model="newTime" min="{{ now()->format('Y-m-d\TH:i') }}"
                                class="vc-control @error('newTime') is-invalid @enderror">
                            @error('newTime') <span class="ld-error">{{ $message }}</span> @enderror
                        </label>
                    @else
                        <label class="vc-field">
                            <span class="vc-field__label">{{ __('opportunities.apt_cancel_reason') }} <span class="ld-req">*</span></span>
                            <textarea wire:model="cancelReason" rows="3" maxlength="500"
                                class="vc-control od-textarea @error('cancelReason') is-invalid @enderror"
                                placeholder="{{ __('opportunities.apt_cancel_placeholder') }}"></textarea>
                            @error('cancelReason') <span class="ld-error">{{ $message }}</span> @enderror
                        </label>
                        <p class="ap-dialog__hint ap-dialog__hint--warn"><x-icon name="alert" />{{ __('appointments.dialog.cancel_warning') }}</p>
                    @endif
                </div>

                <footer class="ld-modal__foot ap-dialog__foot">
                    <a href="{{ $detailUrl($row) }}" class="ap-dialog__link"><x-icon name="link" />{{ __('appointments.open_detail') }}</a>
                    <span class="ap-dialog__buttons">
                        <button type="button" class="vc-btn vc-btn--ghost" wire:click="closeDialog">{{ __('appointments.dialog.back') }}</button>
                        @php
                            $btnClass = match (true) {
                                $dialog === 'cancel' => 'od-btn--danger',
                                $dialog === 'result' && $outcome === 'Failed' => 'od-btn--danger',
                                $dialog === 'result' => 'od-btn--success',
                                default => 'vc-btn--primary',
                            };
                        @endphp
                        <button type="submit" class="vc-btn {{ $btnClass }}" wire:loading.attr="disabled" wire:target="{{ $submit }}">
                            <x-icon :name="$dialog === 'cancel' ? 'x' : 'check'" />{{ __('appointments.dialog.save_'.$dialog) }}
                        </button>
                    </span>
                </footer>
            </form>
        </div>
    @endif
</div>
