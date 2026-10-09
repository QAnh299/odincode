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
        {{-- ── Cột trái: chăm sóc, lịch hẹn, báo giá ───────────────── --}}
        <div class="od-main">
            {{-- Lịch sử chăm sóc --}}
            <section class="vc-card">
                <h2 class="od-title">{{ __('opportunities.care_history') }}</h2>

                @forelse ($opp->careResults as $care)
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
            </section>

            {{-- Lịch hẹn --}}
            <section class="vc-card vc-results">
                <h2 class="od-title od-title--bar">{{ __('opportunities.appointments') }}</h2>

                @if ($opp->appointments->isEmpty())
                    <p class="od-empty od-empty--pad">{{ __('opportunities.no_appointments') }}</p>
                @else
                    <div class="vc-table-wrap">
                        <table class="vc-table vc-table--static">
                            <thead>
                                <tr>
                                    <th>{{ __('opportunities.appointment_time') }}</th>
                                    <th>{{ __('opportunities.appointment_type') }}</th>
                                    <th>{{ __('opportunities.location') }}</th>
                                    <th>{{ __('opportunities.notes') }}</th>
                                    <th>{{ __('opportunities.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($opp->appointments as $apt)
                                    <tr wire:key="apt-{{ $apt->appointment_id }}">
                                        <td data-label="{{ __('opportunities.appointment_time') }}" class="ld-nowrap">
                                            {{ $apt->scheduled_time?->format('d/m/Y H:i') }}
                                        </td>
                                        <td data-label="{{ __('opportunities.appointment_type') }}">
                                            {{ $label('opportunities.appointment_type_name', $apt->appointment_type) }}
                                        </td>
                                        <td data-label="{{ __('opportunities.location') }}">{{ $apt->location ?: '—' }}</td>
                                        <td data-label="{{ __('opportunities.notes') }}">{{ $apt->notes ?: '—' }}</td>
                                        <td data-label="{{ __('opportunities.status') }}">
                                            <span class="vc-badge od-apt--{{ $apt->status }}">
                                                <span class="vc-badge__dot"></span>{{ $label('opportunities.appointment_status', $apt->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Báo giá --}}
            <section class="vc-card vc-results">
                <h2 class="od-title od-title--bar">{{ __('opportunities.quotations') }}</h2>

                @if ($opp->quotations->isEmpty())
                    <p class="od-empty od-empty--pad">
                        {{ $this->canCreateQuotation() ? __('opportunities.no_quotations_closed') : __('opportunities.no_quotations') }}
                    </p>
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
                                @foreach ($opp->quotations as $quo)
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
