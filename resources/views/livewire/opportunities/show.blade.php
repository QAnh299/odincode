@php
    $opp = $this->opportunity;
    $lead = $opp->lead;
    $currentOrder = $opp->stage?->sort_order ?? 0;

    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
    $label = fn (string $key, string $value) => \Illuminate\Support\Facades\Lang::has("$key.$value") ? __("$key.$value") : $value;
@endphp

<div class="vc od">
    {{-- Dùng lại giao diện trang voucher (voucher.css), nhãn kênh liên hệ (lead.css) + phần riêng opportunity.css --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/lead.css', 'resources/css/opportunity.css'])
    @endassets

    {{-- ── Điều hướng ───────────────────────────────────────────────── --}}
    <nav class="vc-crumb" aria-label="breadcrumb">
        <a href="{{ route($this->routePrefix.'.opportunities') }}" class="vc-crumb__back">
            <x-icon name="arrow-left" />{{ __('opportunities.back') }}
        </a>
        <span class="vc-crumb__path">
            <a href="{{ route($this->routePrefix.'.opportunities') }}">{{ __('opportunities.title') }}</a>
            <x-icon name="chevron" />
            <span aria-current="page">{{ $opp->opportunity_id }}</span>
        </span>
    </nav>

    {{-- ── Đầu trang: tên khách, nút Tạo báo giá + thanh giai đoạn ───── --}}
    <section class="vc-card od-head">
        <div class="od-head__top">
            <div class="od-head__title">
                <span class="vc-mono">{{ $opp->opportunity_id }}</span>
                <h1>{{ $lead?->full_name ?? '—' }}</h1>
                {{-- Stage Lưu trữ: Từ chối (Lost) / Lưu trữ (Archived), kèm lý do --}}
                @if (in_array($opp->status, [\App\Models\Opportunity::STATUS_LOST, \App\Models\Opportunity::STATUS_ARCHIVED], true))
                    <span class="vc-badge ok-status ok-status--{{ $opp->status }}">
                        <span class="vc-badge__dot"></span>{{ __('opportunities.status_name.'.$opp->status) }}
                    </span>
                    @if ($this->closeReason)
                        <span class="od-muted">{{ $this->closeReason }}</span>
                    @endif
                @endif
                {{-- HĐ11: nhãn tính ra từ báo giá Nháp còn hạn (không lưu trong CSDL) --}}
                @if ($this->undecidedDays !== null)
                    <span class="vc-badge od-undecided"><x-icon name="hourglass" />{{ __('opportunities.undecided', ['days' => $this->undecidedDays]) }}</span>
                @endif
                @unless ($this->isOwner())
                    <span class="od-readonly"><x-icon name="info" />{{ __('opportunities.read_only') }}</span>
                @endunless
            </div>

            @if ($this->canCreateQuotation())
                <button type="button" class="vc-btn vc-btn--primary" wire:loading.attr="disabled" wire:target="createQuotation"
                    x-on:click="$dispatch('odin-confirm', { title: @js(__('opportunities.confirm_quote_title')), message: @js(__('opportunities.confirm_quote')), ok: @js(__('opportunities.create_quotation')), run: () => $wire.createQuotation() })">
                    <x-icon name="file" />{{ __('opportunities.create_quotation') }}
                </button>
            @elseif ($this->canStartQuotation())
                <a href="{{ route('salesperson.quotations.create', $opp) }}" class="vc-btn vc-btn--primary">
                    <x-icon name="file" />{{ __('opportunities.create_quotation') }}
                </a>
            @endif
        </div>

        {{-- Thanh giai đoạn kiểu Odoo: stage đã qua / hiện tại / chưa tới --}}
        <ol class="od-stages" aria-label="{{ __('opportunities.stage') }}">
            @foreach ($this->stages as $stage)
                @php
                    $state = match (true) {
                        $stage->stage_id === $opp->stage_id => 'current',
                        $stage->sort_order < $currentOrder  => 'done',
                        default                              => 'todo',
                    };
                @endphp
                <li class="od-stages__item is-{{ $state }}" title="{{ $stage->description }}"
                    @if ($state === 'current') aria-current="step" @endif>
                    @if ($state === 'done')
                        <x-icon name="check" />
                    @endif
                    {{ $stage->stage_name }}
                </li>
            @endforeach
        </ol>
    </section>

    <div class="od-grid">
        <div class="od-main">
            @php
                $cares = $this->careResults;
                $appointments = $this->appointments;
                $nextActivity = $this->nextActivity;
                $canLog = $this->canLogCare();
                $rebookTypes = $this->rebookTypes;
                $reschedulable = $this->reschedulable;
            @endphp

            <section class="vc-card vc-results">
                <div class="od-tabs" role="tablist">
                    @foreach (['care' => $cares->count(), 'appointments' => $appointments->count()] as $key => $count)
                        <button type="button" role="tab" wire:click="setTab('{{ $key }}')"
                            class="od-tab {{ $tab === $key ? 'is-active' : '' }}"
                            aria-selected="{{ $tab === $key ? 'true' : 'false' }}">
                            {{ __('opportunities.tab.'.$key) }}
                            <span class="od-tab__count">{{ $count }}</span>
                        </button>
                    @endforeach

                    <div class="od-tabs__actions">
                        @if ($tab === 'care' && $this->needsContact())
                            <button type="button" class="vc-btn vc-btn--primary" wire:click="startCare" @disabled(! $canLog)
                                title="{{ $this->careLockHint() }}">
                                <x-icon name="phone" />{{ $nextActivity ? __('opportunities.care_log', ['activity' => $nextActivity->activity_name]) : __('opportunities.care_log_short') }}
                            </button>
                        @elseif ($tab === 'appointments' && $this->canCreateAppointment())
                            <button type="button" class="vc-btn vc-btn--ghost {{ $form === 'reschedule' ? 'is-on' : '' }}"
                                wire:click="startReschedule" @disabled(! $reschedulable)
                                title="{{ $reschedulable ? '' : __('opportunities.apt_hint_no_reschedule') }}">
                                <x-icon name="calendar" />{{ __('opportunities.reschedule') }}
                            </button>
                            <button type="button" class="vc-btn vc-btn--primary"
                                wire:click="startCreate" @disabled(! $rebookTypes)
                                title="{{ $rebookTypes ? '' : __('opportunities.apt_hint_no_create') }}">
                                <x-icon name="plus" />{{ __('opportunities.create_appointment') }}
                            </button>
                        @endif
                    </div>
                </div>

                <div class="od-tabpanel" role="tabpanel" wire:loading.class="is-loading" wire:target="setTab, markAppointment, startCreate, startReschedule, startCare">
                    @if ($notice)
                        <div class="ld-notice ld-notice--{{ $notice['tone'] }} od-notice" role="status">
                            <x-icon :name="$notice['tone'] === 'danger' ? 'alert' : 'check-circle'" />
                            <span class="ld-notice__text">{{ $notice['text'] }}</span>
                            <button type="button" class="ld-notice__close" wire:click="dismissNotice" aria-label="{{ __('leads.close') }}">
                                <x-icon name="x" />
                            </button>
                        </div>
                    @endif

                    @if ($tab === 'appointments')
                        {{-- Form tạo lịch hẹn: chỉ các loại cần hẹn (lần đầu / hẹn lại sau khi khách không đến) --}}
                        @if ($form === 'create')
                            <form class="od-create" wire:submit="saveAppointment">
                                <h3 class="od-create__title">{{ __('opportunities.create_appointment') }}</h3>

                                @include('livewire.opportunities.partials.appointment-fields', ['allowedTypes' => $rebookTypes])

                                <div class="od-resched__actions">
                                    <button type="button" class="vc-btn vc-btn--ghost" wire:click="closeForm">{{ __('leads.cancel') }}</button>
                                    <button type="submit" class="vc-btn vc-btn--primary" wire:loading.attr="disabled" wire:target="saveAppointment">
                                        <x-icon name="check" />{{ __('opportunities.save_appointment') }}
                                    </button>
                                </div>
                            </form>
                        @endif

                        @if ($form === 'reschedule')
                            <form class="od-create" wire:submit="saveReschedule">
                                <h3 class="od-create__title">{{ __('opportunities.reschedule') }}</h3>

                                <div class="vc-field od-create__wide">
                                    <p class="od-place od-place--top"><x-icon name="tag" />{{ $this->appointmentPlace ?? '—' }}</p>
                                    <span class="vc-field__label">{{ __('opportunities.apt_pick') }} <span class="ld-req">*</span></span>
                                    <div class="od-picks">
                                        @foreach ($reschedulable as $key => $optionLabel)
                                            <div class="od-pick {{ in_array($key, $editKeys, true) ? 'is-on' : '' }}" wire:key="pick-{{ $key }}">
                                                <label class="od-check">
                                                    <input type="checkbox" wire:model.live="editKeys" value="{{ $key }}">
                                                    <span>{{ $optionLabel }}</span>
                                                </label>

                                                @if (in_array($key, $editKeys, true))
                                                    <div class="od-pick__fields">
                                                        <label class="vc-field">
                                                            <span class="vc-field__label">{{ __('opportunities.new_time') }} <span class="ld-req">*</span></span>
                                                            <input type="datetime-local" wire:model="editTimes.{{ $key }}" min="{{ now()->format('Y-m-d\TH:i') }}"
                                                                class="vc-control @error('editTimes.'.$key) is-invalid @enderror">
                                                            @error('editTimes.'.$key) <span class="ld-error">{{ $message }}</span> @enderror
                                                        </label>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                    @error('editKeys') <span class="ld-error">{{ $message }}</span> @enderror
                                    @error('editKeys.*') <span class="ld-error">{{ $message }}</span> @enderror
                                </div>

                                <div class="od-resched__actions">
                                    <button type="button" class="vc-btn vc-btn--ghost" wire:click="closeForm">{{ __('leads.cancel') }}</button>
                                    <button type="submit" class="vc-btn vc-btn--primary" wire:loading.attr="disabled" wire:target="saveReschedule">
                                        <x-icon name="check" />{{ __('opportunities.save_reschedule') }}
                                    </button>
                                </div>
                            </form>
                        @endif

                        {{-- Lịch hẹn: mới nhất lên đầu; mỗi mã lịch hẹn gồm dòng Test và/hoặc Tư vấn --}}
                        @include('livewire.opportunities.partials.appointment-list', ['appointments' => $appointments, 'place' => $this->appointmentPlace])
                    @else
                        {{-- Form ghi nhận lần liên hệ tiếp theo (mở từ nút trên hàng tab) --}}
                        @if ($this->needsContact())
                            @if ($form === 'care' && $canLog)
                                <form class="od-create" wire:submit="saveCare">
                                    <h3 class="od-create__title">{{ __('opportunities.care_log', ['activity' => $nextActivity->activity_name]) }}</h3>
                                    @if ($nextActivity->notes)
                                        <p class="od-muted od-create__wide od-create__sub">{{ $nextActivity->notes }}</p>
                                    @endif

                                    <label class="vc-field">
                                        <span class="vc-field__label">{{ __('opportunities.care_time') }} <span class="ld-req">*</span></span>
                                        <input type="datetime-local" wire:model="careTime" max="{{ now()->format('Y-m-d\TH:i') }}"
                                            class="vc-control @error('careTime') is-invalid @enderror">
                                        @error('careTime') <span class="ld-error">{{ $message }}</span> @enderror
                                    </label>

                                    <div class="vc-field">
                                        <span class="vc-field__label">{{ __('opportunities.care_result_label') }} <span class="ld-req">*</span></span>
                                        <div class="od-checks od-results">
                                            @foreach (\App\Models\CareResult::RESULTS as $result)
                                                <label class="od-check od-result od-res--{{ $result }} {{ $careResult === $result ? 'is-on' : '' }}">
                                                    <input type="radio" wire:model.live="careResult" value="{{ $result }}">
                                                    <span>{{ __('opportunities.care_result.'.$result) }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @error('careResult') <span class="ld-error">{{ $message }}</span> @enderror
                                        {{-- Thất bại (khách từ chối): báo trước Opportunity sẽ đi đâu --}}
                                        @if ($careResult === \App\Models\CareResult::RESULT_FAILED)
                                            <span class="od-muted">
                                                {{ __('opportunities.care_failed_lost') }}
                                            </span>
                                        @endif
                                    </div>

                                    <label class="vc-field od-create__wide">
                                        <span class="vc-field__label">
                                            {{ __('opportunities.notes') }}
                                            @if ($careResult === \App\Models\CareResult::RESULT_FAILED) <span class="ld-req">*</span> @endif
                                        </span>
                                        <textarea wire:model="careNotes" rows="3" maxlength="1000" class="vc-control od-textarea"
                                            placeholder="{{ __('opportunities.care_notes_placeholder') }}"></textarea>
                                        @error('careNotes') <span class="ld-error">{{ $message }}</span> @enderror
                                    </label>

                                    <div class="od-resched__actions">
                                        <button type="button" class="vc-btn vc-btn--ghost" wire:click="closeForm">{{ __('leads.cancel') }}</button>
                                        <button type="submit" class="vc-btn vc-btn--primary" wire:loading.attr="disabled" wire:target="saveCare">
                                            <x-icon name="check" />{{ __('opportunities.care_save') }}
                                        </button>
                                    </div>
                                </form>
                            @endif
                        @endif

                        {{-- Lịch sử chăm sóc: tách theo hoạt động, mới nhất lên đầu --}}
                        @include('livewire.opportunities.partials.care-history', ['cares' => $cares])
                    @endif
                </div>
            </section>

            {{-- Báo giá: chỉ ở stage Chốt (stage 1-3 chưa có báo giá) --}}
            @if ($this->showQuotations())
                @php $quotations = $this->quotations; @endphp
                <section class="vc-card vc-results">
                    <h2 class="od-title od-title--bar">{{ __('opportunities.quotations') }}</h2>

                    @if ($quotations->isEmpty())
                        <p class="od-empty od-empty--pad">{{ $this->canCreateQuotation() || $this->canStartQuotation() ? __('opportunities.no_quotations_closed') : __('opportunities.no_quotations_yet') }}</p>
                    @else
                        <div class="vc-table-wrap">
                            <table class="vc-table vc-table--static">
                                <thead>
                                    <tr>
                                        <th>{{ __('opportunities.quotation_id') }}</th>
                                        <th>{{ __('opportunities.created_at') }}</th>
                                        <th>{{ __('opportunities.expiry_date') }}</th>
                                        <th>{{ __('opportunities.voucher') }}</th>
                                        <th class="text-lg-end">{{ __('opportunities.quotation_total') }}</th>
                                        <th>{{ __('opportunities.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $quoteRoute = $this->routePrefix.'.quotations.show'; $canOpen = \Illuminate\Support\Facades\Route::has($quoteRoute); @endphp
                                    @foreach ($quotations as $quo)
                                        @php
                                            $quoUrl = $canOpen ? route($quoteRoute, ['quotation' => $quo->quotation_id, 'from' => 'opportunity']) : null;
                                            $isDraft = $quo->status === \App\Models\Quotation::STATUS_DRAFT;
                                            $daysLeft = $isDraft ? $quo->daysUntilExpiry() : null;
                                        @endphp
                                        {{-- Bấm cả dòng mở chi tiết báo giá; dùng bàn phím: Tab tới mã báo giá rồi Enter --}}
                                        <tr wire:key="quo-{{ $quo->quotation_id }}" @if ($quoUrl) class="od-row-link" onclick="window.location='{{ $quoUrl }}'" @endif>
                                            <td data-label="{{ __('opportunities.quotation_id') }}">
                                                @if ($quoUrl)
                                                    <a href="{{ $quoUrl }}" class="vc-mono od-link" onclick="event.stopPropagation()"
                                                        title="{{ __('opportunities.view_quotation') }}">{{ $quo->quotation_id }}</a>
                                                @else
                                                    <span class="vc-mono">{{ $quo->quotation_id }}</span>
                                                @endif
                                            </td>
                                            <td data-label="{{ __('opportunities.created_at') }}" class="ld-nowrap">
                                                {{ $quo->created_at?->format('d/m/Y') }}
                                            </td>
                                            <td data-label="{{ __('opportunities.expiry_date') }}" class="ld-nowrap">
                                                <span class="od-quo-expiry">
                                                    {{ $quo->expiry_date?->format('d/m/Y') }}
                                                    {{-- Báo giá Nháp: số ngày còn lại / quá hạn (nhãn tính ra) --}}
                                                    @if ($isDraft)
                                                        <small @class(['od-quo-days', 'is-over' => $daysLeft < 0])>
                                                            {{ $daysLeft < 0 ? __('quotations.overdue') : __('quotations.days_left', ['days' => $daysLeft]) }}
                                                        </small>
                                                    @endif
                                                </span>
                                            </td>
                                            <td data-label="{{ __('opportunities.voucher') }}">{{ $quo->voucher_id ?? '—' }}</td>
                                            <td data-label="{{ __('opportunities.quotation_total') }}" class="text-lg-end">
                                                {{-- computed_total = tạm tính − voucher (cùng công thức trang chi tiết báo giá, Quotation::totalSql) --}}
                                                <span class="vc-value">{{ $money($quo->computed_total) }}</span>
                                            </td>
                                            <td data-label="{{ __('opportunities.status') }}">
                                                <span class="vc-badge vc-q--{{ strtolower($quo->status) }}">
                                                    <span class="vc-badge__dot"></span>{{ $label('quotations.status', $quo->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="od-muted od-foot">{{ __('opportunities.total_hint') }}</p>
                    @endif
                </section>
            @endif
        </div>

        {{-- ── Cột phải: thông tin khách hàng + cơ hội ─────────────── --}}
        <aside class="od-side">
            <section class="vc-card">
                <div class="od-title-row">
                    <h2 class="od-title">{{ __('opportunities.customer') }}</h2>
                    @if ($this->canEditLead())
                        <button type="button" class="vc-btn vc-btn--ghost od-title-row__btn" wire:click="startEditLead">
                            <x-icon name="edit" />{{ __('leads.edit') }}
                        </button>
                    @endif
                </div>
                <dl class="od-info">
                    <dt>{{ __('leads.phone') }}</dt>
                    <dd>{{ $lead?->phone ?? '—' }}</dd>
                    <dt>{{ __('opportunities.email') }}</dt>
                    <dd>{{ $lead?->email ?: '—' }}</dd>
                    <dt>{{ __('leads.contact_method') }}</dt>
                    <dd>
                        @if ($lead)
                            <span class="ld-method ld-method--{{ $lead->contact_method }}">{{ __('leads.method.'.$lead->contact_method) }}</span>
                        @else
                            —
                        @endif
                    </dd>
                    <dt>{{ __('leads.source') }}</dt>
                    <dd>
                        @if ($lead?->sourceUrlIsLink())
                            <a href="{{ $lead->source_url }}" target="_blank" rel="noopener noreferrer">{{ $lead->source_name }}</a>
                        @else
                            {{ $lead?->source_name ?? '—' }}
                            @if ($lead?->source_url)
                                · {{ $lead->source_url }}
                            @endif
                        @endif
                    </dd>
                    <dt>{{ __('leads.branch') }}</dt>
                    <dd>{{ $lead?->branch?->branch_name ?? '—' }}</dd>
                    <dt>{{ __('opportunities.lead') }}</dt>
                    <dd>
                        <span class="vc-mono">{{ $opp->lead_id }}</span>
                        <span class="od-muted">· {{ $lead?->created_at?->format('d/m/Y') }}</span>
                    </dd>
                </dl>
            </section>

            <section class="vc-card">
                <h2 class="od-title">{{ __('opportunities.info') }}</h2>
                <dl class="od-info">
                    <dt>{{ __('opportunities.owner') }}</dt>
                    <dd>
                        {{ $opp->employee?->full_name ?? '—' }}
                        @if ($opp->employee?->team)
                            <span class="od-muted">· {{ $opp->employee->team->team_name }}</span>
                        @endif
                    </dd>
                    <dt>{{ __('opportunities.expected_value') }}</dt>
                    <dd>
                        @if ($value = $opp->displayValue())
                            <span class="vc-value">{{ $value }}</span>
                        @else
                            {{ __('opportunities.no_value') }}
                        @endif
                    </dd>
                    <dt>{{ __('opportunities.stage') }}</dt>
                    <dd>{{ $opp->stage?->stage_name ?? '—' }}</dd>
                    <dt>{{ __('opportunities.conversion_date') }}</dt>
                    <dd>{{ $opp->conversion_date?->format('d/m/Y H:i') }}</dd>
                    @if ($opp->student)
                        <dt>{{ __('opportunities.student') }}</dt>
                        <dd>
                            <span class="vc-mono">{{ $opp->student->student_id }}</span>
                            <span class="od-muted">· {{ $label('opportunities.student_status', $opp->student->status) }}</span>
                        </dd>
                    @endif
                </dl>

                {{-- Sale Leader: chuyển người phụ trách (tới hết stage Chốt, khi khách chưa đóng tiền) - mở popup --}}
                @if ($this->reassignOptions->isNotEmpty())
                    <button type="button" class="vc-btn vc-btn--ghost od-reassign__open" wire:click="startReassign">
                        <x-icon name="users" />{{ __('opportunities.reassign') }}
                    </button>
                @endif

                {{-- Lịch sử chuyển người phụ trách --}}
                @if ($this->transfers->isNotEmpty())
                    <div class="od-transfers">
                        <h3 class="od-transfers__title">{{ __('opportunities.transfers') }}</h3>
                        <ul class="od-transfers__list">
                            @foreach ($this->transfers as $transfer)
                                <li class="od-transfers__item" wire:key="transfer-{{ $transfer->transfer_id }}">
                                    <span class="od-transfers__line">
                                        {{ __('opportunities.transfer_line', [
                                            'from' => $transfer->fromEmployee?->full_name ?? $transfer->from_employee_id,
                                            'to'   => $transfer->toEmployee?->full_name ?? $transfer->to_employee_id,
                                        ]) }}
                                    </span>
                                    <span class="od-muted od-transfers__meta">
                                        {{ __('opportunities.transfer_meta', [
                                            'time'  => $transfer->transferred_at?->format('d/m/Y H:i'),
                                            'by'    => $transfer->transferrer?->full_name ?? $transfer->transferred_by,
                                        ]) }}
                                    </span>
                                    <span class="od-transfers__reason">{{ $transfer->reason }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        </aside>
    </div>
    {{-- Popup: chuyển người phụ trách (Sale Leader) --}}
    @if ($reassigning && $this->reassignOptions->isNotEmpty())
        <div class="ld-modal" wire:key="modal-reassign" x-data x-on:keydown.escape.window="$wire.cancelReassign()">
            <div class="ld-modal__backdrop" wire:click="cancelReassign"></div>
            <form class="ld-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="od-reassign-title" wire:submit="reassign">
                <header class="ld-modal__head">
                    <span class="ld-modal__icon"><x-icon name="users" /></span>
                    <div>
                        <h2 class="ld-modal__title" id="od-reassign-title">{{ __('opportunities.reassign') }}</h2>
                        <p class="ld-modal__sub">
                            <span class="vc-mono">{{ $opp->opportunity_id }}</span> · {{ $opp->lead?->full_name }} · {{ $opp->stage?->stage_name }}
                        </p>
                    </div>
                    <button type="button" class="ld-modal__close" wire:click="cancelReassign" aria-label="{{ __('leads.close') }}">
                        <x-icon name="x" />
                    </button>
                </header>

                @php
                    // Thanh tải x/50 như popup phân công Lead (đậm = đang quản lý, sọc = cơ hội này nếu chuyển sang); 50 chỉ là ngưỡng cảnh báo
                    $max = \App\Services\LeadDistributionService::MAX_PER_SALESPERSON;
                    $loads = $this->reassignLoad;
                    $loadBar = function (int $now, int $add) use ($max) {
                        $after = $now + $add;
                        $nowPct = min(100, $now * 100 / $max);

                        return [
                            'now'  => $nowPct,
                            'add'  => min(100 - $nowPct, $add * 100 / $max),
                            'tone' => $after > $max ? 'danger' : ($after >= $max * 0.8 ? 'warn' : 'ok'),
                        ];
                    };
                    // Chữ cái đại diện + màu avatar cố định theo mã (giống popup phân công)
                    $initial = fn (?string $name) => mb_strtoupper(mb_substr(\Illuminate\Support\Str::afterLast(trim((string) $name), ' '), 0, 1));
                    $hues = [152, 200, 262, 24, 330, 45];
                    $hue = fn (string $id) => $hues[crc32($id) % count($hues)];
                    $currentLoad = $loads[$opp->employee_id] ?? 0;
                    $currentBar = $loadBar($currentLoad, 0);
                @endphp
                <div class="ld-modal__body od-reassign">
                    {{-- Người đang phụ trách --}}
                    <section>
                        <h3 class="od-reassign__label">{{ __('opportunities.reassign_current') }}</h3>
                        <div class="od-reassign__card is-current">
                            <span class="ld-dist__avatar" style="--h: {{ $hue((string) $opp->employee_id) }}">{{ $initial($opp->employee?->full_name) ?: '?' }}</span>
                            <span class="od-reassign__who">
                                <strong>{{ $opp->employee?->full_name ?? '—' }}</strong>
                                <small>{{ $opp->employee_id }}</small>
                            </span>
                            @if ($opp->employee_id)
                                <span class="ld-dist__load ld-dist__load--{{ $currentBar['tone'] }}">
                                    <span class="ld-dist__load-top">
                                        <span>{{ __('leads.distribute.load') }}</span>
                                        <strong>{{ $currentLoad }}<em>/{{ $max }}</em></strong>
                                    </span>
                                    <span class="ld-dist__bar"><span class="ld-dist__bar-now" style="width: {{ $currentBar['now'] }}%"></span></span>
                                </span>
                            @endif
                        </div>
                    </section>

                    <span class="od-reassign__arrow" aria-hidden="true"><x-icon name="chevron" /></span>

                    {{-- Salesperson nhận --}}
                    <section>
                        <h3 class="od-reassign__label">{{ __('opportunities.reassign_to') }} <span class="ld-req">*</span></h3>
                        <div class="od-reassign__list @error('newOwner') is-invalid @enderror" role="radiogroup" aria-label="{{ __('opportunities.reassign_to') }}">
                            @foreach ($this->reassignOptions as $option)
                                @php
                                    $id = $option->employee_id;
                                    $now = $loads[$id] ?? 0;
                                    $picked = $newOwner === $id;
                                    $bar = $loadBar($now, $picked ? 1 : 0);
                                @endphp
                                <label wire:key="reassign-{{ $id }}" class="od-reassign__card is-option {{ $picked ? 'is-active' : '' }}">
                                    <input type="radio" class="visually-hidden" wire:model.live="newOwner" value="{{ $id }}">
                                    <span class="ld-dist__avatar" style="--h: {{ $hue($id) }}">
                                        {{ $initial($option->full_name) }}
                                        @if ($picked)<span class="od-reassign__tick"><x-icon name="check" /></span>@endif
                                    </span>
                                    <span class="od-reassign__who">
                                        <strong>{{ $option->full_name }}</strong>
                                        <small>{{ $id }}</small>
                                    </span>
                                    <span class="ld-dist__load ld-dist__load--{{ $bar['tone'] }}" title="{{ __('leads.distribute.col_load', ['max' => $max]) }}">
                                        <span class="ld-dist__load-top">
                                            <span>{{ __('leads.distribute.load') }}</span>
                                            <strong>{{ $now }}@if ($picked)<ins>+1</ins>@endif<em>/{{ $max }}</em></strong>
                                        </span>
                                        <span class="ld-dist__bar">
                                            <span class="ld-dist__bar-now" style="width: {{ $bar['now'] }}%"></span>
                                            <span class="ld-dist__bar-add" style="width: {{ $bar['add'] }}%"></span>
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('newOwner') <span class="ld-error">{{ $message }}</span> @enderror
                    </section>

                    <label class="vc-field">
                        <span class="vc-field__label">{{ __('opportunities.reassign_reason') }} <span class="ld-req">*</span></span>
                        <textarea wire:model="transferReason" rows="3" maxlength="1000"
                            class="vc-control od-textarea @error('transferReason') is-invalid @enderror"
                            placeholder="{{ __('opportunities.reassign_reason_placeholder') }}"></textarea>
                        @error('transferReason') <span class="ld-error">{{ $message }}</span> @enderror
                    </label>
                </div>

                <footer class="ld-modal__foot">
                    <button type="button" class="vc-btn vc-btn--ghost" wire:click="cancelReassign">{{ __('leads.cancel') }}</button>
                    <button type="submit" class="vc-btn vc-btn--primary" wire:loading.attr="disabled" wire:target="reassign"
                        wire:confirm="{{ __('opportunities.reassign_confirm') }}">
                        <x-icon name="check" />{{ __('opportunities.reassign_save') }}
                    </button>
                </footer>
            </form>
        </div>
    @endif

    {{-- Popup: chuyển người phụ trách thành công --}}
    @if ($reassigned)
        <div class="ld-modal" wire:key="modal-reassigned" x-data x-on:keydown.escape.window="$wire.closeReassigned()">
            <div class="ld-modal__backdrop" wire:click="closeReassigned"></div>
            <div class="ld-modal__dialog od-success" role="alertdialog" aria-modal="true" aria-labelledby="od-reassigned-title">
                <div class="ld-modal__body od-success__body">
                    <span class="od-success__icon"><x-icon name="check-circle" /></span>
                    <h2 class="ld-modal__title" id="od-reassigned-title">{{ __('opportunities.reassigned_title') }}</h2>
                    <p class="od-success__text">
                        {!! __('opportunities.reassigned_text', [
                            'code' => '<strong>'.e($opp->opportunity_id).'</strong>',
                            'from' => '<strong>'.e($reassigned['from'] ?? '—').'</strong>',
                            'to'   => '<strong>'.e($reassigned['to']).'</strong>',
                        ]) !!}
                    </p>
                    @if ($reassigned['moved'])
                        <p class="od-muted">{{ __('opportunities.reassigned_moved', ['count' => $reassigned['moved']]) }}</p>
                    @endif
                    @if ($reassigned['quotes'] ?? 0)
                        <p class="od-muted">{{ __('opportunities.reassigned_quotes', ['count' => $reassigned['quotes']]) }}</p>
                    @endif
                </div>
                <footer class="ld-modal__foot od-success__foot">
                    <button type="button" class="vc-btn vc-btn--primary" wire:click="closeReassigned" autofocus>
                        {{ __('leads.close') }}
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- ── Popup sửa thông tin khách (Lead) ── --}}
    @if ($editingLead && $this->canEditLead())
        @include('livewire.leads.partials.edit-modal', ['lead' => $lead, 'branches' => collect()])
    @endif
</div>
