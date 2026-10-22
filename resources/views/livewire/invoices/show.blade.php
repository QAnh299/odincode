@php
    use App\Models\Invoice;
    use App\Models\SalesOrder;
    use Illuminate\Support\Facades\Route;

    $invoice = $this->invoice;
    $prefix = $this->routePrefix;
    $order = $invoice->order;
    $quotation = $order?->quotation;
    $lead = $quotation?->opportunity?->lead;

    $status = $invoice->status;
    $tone = in_array($status, Invoice::STATUSES, true) ? strtolower($status) : 'all';
    $isUnpaid = $status === Invoice::STATUS_UNPAID;
    $isCash = $invoice->payment_method === Invoice::METHOD_CASH;
    $isTransfer = $invoice->payment_method === Invoice::METHOD_BANK_TRANSFER;
    $daysLeft = $status !== Invoice::STATUS_PAID ? $invoice->daysUntilDue() : null;

    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
    $listUrl = route($prefix.'.invoices');
    $quotationUrl = $quotation && Route::has($prefix.'.quotations.show') ? route($prefix.'.quotations.show', $quotation) : null;
    $paymentType = in_array($order?->payment_type, SalesOrder::PAYMENT_TYPES, true) ? $order->payment_type : null;
@endphp

<div class="qt qt-detail iv">
    {{-- Dùng lại khung trang chi tiết của báo giá (quotation.css) + nhãn trạng thái hóa đơn (invoice.css) --}}
    @assets
        @vite(['resources/css/quotation.css', 'resources/css/invoice.css'])
    @endassets

    <nav class="qt-crumb" aria-label="breadcrumb">
        <a href="{{ $listUrl }}" class="qt-crumb__back"><x-icon name="arrow-left" />{{ __('invoices.detail.back') }}</a>
        <span class="qt-crumb__path">
            <a href="{{ $listUrl }}">{{ __('invoices.title') }}</a>
            <x-icon name="chevron" />
            <span aria-current="page">{{ $invoice->invoice_id }}</span>
        </span>
    </nav>

    <header class="qt-detail-head">
        <h1 class="qt-detail-head__title">
            <span class="qt-detail-head__icon"><x-icon name="ticket" /></span>
            <span class="qt-code__id">{{ $invoice->invoice_id }}</span>
        </h1>
        <span class="iv-badge iv-tone--{{ $tone }}">
            <span class="iv-badge__dot"></span>{{ in_array($status, Invoice::STATUSES, true) ? __('invoices.status.'.$status) : $status }}
        </span>
        <span class="qt-detail-head__meta"><x-icon name="layers" />{{ __('invoices.installment', ['number' => $invoice->payment_installment]) }}</span>
        <span class="qt-detail-head__meta"><x-icon name="calendar" />{{ __('invoices.detail.due_on', ['date' => $invoice->due_date?->format('d/m/Y')]) }}</span>
        @if ($daysLeft !== null)
            <span @class(['iv-due', 'iv-due--late' => $daysLeft < 0])>
                <x-icon :name="$daysLeft < 0 ? 'alert' : 'hourglass'" />
                {{ $daysLeft < 0 ? __('invoices.days_late', ['days' => -$daysLeft]) : __('invoices.days_left', ['days' => $daysLeft]) }}
            </span>
        @endif
    </header>

    @if ($notice)
        <p @class(['qt-notice', 'qt-notice--'.$notice['tone']]) role="status">
            <x-icon :name="$notice['tone'] === 'success' ? 'check-circle' : ($notice['tone'] === 'danger' ? 'alert' : 'info')" />{{ $notice['text'] }}
        </p>
    @endif

    {{-- ── Trạng thái thanh toán + thao tác theo vai trò ── --}}
    <section @class(['qt-actions', 'iv-pay', 'iv-pay--'.$tone])>
        <p class="iv-pay__state">
            @if ($status === Invoice::STATUS_PAID)
                <x-icon name="check-circle" />
                {{ $isCash
                    ? __('invoices.detail.paid_cash', ['name' => $invoice->accountant?->full_name ?? '—', 'date' => $invoice->payment_date?->format('d/m/Y H:i')])
                    : __('invoices.detail.paid_transfer', ['date' => $invoice->payment_date?->format('d/m/Y H:i')]) }}
            @elseif ($status === Invoice::STATUS_OVERDUE)
                <x-icon name="alert" />{{ __('invoices.detail.overdue_note') }}
            @elseif ($isCash)
                {{-- HĐ30 --}}
                <x-icon name="hourglass" />{{ __('invoices.detail.cash_waiting') }}
            @elseif ($isTransfer)
                <x-icon name="clock" />{{ __('invoices.detail.transfer_waiting') }}
            @else
                <x-icon name="info" />{{ __('invoices.detail.no_method') }}
            @endif
        </p>

        <div class="qt-actions__buttons">
            @if ($this->canApproveCash)
                {{-- HĐ32: kế toán đã đối chiếu số tiền khách nộp khớp hóa đơn --}}
                <button type="button" class="qt-btn qt-btn--primary" wire:click="approveCash" wire:loading.attr="disabled" wire:target="approveCash"
                    wire:confirm="{{ __('invoices.detail.approve_confirm', ['code' => $invoice->invoice_id, 'amount' => $money($invoice->payment_amount)]) }}">
                    <x-icon name="check-circle" />{{ __('invoices.detail.approve_cash') }}
                </button>
            @endif
            @if ($this->canConfirmTransfer)
                {{-- Ngoại lệ: khách chuyển lệch số tiền, kế toán đối chiếu sao kê rồi xác nhận thủ công --}}
                <button type="button" class="qt-btn qt-btn--primary" wire:click="confirmTransfer" wire:loading.attr="disabled" wire:target="confirmTransfer"
                    wire:confirm="{{ __('invoices.detail.confirm_transfer_confirm', ['code' => $invoice->invoice_id, 'amount' => $money($invoice->payment_amount)]) }}">
                    <x-icon name="check-circle" />{{ __('invoices.detail.confirm_transfer') }}
                </button>
            @endif
            @if ($this->canSimulate)
                <button type="button" class="qt-btn qt-btn--ghost" wire:click="simulateTransfer" wire:loading.attr="disabled" wire:target="simulateTransfer"
                    wire:confirm="{{ __('invoices.detail.simulate_confirm') }}" title="{{ __('invoices.detail.simulate_hint') }}">
                    <x-icon name="refresh" />{{ __('invoices.detail.simulate') }}
                </button>
                <button type="button" class="qt-btn qt-btn--ghost" wire:click="simulateShortTransfer" wire:loading.attr="disabled" wire:target="simulateShortTransfer"
                    title="{{ __('invoices.detail.simulate_hint') }}">
                    <x-icon name="alert" />{{ __('invoices.detail.simulate_short') }}
                </button>
            @endif
            @if ($this->canEdit)
                <a href="{{ route('salesperson.invoices.edit', $invoice) }}" class="qt-btn qt-btn--ghost"><x-icon name="edit" />{{ __('invoices.detail.edit') }}</a>
            @endif
            @if ($this->canCreateNext)
                <a href="{{ route('salesperson.invoices.create', $order) }}" class="qt-btn qt-btn--ghost"><x-icon name="plus" />{{ __('invoices.detail.create_next') }}</a>
            @endif
        </div>
        @if ($alert = $this->transferAlert)
            {{-- Khách chuyển lệch số tiền: hệ thống không ghi nhận, báo kế toán và SP --}}
            <p class="qt-notice qt-notice--danger" role="alert"><x-icon name="alert" />{{ __('invoices.detail.transfer_mismatch', [
                'received' => $money($alert['amount']), 'amount' => $money($invoice->payment_amount), 'time' => $alert['at']->format('d/m/Y H:i'),
            ]) }}</p>
        @endif
        @if ($this->canConfirmTransfer)
            <p class="qt-actions__hint"><x-icon name="info" />{{ __('invoices.detail.confirm_transfer_hint') }}</p>
        @endif
        @if ($this->canApproveCash)
            <p class="qt-actions__hint"><x-icon name="info" />{{ __('invoices.detail.approve_hint') }}</p>
        @endif
    </section>

    {{-- ── Một khung phiếu: đơn hàng (khách, khóa học, tổng / đã đóng / còn lại) + thanh toán kỳ này ── --}}
    @if ($order)
        <x-invoice-receipt :order="$order" :invoice="$invoice" :installment="$invoice->payment_installment">
            <h3 class="iv-doc__part-title">{{ __('invoices.form.this_installment', ['number' => $invoice->payment_installment]) }}</h3>

            <div @class(['iv-doc__pay', 'has-qr' => $isUnpaid && $isTransfer])>
                <dl class="iv-doc__info iv-doc__info--pay">
                    <div class="is-amount"><dt>{{ __('invoices.amount') }}</dt><dd>{{ $money($invoice->payment_amount) }}</dd></div>
                    <div><dt>{{ __('invoices.method_label') }}</dt><dd>{{ $invoice->payment_method ? __('invoices.method.'.$invoice->payment_method) : '—' }}</dd></div>
                    <div><dt>{{ __('invoices.issued_date') }}</dt><dd>{{ $invoice->issued_date?->format('d/m/Y') }}</dd></div>
                    <div><dt>{{ __('invoices.due_date') }}</dt><dd>{{ $invoice->due_date?->format('d/m/Y') }}</dd></div>
                    <div>
                        <dt>{{ __('invoices.status_label') }}</dt>
                        <dd><span class="iv-badge iv-tone--{{ $tone }}"><span class="iv-badge__dot"></span>{{ in_array($status, Invoice::STATUSES, true) ? __('invoices.status.'.$status) : $status }}</span></dd>
                    </div>
                    <div>
                        <dt>{{ __('invoices.detail.confirmed_by') }}</dt>
                        <dd>
                            @if ($status !== Invoice::STATUS_PAID)
                                —
                            @elseif ($isCash)
                                {{ $invoice->accountant?->full_name ?? '—' }} · {{ $invoice->payment_date?->format('d/m/Y H:i') }}
                            @else
                                {{ __('invoices.detail.system') }} · {{ $invoice->payment_date?->format('d/m/Y H:i') }}
                            @endif
                        </dd>
                    </div>
                </dl>

                {{-- HĐ27: QR chuyển khoản (đúng số tiền, nội dung = mã hóa đơn) --}}
                @if ($isUnpaid && $isTransfer)
                    <div class="iv-qr">
                        <img src="{{ $invoice->qrUrl() }}" alt="{{ __('invoices.detail.qr_alt', ['code' => $invoice->invoice_id]) }}" class="iv-qr__img" loading="lazy">
                        <p class="iv-qr__meta">
                            {{ config('services.vietqr.bank_id') }} · {{ config('services.vietqr.account_no') }}<br>
                            {{ config('services.vietqr.account_name') }}<br>
                            {{ __('invoices.detail.qr_content') }}: <strong>{{ $invoice->invoice_id }}</strong>
                        </p>
                    </div>
                @endif
            </div>

            {{-- Các kỳ thanh toán của đơn --}}
            @if ($order->invoices->count() > 1)
                <h3 class="iv-doc__part-title">{{ __('invoices.detail.installments_title') }}</h3>
                <div class="iv-doc__lines">
                    <table>
                        <thead>
                            <tr>
                                <th>{{ __('invoices.code') }}</th>
                                <th>{{ __('invoices.method_label') }}</th>
                                <th class="num">{{ __('invoices.amount') }}</th>
                                <th>{{ __('invoices.status_label') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->invoices as $item)
                                <tr wire:key="sib-{{ $item->invoice_id }}" @class(['iv-current' => $item->invoice_id === $invoice->invoice_id])>
                                    <td data-label="{{ __('invoices.code') }}">
                                        <a href="{{ route($prefix.'.invoices.show', $item) }}" class="qt-mono">{{ $item->invoice_id }}</a>
                                        <small>{{ __('invoices.installment', ['number' => $item->payment_installment]) }}</small>
                                    </td>
                                    <td data-label="{{ __('invoices.method_label') }}">{{ $item->payment_method ? __('invoices.method.'.$item->payment_method) : '—' }}</td>
                                    <td class="num" data-label="{{ __('invoices.amount') }}">{{ $money($item->payment_amount) }}</td>
                                    <td data-label="{{ __('invoices.status_label') }}">
                                        <span class="iv-badge iv-tone--{{ strtolower($item->status) }}"><span class="iv-badge__dot"></span>{{ __('invoices.status.'.$item->status) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($quotationUrl)
                <p class="iv-doc__note"><x-icon name="file" /><a href="{{ $quotationUrl }}">{{ __('invoices.detail.view_quotation', ['code' => $quotation->quotation_id]) }}</a></p>
            @endif

            {{-- Ký nhận như phiếu thu --}}
            <div class="iv-doc__sign">
                <div><strong>{{ __('invoices.receipt.payer') }}</strong><span>{{ __('invoices.receipt.sign_hint') }}</span></div>
                <div><strong>{{ __('invoices.receipt.receiver') }}</strong><span>{{ __('invoices.receipt.sign_hint') }}</span>
                    @if ($status === Invoice::STATUS_PAID && $isCash)<em>{{ $invoice->accountant?->full_name }}</em>@endif
                </div>
            </div>
        </x-invoice-receipt>
    @endif
</div>
