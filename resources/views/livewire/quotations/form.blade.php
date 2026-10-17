@php
    $opportunity = $this->opportunity;
    $lead = $opportunity->lead;
    $isEdit = $quotationId !== null;
    $backUrl = $isEdit
        ? route('salesperson.quotations.show', $quotationId)
        : route('salesperson.opportunities.show', $opportunity);

    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
    $percent = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').'%';
@endphp

<div class="qt qt-detail">
    @assets
        @vite('resources/css/quotation.css')
    @endassets

    {{-- ── Điều hướng ───────────────────────────────────────────────── --}}
    <nav class="qt-crumb" aria-label="breadcrumb">
        <a href="{{ $backUrl }}" class="qt-crumb__back">
            <x-icon name="arrow-left" />{{ $isEdit ? __('quotations.form.back_quotation') : __('quotations.form.back_opportunity') }}
        </a>
        <span class="qt-crumb__path">
            <a href="{{ route('salesperson.quotations') }}">{{ __('quotations.title') }}</a>
            <x-icon name="chevron" />
            <span aria-current="page">{{ $isEdit ? $quotationId : __('quotations.form.create_title') }}</span>
        </span>
    </nav>

    <header class="qt-detail-head">
        <h1 class="qt-detail-head__title">
            <span class="qt-detail-head__icon"><x-icon name="{{ $isEdit ? 'edit' : 'plus' }}" /></span>
            {{ $isEdit ? __('quotations.form.edit_title', ['code' => $quotationId]) : __('quotations.form.create_title') }}
        </h1>
    </header>

    @if ($blocked)
        {{-- HĐ2 không đạt: thông báo lý do, kết thúc --}}
        <section class="qt-card qt-panel-card qt-form-blocked" role="alert">
            <span class="qt-empty__icon"><x-icon name="alert" /></span>
            <p class="qt-empty__title">{{ __('quotations.form.blocked_title') }}</p>
            <p class="qt-empty__sub">{{ __('quotations.form.blocked.'.$blocked) }}</p>
            <a href="{{ $backUrl }}" class="qt-btn qt-btn--ghost"><x-icon name="arrow-left" />{{ __('quotations.form.back_opportunity') }}</a>
        </section>
    @else
        <form wire:submit="save" class="qt-detail-grid" novalidate>
            <div class="qt-detail-main">
                {{-- ── Khóa học (HĐ3): chọn khóa đang kích hoạt + số lượng; đơn giá và VAT lấy từ khóa học ── --}}
                <section class="qt-card">
                    <div class="qt-section-head">
                        <h2 class="qt-section-title">{{ __('quotations.form.courses') }}</h2>
                    </div>

                    {{-- Chọn là thêm ngay (updatedPickCourse); ô chọn trở về trống để chọn tiếp khóa khác --}}
                    <div class="qt-form-pick">
                        <select wire:model.live="pickCourse" class="qt-control" aria-label="{{ __('quotations.form.pick_course') }}"
                            @disabled($this->availableCourses->isEmpty())>
                            <option value="">{{ $this->availableCourses->isEmpty() ? __('quotations.form.all_courses_added') : __('quotations.form.pick_course') }}</option>
                            @foreach ($this->availableCourses as $course)
                                <option value="{{ $course->course_id }}" wire:key="pick-{{ $course->course_id }}">{{ $course->course_name }} · {{ $course->displayPrice() }}</option>
                            @endforeach
                        </select>
                        <span class="qt-form-pick__spin" wire:loading.delay wire:target="pickCourse">
                            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                        </span>
                    </div>

                    @if ($this->rows === [])
                        <p class="qt-detail-empty">{{ __('quotations.form.no_lines') }}</p>
                    @else
                        <div class="qt-table-wrap">
                            <table class="qt-table qt-form-lines">
                                <thead>
                                    <tr>
                                        <th>{{ __('quotations.detail.course_name') }}</th>
                                        <th class="text-lg-end">{{ __('quotations.detail.unit_price') }}</th>
                                        <th>{{ __('quotations.detail.quantity') }}</th>
                                        <th class="text-lg-end">{{ __('quotations.detail.vat') }}</th>
                                        <th class="text-lg-end">{{ __('quotations.detail.line_total') }}</th>
                                        <th><span class="visually-hidden">{{ __('quotations.form.remove') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->rows as $row)
                                        <tr wire:key="line-{{ $row['course']->course_id }}">
                                            <td data-label="{{ __('quotations.detail.course_name') }}">
                                                <span class="qt-stack">
                                                    <span class="qt-stack__main">{{ $row['course']->course_name }}</span>
                                                    <span class="qt-stack__sub">{{ $row['course']->course_id }}</span>
                                                </span>
                                            </td>
                                            <td data-label="{{ __('quotations.detail.unit_price') }}" class="text-lg-end">{{ $money($row['course']->unit_price) }}</td>
                                            <td data-label="{{ __('quotations.detail.quantity') }}">
                                                <input type="number" min="1" max="{{ \App\Services\QuotationService::MAX_QUANTITY }}" step="1"
                                                    wire:model.live.debounce.300ms="lines.{{ $row['index'] }}.quantity"
                                                    @class(['qt-control', 'qt-control--qty', 'is-invalid' => $errors->has('lines.'.$row['index'].'.quantity')])
                                                    aria-label="{{ __('quotations.form.quantity') }} {{ $row['course']->course_name }}">
                                                @error('lines.'.$row['index'].'.quantity')
                                                    <span class="qt-field__error">{{ $message }}</span>
                                                @enderror
                                            </td>
                                            <td data-label="{{ __('quotations.detail.vat') }}" class="text-lg-end">{{ $percent($row['course']->VAT) }}</td>
                                            <td data-label="{{ __('quotations.detail.line_total') }}" class="text-lg-end">
                                                <span class="qt-amount">{{ $money($row['total']) }}</span>
                                            </td>
                                            <td class="text-lg-end">
                                                <button type="button" class="qt-chip__remove qt-form-remove" wire:click="removeLine({{ $row['index'] }})"
                                                    title="{{ __('quotations.form.remove') }}">
                                                    <x-icon name="x" /><span class="visually-hidden">{{ __('quotations.form.remove') }}</span>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @error('lines')
                        <p class="qt-field__error qt-form-error">{{ $message }}</p>
                    @enderror

                    {{-- Tạm tính − voucher = tổng (tổng không lưu trong CSDL, luôn tính ra) --}}
                    @php $totals = $this->totals; @endphp
                    <dl class="qt-totals">
                        <div class="qt-totals__row">
                            <dt>{{ __('quotations.detail.subtotal') }}</dt>
                            <dd>{{ $money($totals['subtotal']) }}</dd>
                        </div>
                        @if ($totals['voucher'])
                            <div class="qt-totals__row qt-totals__row--discount">
                                <dt>{{ __('quotations.detail.discount', ['code' => $totals['voucher']->voucher_id, 'value' => $totals['voucher']->displayValue()]) }}</dt>
                                <dd>−{{ $money($totals['discount']) }}</dd>
                            </div>
                        @endif
                        <div class="qt-totals__row qt-totals__row--grand">
                            <dt>{{ __('quotations.detail.grand_total') }}</dt>
                            <dd>{{ $money($totals['total']) }}</dd>
                        </div>
                    </dl>
                </section>

                {{-- ── Thông tin báo giá: hạn, voucher, ghi chú ── --}}
                <section class="qt-card qt-panel-card">
                    <h2 class="qt-section-title">{{ __('quotations.detail.general') }}</h2>
                    <div class="qt-form-fields">
                        <label class="qt-field">
                            <span class="qt-field__label">{{ __('quotations.form.expiry_date') }} *</span>
                            <input type="date" wire:model="expiryDate" min="{{ $this->createdDate }}"
                                @class(['qt-control', 'is-invalid' => $errors->has('expiryDate')])>
                            <span class="qt-field__help">{{ __('quotations.form.expiry_help', ['days' => \App\Services\QuotationService::DEFAULT_EXPIRY_DAYS]) }}</span>
                            @error('expiryDate')
                                <span class="qt-field__error">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="qt-field">
                            <span class="qt-field__label">{{ __('quotations.form.voucher') }}</span>
                            <select wire:model.live="voucherId" @class(['qt-control', 'is-invalid' => $errors->has('voucherId')])>
                                <option value="">{{ __('quotations.form.no_voucher') }}</option>
                                @foreach ($this->vouchers as $voucher)
                                    <option value="{{ $voucher->voucher_id }}">
                                        {{ $voucher->voucher_id }} · −{{ $voucher->displayValue() }} · {{ __('quotations.form.voucher_until', ['date' => $voucher->end_date->format('d/m/Y')]) }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="qt-field__help">{{ __('quotations.form.voucher_help') }}</span>
                            @error('voucherId')
                                <span class="qt-field__error">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="qt-field qt-field--wide">
                            <span class="qt-field__label">{{ __('quotations.form.notes') }}</span>
                            <textarea wire:model="notes" rows="3" maxlength="2000"
                                @class(['qt-control', 'qt-control--area', 'is-invalid' => $errors->has('notes')])></textarea>
                            @error('notes')
                                <span class="qt-field__error">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>
                </section>

                <div class="qt-form-actions">
                    <a href="{{ $backUrl }}" class="qt-btn qt-btn--ghost">{{ __('quotations.form.cancel') }}</a>
                    <button type="submit" class="qt-btn qt-btn--primary" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save"><x-icon name="check" /></span>
                        <span wire:loading wire:target="save" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                        {{ $isEdit ? __('quotations.form.save_changes') : __('quotations.form.save_draft') }}
                    </button>
                </div>
            </div>

            {{-- ── Cột phải: khách hàng + cơ hội (chỉ đọc) ── --}}
            <aside class="qt-detail-side">
                <section class="qt-card qt-panel-card">
                    <h2 class="qt-section-title">{{ __('quotations.detail.customer') }}</h2>
                    <dl class="qt-info qt-info--stack">
                        <div class="qt-info__row"><dt>{{ __('quotations.detail.full_name') }}</dt><dd>{{ $lead?->full_name ?? '—' }}</dd></div>
                        <div class="qt-info__row"><dt>{{ __('quotations.detail.phone') }}</dt><dd>{{ $lead?->phone ?: '—' }}</dd></div>
                        <div class="qt-info__row"><dt>{{ __('quotations.detail.email') }}</dt><dd>{{ $lead?->email ?: '—' }}</dd></div>
                    </dl>
                </section>
                <section class="qt-card qt-panel-card">
                    <h2 class="qt-section-title">{{ __('quotations.detail.opportunity') }}</h2>
                    <dl class="qt-info qt-info--stack">
                        <div class="qt-info__row"><dt>{{ __('quotations.detail.opportunity_id') }}</dt><dd><span class="qt-mono">{{ $opportunity->opportunity_id }}</span></dd></div>
                        <div class="qt-info__row"><dt>{{ __('quotations.detail.stage') }}</dt><dd>{{ $opportunity->stage?->stage_name ?? '—' }}</dd></div>
                        <div class="qt-info__row"><dt>{{ __('quotations.form.created_date') }}</dt><dd>{{ \Carbon\Carbon::parse($this->createdDate)->format('d/m/Y') }}</dd></div>
                    </dl>
                </section>
            </aside>
        </form>
    @endif
</div>
