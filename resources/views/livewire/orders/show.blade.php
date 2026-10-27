@php
    use App\Models\Invoice;
    use App\Models\SalesOrder;
    use Illuminate\Support\Facades\Route;

    $order = $this->order;
    $prefix = $this->routePrefix;
    $listUrl = route($prefix.'.orders');
    $quotation = $order->quotation;
    $invoices = $order->invoices;

    $status = $order->status;
    $tone = in_array($status, SalesOrder::STATUSES, true) ? ($status === SalesOrder::STATUS_COMPLETED ? 'completed' : 'progress') : 'all';
    $paymentType = in_array($order->payment_type, SalesOrder::PAYMENT_TYPES, true) ? $order->payment_type : 'none';
    $percent = $order->progressPercent();
    $invoicedTotal = (float) $invoices->sum('payment_amount');

    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';

    $quotationUrl = $quotation && Route::has($prefix.'.quotations.show') ? route($prefix.'.quotations.show', $quotation) : null;
    $opportunityUrl = $quotation && Route::has($prefix.'.opportunities.show') ? route($prefix.'.opportunities.show', $quotation->opportunity_id) : null;
    $hasInvoiceShow = Route::has($prefix.'.invoices.show');
@endphp

<div class="qt qt-detail od-detail">
    {{-- Khung trang chi tiết (quotation.css) + nhãn đơn (order.css) + khung phiếu và nhãn hóa đơn (invoice.css) --}}
    @assets
        @vite(['resources/css/quotation.css', 'resources/css/order.css', 'resources/css/invoice.css'])
    @endassets

    <nav class="qt-crumb" aria-label="breadcrumb">
        <a href="{{ $listUrl }}" class="qt-crumb__back"><x-icon name="arrow-left" />{{ __('orders.detail.back') }}</a>
        <span class="qt-crumb__path">
            <a href="{{ $listUrl }}">{{ __('orders.title') }}</a>
            <x-icon name="chevron" />
            <span aria-current="page">{{ $order->order_id }}</span>
        </span>
    </nav>

    <header class="qt-detail-head">
        <h1 class="qt-detail-head__title">
            <span class="qt-detail-head__icon"><x-icon name="layers" /></span>
            <span class="qt-code__id">{{ $order->order_id }}</span>
        </h1>
        <span class="od-badge od-tone--{{ $tone }}">
            <span class="od-badge__dot"></span>{{ in_array($status, SalesOrder::STATUSES, true) ? __('orders.status.'.$status) : $status }}
        </span>
        <span class="qt-detail-head__meta"><x-icon name="calendar" />{{ __('orders.detail.created_on', ['date' => $order->created_at?->format('d/m/Y') ?? '—']) }}</span>
        <span class="qt-detail-head__meta"><x-icon name="wallet" />{{ __('orders.payment_type.'.$paymentType) }}</span>
    </header>

    @if ($this->canCreateInvoice)
        <section class="qt-actions" aria-label="{{ __('orders.detail.actions') }}">
            <div class="qt-actions__buttons">
                <a href="{{ route('salesperson.invoices.create', $order) }}" class="qt-btn qt-btn--primary">
                    <x-icon name="plus" />{{ $invoices->isEmpty() ? __('orders.create_invoice') : __('orders.detail.create_next_invoice') }}
                </a>
            </div>
        </section>
    @endif

    {{-- ── Một khung phiếu như chi tiết hóa đơn: khách, khóa học, tổng / đã đóng / còn lại + các kỳ hóa đơn ── --}}
    <x-invoice-receipt :order="$order" type="order">
        <x-slot:info>
            <div>
                <dt>{{ __('orders.status_label') }}</dt>
                <dd><span class="od-badge od-tone--{{ $tone }}"><span class="od-badge__dot"></span>{{ in_array($status, SalesOrder::STATUSES, true) ? __('orders.status.'.$status) : $status }}</span></dd>
            </div>
            <div>
                <dt>{{ __('orders.detail.opportunity') }}</dt>
                <dd>
                    @if ($opportunityUrl)
                        <a href="{{ $opportunityUrl }}">{{ $quotation->opportunity_id }}</a>
                    @else
                        {{ $quotation?->opportunity_id ?? '—' }}
                    @endif
                </dd>
            </div>
        </x-slot:info>

        <h3 class="iv-doc__part-title">{{ __('orders.detail.payment') }}</h3>
        <dl class="iv-doc__info iv-doc__info--pay">
            <div><dt>{{ __('orders.paid') }}</dt><dd>{{ $money($order->paid_amount) }} · {{ $percent }}%</dd></div>
            <div><dt>{{ __('orders.detail.invoiced') }}</dt><dd>{{ $money($invoicedTotal) }}</dd></div>
        </dl>

        {{-- Lịch sử các kỳ hóa đơn --}}
        <h3 class="iv-doc__part-title">{{ __('orders.detail.installments') }}</h3>
        @if ($invoices->isEmpty())
            <p class="iv-doc__note"><x-icon name="info" />{{ __('orders.detail.no_invoices') }}</p>
        @else
            <div class="iv-doc__lines">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('orders.detail.invoice_code') }}</th>
                            <th>{{ __('orders.detail.method') }}</th>
                            <th class="num">{{ __('orders.detail.amount') }}</th>
                            <th>{{ __('orders.detail.due') }}</th>
                            <th>{{ __('orders.detail.paid_at') }}</th>
                            <th>{{ __('orders.status_label') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $item)
                            <tr wire:key="inv-{{ $item->invoice_id }}">
                                <td data-label="{{ __('orders.detail.invoice_code') }}">
                                    @if ($hasInvoiceShow)
                                        <a href="{{ route($prefix.'.invoices.show', $item) }}" class="qt-mono">{{ $item->invoice_id }}</a>
                                    @else
                                        <span class="qt-mono">{{ $item->invoice_id }}</span>
                                    @endif
                                    <small>{{ __('invoices.installment', ['number' => $item->payment_installment]) }}</small>
                                </td>
                                <td data-label="{{ __('orders.detail.method') }}">{{ $item->payment_method ? __('invoices.method.'.$item->payment_method) : '—' }}</td>
                                <td class="num" data-label="{{ __('orders.detail.amount') }}">{{ $money($item->payment_amount) }}</td>
                                <td data-label="{{ __('orders.detail.due') }}">{{ $item->due_date?->format('d/m/Y') ?? '—' }}</td>
                                <td data-label="{{ __('orders.detail.paid_at') }}">
                                    {{ $item->payment_date?->format('d/m/Y H:i') ?? '—' }}
                                    @if ($item->status === Invoice::STATUS_PAID)
                                        <small>{{ $item->accountant?->full_name ?? __('invoices.detail.system') }}</small>
                                    @endif
                                </td>
                                <td data-label="{{ __('orders.status_label') }}">
                                    <span class="iv-badge iv-tone--{{ strtolower($item->status) }}"><span class="iv-badge__dot"></span>{{ in_array($item->status, Invoice::STATUSES, true) ? __('invoices.status.'.$item->status) : $item->status }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="iv-doc__note"><x-icon name="file" />{{ filled($order->notes) ? $order->notes : __('orders.detail.no_notes') }}</p>
        @if ($quotationUrl)
            <p class="iv-doc__note"><x-icon name="file" /><a href="{{ $quotationUrl }}">{{ __('invoices.detail.view_quotation', ['code' => $quotation->quotation_id]) }}</a></p>
        @endif
    </x-invoice-receipt>
</div>
