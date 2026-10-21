@php
    $student = $this->student;
    $opp = $student->opportunity;
    $lead = $opp?->lead;
    $order = $this->order;
    $quotation = $order?->quotation;
    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
    $listUrl = route($this->routePrefix.'.students');
    $opportunityUrl = $this->opportunityUrl();
    $quotationRoute = $this->routePrefix.'.quotations.show';
@endphp

<div class="vc st">
    {{-- voucher.css (khung, bảng, nhãn) + lead.css (popup, thông báo) + opportunity.css (tab, lịch sử chăm sóc,
         lịch hẹn của Lịch sử cơ hội) + student.css (phần riêng) --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/lead.css', 'resources/css/opportunity.css', 'resources/css/student.css'])
    @endassets

    {{-- ── Điều hướng ───────────────────────────────────────────────── --}}
    <nav class="vc-crumb" aria-label="breadcrumb">
        <a href="{{ $listUrl }}" class="vc-crumb__back"><x-icon name="arrow-left" />{{ __('students.back') }}</a>
        <span class="vc-crumb__path">
            <a href="{{ $listUrl }}">{{ __('students.title') }}</a>
            <x-icon name="chevron" />
            <span aria-current="page">{{ $student->student_id }}</span>
        </span>
    </nav>

    @if ($notice)
        <div class="ld-notice" role="status">
            <x-icon name="check-circle" />
            <span class="ld-notice__text">{{ $notice }}</span>
            <button type="button" class="ld-notice__close" wire:click="dismissNotice" aria-label="{{ __('leads.close') }}"><x-icon name="x" /></button>
        </div>
    @endif

    {{-- ── Đầu trang ────────────────────────────────────────────────── --}}
    <section class="vc-card st-head">
        <div class="st-head__main">
            <span class="st-avatar">{{ mb_strtoupper(mb_substr(trim(\Illuminate\Support\Str::afterLast($student->full_name, ' ')), 0, 1)) }}</span>
            <div>
                <span class="vc-mono">{{ $student->student_id }}</span>
                <h1 class="st-head__name">{{ $student->full_name }}</h1>
                <span class="vc-badge vc-badge--lg st-tone--{{ $student->status }}">
                    <span class="vc-badge__dot"></span>{{ __('students.status_name.'.$student->status) }}
                </span>
            </div>
        </div>
        @if ($this->canEdit)
            <button type="button" class="vc-btn vc-btn--primary" wire:click="startEdit">
                <x-icon name="user" />{{ __('students.edit') }}
            </button>
        @endif
    </section>

    <div class="st-grid">
        {{-- ── Cột trái: khoá học + thanh toán ─────────────────────── --}}
        <div class="st-main">
            <section class="vc-card vc-results">
                <h2 class="st-title st-title--bar">{{ __('students.courses_title') }}</h2>
                @if (! $quotation || $quotation->details->isEmpty())
                    <p class="st-empty">{{ __('students.no_order') }}</p>
                @else
                    <div class="vc-table-wrap">
                        <table class="vc-table vc-table--static">
                            <thead>
                                <tr>
                                    <th>{{ __('students.course') }}</th>
                                    <th>{{ __('students.duration') }}</th>
                                    <th class="text-lg-end">{{ __('students.unit_price') }}</th>
                                    <th class="text-lg-center">{{ __('students.quantity') }}</th>
                                    <th class="text-lg-end">{{ __('students.line_total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quotation->details as $detail)
                                    <tr wire:key="detail-{{ $detail->course_id }}">
                                        <td data-label="{{ __('students.course') }}">
                                            <span class="st-name">{{ $detail->course?->course_name ?? $detail->course_id }}</span>
                                            <span class="st-sub vc-mono">{{ $detail->course_id }}</span>
                                        </td>
                                        <td data-label="{{ __('students.duration') }}">{{ $detail->course?->duration ?: '—' }}</td>
                                        <td data-label="{{ __('students.unit_price') }}" class="text-lg-end">{{ $money($detail->unit_price) }}</td>
                                        <td data-label="{{ __('students.quantity') }}" class="text-lg-center">{{ $detail->quantity }}</td>
                                        <td data-label="{{ __('students.line_total') }}" class="text-lg-end"><span class="vc-value">{{ $money($detail->line_total) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            @if ($order)
                <section class="vc-card vc-results">
                    <h2 class="st-title st-title--bar">{{ __('students.payment_title') }}</h2>
                    <dl class="st-pay">
                        <div><dt>{{ __('students.order') }}</dt><dd><span class="vc-mono">{{ $order->order_id }}</span></dd></div>
                        <div><dt>{{ __('students.payment_type') }}</dt><dd>{{ __('orders.payment_type.'.$order->payment_type) }}</dd></div>
                        <div><dt>{{ __('students.total') }}</dt><dd>{{ $money($order->total_amount) }}</dd></div>
                        <div><dt>{{ __('students.paid') }}</dt><dd class="st-paid">{{ $money($order->paid_amount) }}</dd></div>
                        @if ($quotation)
                            <div>
                                <dt>{{ __('students.quotation') }}</dt>
                                <dd>
                                    @if (\Illuminate\Support\Facades\Route::has($quotationRoute))
                                        <a href="{{ route($quotationRoute, $quotation) }}" class="vc-mono">{{ $quotation->quotation_id }}</a>
                                    @else
                                        <span class="vc-mono">{{ $quotation->quotation_id }}</span>
                                    @endif
                                    @if ($quotation->voucher_id)
                                        <span class="st-sub">{{ __('students.voucher', ['code' => $quotation->voucher_id]) }}</span>
                                    @endif
                                </dd>
                            </div>
                        @endif
                    </dl>

                    @if ($order->invoices->isNotEmpty())
                        <div class="vc-table-wrap">
                            <table class="vc-table vc-table--static">
                                <thead>
                                    <tr>
                                        <th>{{ __('students.installment') }}</th>
                                        <th>{{ __('students.invoice') }}</th>
                                        <th class="text-lg-end">{{ __('students.amount') }}</th>
                                        <th>{{ __('students.method') }}</th>
                                        <th>{{ __('students.payment_date') }}</th>
                                        <th>{{ __('students.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($order->invoices as $invoice)
                                        <tr wire:key="invoice-{{ $invoice->invoice_id }}">
                                            <td data-label="{{ __('students.installment') }}">{{ __('students.installment_no', ['no' => $invoice->payment_installment]) }}</td>
                                            <td data-label="{{ __('students.invoice') }}"><span class="vc-mono">{{ $invoice->invoice_id }}</span></td>
                                            <td data-label="{{ __('students.amount') }}" class="text-lg-end">{{ $money($invoice->payment_amount) }}</td>
                                            <td data-label="{{ __('students.method') }}">{{ $invoice->payment_method ? __('invoices.method.'.$invoice->payment_method) : '—' }}</td>
                                            <td data-label="{{ __('students.payment_date') }}" class="st-nowrap">{{ $invoice->payment_date ? \Illuminate\Support\Carbon::parse($invoice->payment_date)->format('d/m/Y H:i') : '—' }}</td>
                                            <td data-label="{{ __('students.status') }}">
                                                <span class="vc-badge st-inv--{{ $invoice->status }}">
                                                    <span class="vc-badge__dot"></span>{{ __('invoices.status.'.$invoice->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif

            {{-- ── Lịch sử cơ hội: các lần khách hàng là Opportunity (mỗi lần có thể nhiều khoá học);
                 mở một Opportunity để xem 2 tab Lịch sử chăm sóc / Lịch hẹn ─────────────────────── --}}
            <section class="vc-card vc-results">
                <h2 class="st-title st-title--bar">{{ __('students.opportunities_title') }}</h2>

                @foreach ($this->opportunities as $item)
                    @php $isOpen = $openOpportunity === $item->opportunity_id; @endphp
                    <div class="st-opp {{ $isOpen ? 'is-open' : '' }}" wire:key="opp-{{ $item->opportunity_id }}">
                        <button type="button" class="st-opp__head" wire:click="toggleOpportunity('{{ $item->opportunity_id }}')"
                            aria-expanded="{{ $isOpen ? 'true' : 'false' }}">
                            <span class="st-opp__main">
                                <span class="vc-mono">{{ $item->opportunity_id }}</span>
                                <span class="st-sub st-sub--inline">{{ $item->conversion_date?->format('d/m/Y') }}</span>
                                @if ($item->opportunity_id === $student->opportunity_id)
                                    <span class="st-opp__current">{{ __('students.this_enrollment') }}</span>
                                @endif
                                <span class="vc-badge st-opp--{{ $item->status }}">
                                    <span class="vc-badge__dot"></span>{{ __('students.opportunity_status.'.$item->status) }}
                                </span>
                            </span>
                            <span class="st-opp__courses">{{ $item->courses ? implode(', ', $item->courses) : __('students.no_courses') }}</span>
                            <span class="st-sub">{{ $item->stage?->stage_name }} · {{ $item->employee?->full_name ?? '—' }}</span>
                            <span class="st-opp__chevron"><x-icon name="chevron" /></span>
                        </button>

                        @if ($isOpen)
                            @php
                                $cares = $this->openCares;
                                $appointments = $this->openAppointments;
                            @endphp
                            <div class="st-opp__body">
                                <div class="od-tabs" role="tablist">
                                    @foreach (['care' => $cares->count(), 'appointments' => $appointments->count()] as $key => $count)
                                        <button type="button" role="tab" wire:click="setOpportunityTab('{{ $key }}')"
                                            class="od-tab {{ $opportunityTab === $key ? 'is-active' : '' }}"
                                            aria-selected="{{ $opportunityTab === $key ? 'true' : 'false' }}">
                                            {{ __('opportunities.tab.'.$key) }}
                                            <span class="od-tab__count">{{ $count }}</span>
                                        </button>
                                    @endforeach
                                </div>

                                <div class="od-tabpanel">
                                    @if ($opportunityTab === 'appointments')
                                        @include('livewire.opportunities.partials.appointment-list', ['appointments' => $appointments, 'place' => $this->appointmentPlace()])
                                    @else
                                        @include('livewire.opportunities.partials.care-history', ['cares' => $cares])
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
                @if ($this->opportunities->isEmpty())
                    <p class="st-empty">{{ __('students.no_opportunities') }}</p>
                @endif
            </section>
        </div>

        {{-- ── Cột phải: thông tin học viên, cơ hội, lần đăng ký khác ─ --}}
        <aside class="st-side">
            <section class="vc-card">
                <h2 class="st-title">{{ __('students.info') }}</h2>
                <dl class="st-info">
                    <dt>{{ __('students.phone') }}</dt><dd>{{ $student->phone }}</dd>
                    <dt>{{ __('students.email') }}</dt><dd>{{ $student->email ?: '—' }}</dd>
                    <dt>{{ __('students.date_of_birth') }}</dt><dd>{{ $student->date_of_birth?->format('d/m/Y') ?? '—' }}</dd>
                    <dt>{{ __('students.conversion_date') }}</dt><dd>{{ $student->conversion_date?->format('d/m/Y H:i') }}</dd>
                    <dt>{{ __('students.branch') }}</dt><dd>{{ $lead?->branch?->branch_name ?? '—' }}</dd>
                </dl>
            </section>

            <section class="vc-card">
                <h2 class="st-title">{{ __('students.origin') }}</h2>
                <dl class="st-info">
                    <dt>{{ __('students.opportunity') }}</dt>
                    <dd>
                        @if ($opportunityUrl)
                            <a href="{{ $opportunityUrl }}" class="vc-mono">{{ $student->opportunity_id }}</a>
                        @else
                            <span class="vc-mono">{{ $student->opportunity_id }}</span>
                        @endif
                    </dd>
                    <dt>{{ __('students.lead') }}</dt>
                    <dd><span class="vc-mono">{{ $opp?->lead_id }}</span> <span class="st-sub st-sub--inline">· {{ $lead?->source_name }}</span></dd>
                    <dt>{{ __('students.owner') }}</dt>
                    <dd>
                        {{ $opp?->employee?->full_name ?? '—' }}
                        @if ($opp?->employee?->team)
                            <span class="st-sub st-sub--inline">· {{ $opp->employee->team->team_name }}</span>
                        @endif
                    </dd>
                </dl>
            </section>

            @if ($this->otherEnrollments->isNotEmpty())
                <section class="vc-card">
                    <h2 class="st-title">{{ __('students.other_enrollments') }}</h2>
                    <ul class="st-others">
                        @foreach ($this->otherEnrollments as $other)
                            <li>
                                <a href="{{ route($this->routePrefix.'.students.show', $other) }}" class="vc-mono">{{ $other->student_id }}</a>
                                <span class="st-sub st-sub--inline">{{ $other->conversion_date?->format('d/m/Y') }}</span>
                                <span class="vc-badge st-tone--{{ $other->status }}">{{ __('students.status_name.'.$other->status) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>

    {{-- ── Popup chỉnh sửa (Sale Leader, Salesperson) ──────────────── --}}
    @if ($editing)
        <div class="ld-modal" wire:key="modal-edit" x-data x-on:keydown.escape.window="$wire.cancelEdit()">
            <div class="ld-modal__backdrop" wire:click="cancelEdit"></div>
            <form class="ld-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="st-edit-title" wire:submit="save">
                <header class="ld-modal__head">
                    <span class="ld-modal__icon"><x-icon name="user" /></span>
                    <div>
                        <h2 class="ld-modal__title" id="st-edit-title">{{ __('students.edit_title') }}</h2>
                        <p class="ld-modal__sub">{{ $student->student_id }} · {{ $student->full_name }}</p>
                    </div>
                    <button type="button" class="ld-modal__close" wire:click="cancelEdit" aria-label="{{ __('leads.close') }}"><x-icon name="x" /></button>
                </header>

                <div class="ld-modal__body ld-form">
                    <label class="vc-field ld-form__wide">
                        <span class="vc-field__label">{{ __('students.full_name') }} <span class="ld-req">*</span></span>
                        <input type="text" wire:model="fullName" maxlength="150" class="vc-control @error('fullName') is-invalid @enderror">
                        @error('fullName') <span class="ld-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="vc-field">
                        <span class="vc-field__label">{{ __('students.phone') }} <span class="ld-req">*</span></span>
                        <input type="text" wire:model="phone" maxlength="10" inputmode="numeric" class="vc-control @error('phone') is-invalid @enderror">
                        @error('phone') <span class="ld-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="vc-field">
                        <span class="vc-field__label">{{ __('students.email') }}</span>
                        <input type="email" wire:model="email" maxlength="254" class="vc-control @error('email') is-invalid @enderror">
                        @error('email') <span class="ld-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="vc-field">
                        <span class="vc-field__label">{{ __('students.date_of_birth') }}</span>
                        <input type="date" wire:model="dateOfBirth" max="{{ now()->subDay()->format('Y-m-d') }}" class="vc-control @error('dateOfBirth') is-invalid @enderror">
                        @error('dateOfBirth') <span class="ld-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="vc-field">
                        <span class="vc-field__label">{{ __('students.status') }} <span class="ld-req">*</span></span>
                        <select wire:model="status" class="vc-control @error('status') is-invalid @enderror">
                            @foreach (\App\Models\Student::STATUSES as $option)
                                <option value="{{ $option }}">{{ __('students.status_name.'.$option) }}</option>
                            @endforeach
                        </select>
                        @error('status') <span class="ld-error">{{ $message }}</span> @enderror
                    </label>
                </div>

                <footer class="ld-modal__foot">
                    <button type="button" class="vc-btn vc-btn--ghost" wire:click="cancelEdit">{{ __('leads.cancel') }}</button>
                    <button type="submit" class="vc-btn vc-btn--primary" wire:loading.attr="disabled" wire:target="save">
                        <x-icon name="check" />{{ __('students.save') }}
                    </button>
                </footer>
            </form>
        </div>
    @endif
</div>
