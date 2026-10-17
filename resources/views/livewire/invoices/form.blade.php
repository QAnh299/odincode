@php
    use App\Models\Invoice;
    use App\Models\SalesOrder;

    $order = $this->order;
    $lead = $order->quotation?->opportunity?->lead;
    $isEdit = $invoiceId !== null;
    $limits = $this->limits;
    $type = $this->type;
    $isFull = $type === SalesOrder::PAYMENT_FULL;
    $backUrl = $isEdit ? route('salesperson.invoices.show', $invoiceId) : route('salesperson.quotations.show', $order->quotation_id);
    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
    $editing = $isEdit ? Invoice::query()->with('createdBy')->find($invoiceId) : null;
@endphp

<div class="qt qt-detail iv">
    @assets
        @vite(['resources/css/quotation.css', 'resources/css/invoice.css'])
    @endassets

    <nav class="qt-crumb" aria-label="breadcrumb">
        <a href="{{ $backUrl }}" class="qt-crumb__back">
            <x-icon name="arrow-left" />{{ $isEdit ? __('invoices.form.back_invoice') : __('invoices.form.back_quotation') }}
        </a>
        <span class="qt-crumb__path">
            <a href="{{ route('salesperson.invoices') }}">{{ __('invoices.title') }}</a>
            <x-icon name="chevron" />
            <span aria-current="page">{{ $isEdit ? $invoiceId : __('invoices.form.new') }}</span>
        </span>
    </nav>

    @if ($this->blocked === 'missing_profile')
        {{-- HĐ19 → HĐ20: khách thiếu email (cần để gửi email hóa đơn) → SP bổ sung rồi tiếp tục --}}
        <section class="qt-card qt-panel-card iv-profile">
            <p class="qt-followup__title"><x-icon name="alert" />{{ __('invoices.form.blocked.missing_profile') }}</p>
            <form wire:submit="saveCustomerEmail" class="iv-profile__form">
                <label class="qt-field">
                    <span class="qt-field__label">{{ __('invoices.form.customer_email') }} – {{ $lead?->full_name }}</span>
                    <input type="email" wire:model="customerEmail" maxlength="254" @class(['qt-control', 'is-invalid' => $errors->has('customerEmail')])>
                    @error('customerEmail')
                        <span class="qt-field__error">{{ $message }}</span>
                    @enderror
                </label>
                <button type="submit" class="qt-btn qt-btn--primary"><x-icon name="check" />{{ __('invoices.form.save_email') }}</button>
            </form>
        </section>
    @elseif ($this->blocked)
        <section class="qt-card qt-panel-card qt-form-blocked" role="alert">
            <span class="qt-empty__icon"><x-icon name="alert" /></span>
            <p class="qt-empty__title">{{ __('invoices.form.blocked_title') }}</p>
            <p class="qt-empty__sub">{{ __('invoices.form.blocked.'.$this->blocked) }}</p>
            <a href="{{ $backUrl }}" class="qt-btn qt-btn--ghost"><x-icon name="arrow-left" />{{ __('invoices.form.back_quotation') }}</a>
        </section>
    @else
        <form wire:submit="save" novalidate>
            {{-- Một khung phiếu: đơn hàng (khách, khóa học, tổng / đã đóng / còn lại) + phần nhập kỳ thanh toán này --}}
            <x-invoice-receipt :order="$order" :invoice="$editing" :installment="$this->nextInstallment" :issued-date="$this->issuedDate()">
                <h3 class="iv-doc__part-title">{{ __('invoices.form.this_installment', ['number' => $this->nextInstallment]) }}</h3>

                {{-- HĐ21 – HĐ22: chọn hình thức một lần cho đơn --}}
                @if ($this->needsType)
                    <fieldset class="qt-field iv-form-block">
                        <legend class="qt-field__label">{{ __('invoices.payment_type_label') }} *</legend>
                        <div class="iv-checks">
                            @foreach (SalesOrder::PAYMENT_TYPES as $option)
                                <label class="iv-check" wire:key="type-{{ $option }}">
                                    <input type="radio" wire:model.live="paymentType" value="{{ $option }}">
                                    <span>{{ __('invoices.payment_type.'.$option) }}</span>
                                </label>
                            @endforeach
                        </div>
                        <span class="qt-field__help">{{ __('invoices.form.type_help') }}</span>
                        @error('paymentType')
                            <span class="qt-field__error">{{ $message }}</span>
                        @enderror
                    </fieldset>
                @endif

                <div class="qt-form-fields">
                    {{-- HĐ23: Toàn bộ → tự điền phần còn lại, khóa; Trả góp → SP nhập (tối đa = còn lại − đang chờ) --}}
                    <label class="qt-field">
                        <span class="qt-field__label">{{ __('invoices.amount') }} (VNĐ) *</span>
                        <input type="number" min="1" step="1000" max="{{ $limits['max'] }}" wire:model="amount"
                            @readonly($isFull) @disabled(! $type)
                            placeholder="{{ $type ? '' : __('invoices.form.pick_type_first') }}"
                            @class(['qt-control', 'iv-amount-input', 'is-locked' => $isFull, 'is-invalid' => $errors->has('amount')])>
                        <span class="qt-field__help">
                            @if ($isFull)
                                {{ __('invoices.form.amount_full') }}
                            @elseif ($type)
                                {{ __('invoices.form.amount_help', ['max' => $money($limits['max']), 'remaining' => $money($limits['remaining']), 'pending' => $money($limits['pending'])]) }}
                            @else
                                {{ __('invoices.form.pick_type_first') }}
                            @endif
                        </span>
                        @error('amount')
                            <span class="qt-field__error">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="qt-field">
                        <span class="qt-field__label">{{ __('invoices.due_date') }} *</span>
                        <input type="date" wire:model="dueDate" min="{{ $this->issuedDate() }}" @class(['qt-control', 'is-invalid' => $errors->has('dueDate')])>
                        @error('dueDate')
                            <span class="qt-field__error">{{ $message }}</span>
                        @enderror
                    </label>

                    {{-- HĐ24: phương thức của hóa đơn này --}}
                    <fieldset class="qt-field qt-field--wide">
                        <legend class="qt-field__label">{{ __('invoices.method_label') }} *</legend>
                        <div class="iv-checks">
                            @foreach (Invoice::METHODS as $option)
                                <label class="iv-check" wire:key="method-{{ $option }}">
                                    <input type="radio" wire:model.live="method" value="{{ $option }}">
                                    <span>{{ __('invoices.method.'.$option) }}</span>
                                </label>
                            @endforeach
                        </div>
                        @if ($method === Invoice::METHOD_CASH)
                            <span class="qt-field__help">{{ __('invoices.form.method_cash_help') }}</span>
                        @elseif ($method === Invoice::METHOD_BANK_TRANSFER)
                            <span class="qt-field__help">{{ __('invoices.form.method_transfer_help') }}</span>
                        @endif
                        @error('method')
                            <span class="qt-field__error">{{ $message }}</span>
                        @enderror
                    </fieldset>
                </div>

                @if ($limits['pending'] > 0)
                    <p class="iv-doc__note"><x-icon name="info" />{{ __('invoices.form.pending_note', ['amount' => $money($limits['pending'])]) }}</p>
                @endif

                <div class="qt-form-actions iv-doc__actions">
                    <a href="{{ $backUrl }}" class="qt-btn qt-btn--ghost">{{ __('invoices.form.cancel') }}</a>
                    <button type="submit" class="qt-btn qt-btn--primary" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save"><x-icon name="check" /></span>
                        <span wire:loading wire:target="save" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                        {{ $isEdit ? __('invoices.form.save_changes') : __('invoices.form.save') }}
                    </button>
                </div>
            </x-invoice-receipt>
        </form>
    @endif
</div>
