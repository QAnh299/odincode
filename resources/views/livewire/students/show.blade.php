@php
    $student = $this->student;
    $lead = $student->opportunity?->lead;
    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
    $listUrl = route($this->routePrefix.'.students');
    $totals = $this->totals;
    $cards = $this->courseCards;
    $sel = $this->selected;
    $selOpp = $sel['opportunity'] ?? null;
    $selOrder = $this->selectedOrder;
    $selQuotation = $sel['quotation'] ?? null;
    // Mã có link sang trang chi tiết (nếu vai trò có trang đó), không thì chỉ hiện mã
    $ref = function (string $route, $model, string $text, string $class = 'st-chip') {
        $url = $this->detailUrl($route, $model);

        return $url
            ? '<a href="'.e($url).'" class="'.$class.' is-link" wire:navigate>'.e($text).'</a>'
            : '<span class="'.$class.'">'.e($text).'</span>';
    };
@endphp

<div class="vc st">
    {{-- voucher.css (khung, bảng, nhãn) + lead.css (popup, thông báo) + opportunity.css (tab, lịch sử chăm sóc,
         lịch hẹn) + student.css (phần riêng) --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/lead.css', 'resources/css/opportunity.css', 'resources/css/student.css'])
    @endassets

    {{-- ── Điều hướng ───────────────────────────────────────────────── --}}
    <nav class="vc-crumb" aria-label="breadcrumb">
        <a href="{{ $listUrl }}" class="odin-back"><x-icon name="arrow-left" />{{ __('students.back') }}</a>
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

    {{-- ── Đầu trang: học viên + số liệu học phí ───────────────────── --}}
    <section class="vc-card st-head">
        <div class="st-head__main">
            <span class="st-avatar">{{ mb_strtoupper(mb_substr(trim(\Illuminate\Support\Str::afterLast($student->full_name, ' ')), 0, 1)) }}</span>
            <div class="st-head__who">
                <h1 class="st-head__name">{{ $student->full_name }}</h1>
                <div class="st-tags">
                    <span class="st-tag">{{ $student->student_id }}</span>
                    <span class="st-tag {{ 'st-tone--'.$student->status }}">{{ __('students.status_name.'.$student->status) }}</span>
                </div>
            </div>
        </div>

        <dl class="st-stats">
            <div><dt>{{ __('students.stat_courses') }}</dt><dd>{{ $totals['courses'] }}</dd></div>
            <div><dt>{{ __('students.stat_total') }}</dt><dd>{{ $money($totals['total']) }}</dd></div>
            <div><dt>{{ __('students.paid') }}</dt><dd class="st-paid">{{ $money($totals['paid']) }}</dd></div>
            <div><dt>{{ __('students.remaining') }}</dt><dd class="st-due">{{ $money($totals['remaining']) }}</dd></div>
        </dl>

        @if ($this->canCreateOpportunity)
            <a href="{{ route('salesperson.opportunities.create', ['student' => $student->student_id]) }}"
                class="vc-btn vc-btn--primary st-head__cta" wire:navigate>
                <x-icon name="plus" />{{ __('opportunities.create.btn_enroll') }}
            </a>
        @endif
    </section>

    <div class="st-grid">
        <div class="st-main">
            {{-- ── Khóa học đã đăng ký: thẻ cuộn ngang ───────────────── --}}
            @if ($cards->isEmpty())
                <section class="vc-card"><p class="st-empty">{{ __('students.no_opportunities') }}</p></section>
            @else
                <div class="st-courses" role="listbox" aria-label="{{ __('students.courses_title') }}">
                    @foreach ($cards as $card)
                        @php
                            $isOn = $sel && $sel['key'] === $card['key'];
                            $pct = $card['total'] > 0 ? min(100, round($card['paid'] / $card['total'] * 100)) : 0;
                        @endphp
                        <button type="button" role="option" aria-selected="{{ $isOn ? 'true' : 'false' }}"
                            class="st-course {{ $isOn ? 'is-active' : '' }}"
                            wire:key="card-{{ $card['key'] }}" wire:click="selectCard('{{ $card['key'] }}')">
                            <span class="st-course__top">
                                <span class="st-course__name">{{ $card['name'] }}</span>
                                <span class="st-tag {{ $card['status']['tone'] }}">{{ $card['status']['label'] }}</span>
                            </span>
                            <span class="st-course__meta">
                                {{ $card['course_id'] ?? $card['opportunity_id'] }}@if ($card['course']?->duration) · {{ $card['course']->duration }}@endif
                            </span>
                            <span class="st-progress" aria-hidden="true"><span style="width: {{ $pct }}%"></span></span>
                            <span class="st-course__money">
                                <span>{{ __('students.collected', ['amount' => $money($card['paid'])]) }}</span>
                                <strong>{{ $money($card['total']) }}</strong>
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif

            {{-- ── Chi tiết khóa đang chọn ─────────────────────────────── --}}
            @if ($sel)
                <section class="vc-card st-detail" wire:key="detail-{{ $sel['key'] }}">
                    <header class="st-detail__head">
                        <div class="st-detail__title">
                            <h3>
                                @if ($sel['course'] && ($courseUrl = $this->detailUrl('courses', $sel['course'])))
                                    <a href="{{ $courseUrl }}" wire:navigate>{{ $sel['name'] }}</a>
                                @else
                                    {{ $sel['name'] }}
                                @endif
                            </h3>
                            @if ($sel['detail'])
                                <p class="st-sub">
                                    {{ $sel['course_id'] }}@if ($sel['course']?->duration) · {{ $sel['course']->duration }}@endif
                                    · {{ __('students.quantity') }} {{ $sel['detail']->quantity }}
                                    · {{ __('students.unit_price') }} {{ $money($sel['detail']->unit_price) }}
                                </p>
                            @endif
                        </div>
                        <div class="st-chips">
                            {!! $ref('opportunities', $selOpp->opportunity_id, __('students.chip_opportunity', ['code' => $selOpp->opportunity_id])) !!}
                            @if ($selOrder)
                                {!! $ref('orders', $selOrder->order_id, __('students.chip_order', ['code' => $selOrder->order_id])) !!}
                            @endif
                            @if ($selQuotation)
                                {!! $ref('quotations', $selQuotation->quotation_id, __('students.chip_quotation', ['code' => $selQuotation->quotation_id])) !!}
                            @endif
                            @if ($selQuotation?->voucher_id)
                                {!! $ref('vouchers', $selQuotation->voucher_id, __('students.voucher', ['code' => $selQuotation->voucher_id])) !!}
                            @endif
                            @if ($selOpp->student && $selOpp->student->student_id !== $student->student_id)
                                {!! $ref('students', $selOpp->student->student_id, __('students.chip_student', ['code' => $selOpp->student->student_id])) !!}
                            @endif
                        </div>
                    </header>

                    @php
                        $cares = $this->openCares;
                        $appointments = $this->openAppointments;
                        $tabs = ['payment' => null, 'care' => $cares->count(), 'appointments' => $appointments->count()];
                    @endphp
                    <div class="od-tabs st-detail__tabs" role="tablist">
                        @foreach ($tabs as $key => $count)
                            <button type="button" role="tab" wire:click="setCourseTab('{{ $key }}')"
                                class="od-tab {{ $courseTab === $key ? 'is-active' : '' }}"
                                aria-selected="{{ $courseTab === $key ? 'true' : 'false' }}">
                                {{ $key === 'payment' ? __('students.payment_title') : __('opportunities.tab.'.$key) }}
                                @if ($count !== null)
                                    <span class="od-tab__count">{{ $count }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>

                    <div class="od-tabpanel">
                        @if ($courseTab === 'care')
                            @include('livewire.opportunities.partials.care-history', ['cares' => $cares])
                        @elseif ($courseTab === 'appointments')
                            @include('livewire.opportunities.partials.appointment-list', ['appointments' => $appointments, 'place' => $this->appointmentPlace()])
                        @elseif (! $selQuotation)
                            <p class="st-empty">{{ __('students.no_quotation') }}</p>
                        @elseif (! $selOrder)
                            <p class="st-empty">{{ __('students.no_order_yet') }}</p>
                        @else
                            <div class="st-pay-wrap">
                                <dl class="st-pay">
                                    <div><dt>{{ __('students.payment_type') }}</dt><dd>{{ __('orders.payment_type.'.($selOrder->payment_type ?: 'none')) }}</dd></div>
                                    <div><dt>{{ __('students.total') }}</dt><dd>{{ $money($selOrder->total_amount) }}</dd></div>
                                    <div><dt>{{ __('students.paid') }}</dt><dd class="st-paid">{{ $money($selOrder->paid_amount) }}</dd></div>
                                    <div><dt>{{ __('students.remaining') }}</dt><dd class="st-due">{{ $money($selOrder->remaining_amount) }}</dd></div>
                                </dl>
                                @if ($selQuotation->details->count() > 1)
                                    <p class="st-note">{{ __('students.shared_order', ['count' => $selQuotation->details->count()]) }}</p>
                                @endif

                                @if ($selOrder->invoices->isEmpty())
                                    <p class="st-empty st-empty--flat">{{ __('students.no_invoices') }}</p>
                                @else
                                    <div class="vc-table-wrap st-invoices">
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
                                                @foreach ($selOrder->invoices as $invoice)
                                                    <tr wire:key="invoice-{{ $invoice->invoice_id }}">
                                                        <td data-label="{{ __('students.installment') }}"><strong>{{ __('students.installment_no', ['no' => $invoice->payment_installment]) }}</strong></td>
                                                        <td data-label="{{ __('students.invoice') }}">{!! $ref('invoices', $invoice->invoice_id, $invoice->invoice_id, 'vc-mono') !!}</td>
                                                        <td data-label="{{ __('students.amount') }}" class="text-lg-end"><strong>{{ $money($invoice->payment_amount) }}</strong></td>
                                                        <td data-label="{{ __('students.method') }}">{{ $invoice->payment_method ? __('invoices.method.'.$invoice->payment_method) : '-' }}</td>
                                                        <td data-label="{{ __('students.payment_date') }}" class="st-nowrap">{{ $invoice->payment_date ? \Illuminate\Support\Carbon::parse($invoice->payment_date)->format('d/m/Y H:i') : '-' }}</td>
                                                        <td data-label="{{ __('students.status') }}">
                                                            <span class="st-tag st-inv--{{ $invoice->status }}">{{ __('invoices.status.'.$invoice->status) }}</span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </section>
            @endif
        </div>

        {{-- ── Cột phải: thông tin học viên, nguồn gốc ──────────────── --}}
        <aside class="st-side">
            <section class="vc-card">
                <div class="st-title-row">
                    <h2 class="st-section st-section--card">{{ __('students.info') }}</h2>
                    @if ($this->canEdit)
                        <button type="button" class="vc-btn st-edit-btn" wire:click="startEdit">
                            <x-icon name="edit" />{{ __('students.edit') }}
                        </button>
                    @endif
                </div>
                <dl class="st-info">
                    <div><dt>{{ __('students.phone') }}</dt><dd>{{ $student->phone }}</dd></div>
                    <div><dt>{{ __('students.email') }}</dt><dd>{{ $student->email ?: '-' }}</dd></div>
                    <div><dt>{{ __('students.date_of_birth') }}</dt><dd>{{ $student->date_of_birth?->format('d/m/Y') ?? '-' }}</dd></div>
                    <div><dt>{{ __('students.conversion_date') }}</dt><dd>{{ $student->conversion_date?->format('d/m/Y H:i') }}</dd></div>
                    <div><dt>{{ __('students.branch') }}</dt><dd>{{ $lead?->branch?->branch_name ?? '-' }}</dd></div>
                </dl>
            </section>

            <section class="vc-card">
                <h2 class="st-section st-section--card">{{ __('students.origin') }}</h2>
                <dl class="st-info">
                    <div>
                        <dt>{{ __('students.lead') }}</dt>
                        <dd>
                            @if ($lead)
                                {!! $ref('leads', $lead->lead_id, $lead->lead_id, 'st-ref') !!}
                                @if ($lead->source_name) · {{ $lead->source_name }} @endif
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>{{ __('students.selected_owner') }}</dt>
                        <dd>
                            @if ($owner = $selOpp?->employee)
                                {!! $ref('employees', $owner->employee_id, $owner->full_name, 'st-ref') !!}
                                @if ($owner->team) · {{ $owner->team->team_name }} @endif
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>
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
