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
        <a href="{{ route('salesperson.opportunities') }}" class="vc-crumb__back">
            <x-icon name="arrow-left" />{{ __('opportunities.back') }}
        </a>
        <span class="vc-crumb__path">
            <a href="{{ route('salesperson.opportunities') }}">{{ __('opportunities.title') }}</a>
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
            </div>

            @if ($this->canCreateQuotation())
                {{-- Chỉ hiển thị, chưa có chức năng --}}
                <button type="button" class="vc-btn vc-btn--primary">
                    <x-icon name="file" />{{ __('opportunities.create_quotation') }}
                </button>
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
        {{-- ── Cột trái: tab Lịch sử chăm sóc / Lịch hẹn, báo giá ──── --}}
        <div class="od-main">
            @php
                $cares = $this->careResults;
                $appointments = $this->appointments;
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
                        @php
                            $rebookTypes = $this->rebookTypes;
                            $reschedulable = $this->reschedulable;
                            $canBook = $this->canCreateAppointment();
                        @endphp

                        {{-- Hàng nút: Đổi lịch – Tạo lịch hẹn (không còn ở stage Chốt / Lưu trữ) --}}
                        @if ($canBook)
                            <div class="od-apt-bar">
                                <span class="od-muted od-apt-bar__hint">
                                    @if ($rebookTypes && $appointments->isNotEmpty())
                                        {{ __('opportunities.apt_hint_rebook') }}
                                    @elseif ($appointments->isEmpty() && ! $rebookTypes)
                                        {{ __('opportunities.apt_hint_first') }}
                                    @elseif (! $rebookTypes)
                                        {{ __('opportunities.apt_hint_no_create') }}
                                    @endif
                                </span>
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
                            </div>
                        @endif

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

                        {{-- Form đổi lịch: chọn lịch muốn đổi (Test / Tư vấn) rồi nhập thời gian / địa điểm mới --}}
                        @if ($form === 'reschedule')
                            <form class="od-create" wire:submit="saveReschedule">
                                <h3 class="od-create__title">{{ __('opportunities.reschedule') }}</h3>

                                <div class="vc-field od-create__wide">
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
                                                        <label class="vc-field">
                                                            <span class="vc-field__label">{{ __('opportunities.new_location') }} <span class="ld-req">*</span></span>
                                                            <select wire:model="editLocations.{{ $key }}" class="vc-control @error('editLocations.'.$key) is-invalid @enderror">
                                                                @foreach ($this->branchOptions as $option)
                                                                    <option value="{{ $option }}">{{ $option }}</option>
                                                                @endforeach
                                                            </select>
                                                            @error('editLocations.'.$key) <span class="ld-error">{{ $message }}</span> @enderror
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
                        @forelse ($appointments as $group)
                            @php
                                $statuses = $group['rows']->pluck('status')->unique();
                                $tone = $statuses->count() === 1 ? $statuses->first() : 'Mixed';
                            @endphp
                            <article class="od-apt od-apt--{{ $tone }}" wire:key="apt-{{ $group['id'] }}">
                                <div class="od-apt__head">
                                    <strong>{{ __('opportunities.appointment_no', ['no' => $group['no']]) }}</strong>
                                    <span class="od-muted">{{ $group['id'] }}</span>
                                </div>

                                {{-- Từng loại: Test / Tư vấn – thời gian, địa điểm, kết quả riêng --}}
                                <ul class="od-apt__rows">
                                    @foreach ($group['rows'] as $row)
                                        <li class="od-apt__row" wire:key="apt-{{ $group['id'] }}-{{ $row->appointment_type }}">
                                            <div class="od-apt__type">
                                                <span class="od-type od-type--{{ $row->appointment_type }}">
                                                    {{ $label('opportunities.appointment_type_name', $row->appointment_type) }}
                                                </span>
                                                <span class="vc-badge od-apt--{{ $row->status }}">
                                                    <span class="vc-badge__dot"></span>{{ $label('opportunities.appointment_status', $row->status) }}
                                                </span>
                                            </div>

                                            <ul class="od-apt__meta">
                                                <li><x-icon name="calendar" />{{ $row->scheduled_time->format('d/m/Y H:i') }}</li>
                                                @if ($row->location)
                                                    <li><x-icon name="tag" />{{ $row->location }}</li>
                                                @endif
                                            </ul>

                                            @if ($row->notes)
                                                <p class="od-apt__note">{{ $row->notes }}</p>
                                            @endif

                                            @if ($row->can_mark)
                                                <div class="od-apt__actions">
                                                    <button type="button" class="vc-btn od-btn--success"
                                                        wire:click="markAppointment('{{ $row->appointment_id }}', '{{ $row->appointment_type }}', 'Success')"
                                                        wire:confirm="{{ __('opportunities.confirm_attended') }}">
                                                        <x-icon name="check" />{{ __('opportunities.mark_attended') }}
                                                    </button>
                                                    <button type="button" class="vc-btn od-btn--danger"
                                                        wire:click="markAppointment('{{ $row->appointment_id }}', '{{ $row->appointment_type }}', 'Failed')"
                                                        wire:confirm="{{ __('opportunities.confirm_absent') }}">
                                                        <x-icon name="x" />{{ __('opportunities.mark_absent') }}
                                                    </button>
                                                </div>
                                            @elseif ($row->status === 'Scheduled')
                                                <p class="od-muted od-apt__wait"><x-icon name="clock" />{{ __('opportunities.apt_wait_hint') }}</p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </article>
                        @empty
                            <p class="od-empty">{{ __('opportunities.no_appointments') }}</p>
                        @endforelse
                    @else
                        {{-- Ghi nhận lần liên hệ tiếp theo: chỉ khi chưa tới stage Chốt và chưa liên hệ thành công --}}
                        @if ($this->needsContact())
                            @php $nextActivity = $this->nextActivity; @endphp
                            <div class="od-apt-bar">
                                <span class="od-muted od-apt-bar__hint">
                                    {{ $nextActivity ? '' : __('opportunities.care_hint_full') }}
                                </span>
                                <button type="button" class="vc-btn vc-btn--primary" wire:click="startCare" @disabled(! $nextActivity)>
                                    <x-icon name="phone" />{{ $nextActivity ? __('opportunities.care_log', ['activity' => $nextActivity->activity_name]) : __('opportunities.care_log_short') }}
                                </button>
                            </div>

                            @if ($form === 'care' && $nextActivity)
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
                                            @foreach (['Success', 'Failed'] as $result)
                                                <label class="od-check od-result od-res--{{ $result }} {{ $careResult === $result ? 'is-on' : '' }}">
                                                    <input type="radio" wire:model.live="careResult" value="{{ $result }}">
                                                    <span>{{ __('opportunities.care_result.'.$result) }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @error('careResult') <span class="ld-error">{{ $message }}</span> @enderror
                                    </div>

                                    <label class="vc-field od-create__wide">
                                        <span class="vc-field__label">{{ __('opportunities.notes') }}</span>
                                        <textarea wire:model="careNotes" rows="3" maxlength="1000" class="vc-control od-textarea"
                                            placeholder="{{ __('opportunities.care_notes_placeholder') }}"></textarea>
                                        @error('careNotes') <span class="ld-error">{{ $message }}</span> @enderror
                                    </label>

                                    {{-- Liên hệ thành công = đã chốt lịch hẹn với khách → bắt buộc nhập lịch hẹn --}}
                                    @if ($careResult === 'Success')
                                        <fieldset class="od-create__wide od-subform">
                                            <legend class="od-subform__title">
                                                <x-icon name="calendar" />{{ __('opportunities.care_apt_title') }}
                                            </legend>
                                            <p class="od-muted od-subform__sub">{{ __('opportunities.care_apt_sub') }}</p>

                                            @include('livewire.opportunities.partials.appointment-fields', ['allowedTypes' => \App\Models\Appointment::TYPES])
                                        </fieldset>
                                    @endif

                                    <div class="od-resched__actions">
                                        <button type="button" class="vc-btn vc-btn--ghost" wire:click="closeForm">{{ __('leads.cancel') }}</button>
                                        <button type="submit" class="vc-btn vc-btn--primary" wire:loading.attr="disabled" wire:target="saveCare">
                                            <x-icon name="check" />{{ __('opportunities.care_save') }}
                                        </button>
                                    </div>
                                </form>
                            @endif
                        @endif

                        {{-- Lịch sử chăm sóc: các lần liên hệ, mới nhất lên đầu --}}
                        @forelse ($cares as $care)
                            <div class="od-timeline__item od-res--{{ $care->result }}" wire:key="care-{{ $care->result_id }}">
                                <span class="od-timeline__dot"></span>
                                <div class="od-timeline__body">
                                    <div class="od-timeline__head">
                                        <strong>{{ $care->activity?->activity_name ?? $care->activity_id }}</strong>
                                        <span class="vc-badge od-res--{{ $care->result }}">
                                            <span class="vc-badge__dot"></span>{{ __('opportunities.care_result.'.$care->result) }}
                                        </span>
                                    </div>
                                    @if ($care->notes)
                                        <p class="od-timeline__note">{{ $care->notes }}</p>
                                    @endif
                                    <span class="od-muted">{{ $care->performed_at?->format('d/m/Y H:i') }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="od-empty">{{ __('opportunities.no_care') }}</p>
                        @endforelse
                    @endif
                </div>
            </section>

            {{-- Báo giá: chỉ ở stage Chốt (stage 1–3 chưa có báo giá) --}}
            @if ($this->showQuotations())
                @php $quotations = $this->quotations; @endphp
                <section class="vc-card vc-results">
                    <h2 class="od-title od-title--bar">{{ __('opportunities.quotations') }}</h2>

                    @if ($quotations->isEmpty())
                        <p class="od-empty od-empty--pad">{{ __('opportunities.no_quotations_closed') }}</p>
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
                                    @foreach ($quotations as $quo)
                                        <tr wire:key="quo-{{ $quo->quotation_id }}">
                                            <td data-label="{{ __('opportunities.quotation_id') }}">
                                                <span class="vc-mono">{{ $quo->quotation_id }}</span>
                                            </td>
                                            <td data-label="{{ __('opportunities.created_at') }}" class="ld-nowrap">
                                                {{ $quo->created_at?->format('d/m/Y') }}
                                            </td>
                                            <td data-label="{{ __('opportunities.expiry_date') }}" class="ld-nowrap">
                                                {{ $quo->expiry_date?->format('d/m/Y') }}
                                            </td>
                                            <td data-label="{{ __('opportunities.voucher') }}">{{ $quo->voucher_id ?? '—' }}</td>
                                            <td data-label="{{ __('opportunities.quotation_total') }}" class="text-lg-end">
                                                <span class="vc-value">{{ $money($quo->total) }}</span>
                                            </td>
                                            <td data-label="{{ __('opportunities.status') }}">
                                                <span class="vc-badge vc-q--{{ strtolower($quo->status) }}">
                                                    <span class="vc-badge__dot"></span>{{ $label('courses.quotation_status', $quo->status) }}
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
                <h2 class="od-title">{{ __('opportunities.customer') }}</h2>
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
                        @if ($lead?->source_url)
                            <a href="{{ $lead->source_url }}" target="_blank" rel="noopener noreferrer">{{ $lead->source_name }}</a>
                        @else
                            {{ $lead?->source_name ?? '—' }}
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
                    <dt>{{ __('opportunities.expected_value') }}</dt>
                    <dd><span class="vc-value">{{ $opp->displayValue() ?? __('opportunities.no_value') }}</span></dd>
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
            </section>
        </aside>
    </div>
</div>
