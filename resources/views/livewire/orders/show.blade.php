@php
    use App\Models\Invoice;
    use App\Models\SalesOrder;
    use Illuminate\Support\Facades\Route;

    $order = $this->order;
    $prefix = $this->routePrefix;
    $listUrl = route($prefix.'.orders');
    $quotation = $order->quotation;
    $lead = $quotation?->opportunity?->lead;
    $invoices = $order->invoices;

    $status = $order->status;
    $tone = in_array($status, SalesOrder::STATUSES, true) ? ($status === SalesOrder::STATUS_COMPLETED ? 'completed' : 'progress') : 'all';
    $paymentType = in_array($order->payment_type, SalesOrder::PAYMENT_TYPES, true) ? $order->payment_type : 'none';
    $percent = $order->progressPercent();
    $invoicedTotal = (float) $invoices->sum('payment_amount');

    // Khóa học + tổng tiền theo báo giá đã xác nhận; tổng / đã thanh toán / còn lại là giá trị ĐÃ LƯU trong SalesOrders
    $subtotal = (float) ($quotation?->details?->sum('line_total') ?? 0);
    $voucher = $quotation?->voucher;
    $discount = $voucher ? $voucher->discountFor($subtotal) : 0;

    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
    $vat = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').'%';
    $dash = fn ($value) => filled($value) ? $value : '-';

    $quotationUrl = $quotation && Route::has($prefix.'.quotations.show') ? route($prefix.'.quotations.show', [$quotation] + $this->trailQuery()) : null;
    $opportunityUrl = $quotation && Route::has($prefix.'.opportunities.show') ? route($prefix.'.opportunities.show', [$quotation->opportunity_id] + $this->trailQuery()) : null;
    $hasInvoiceShow = Route::has($prefix.'.invoices.show');
@endphp

<div class="qt qt-detail od-detail">
    {{-- Khung trang chi tiết (quotation.css) + nhãn đơn, tổng quan thanh toán (order.css) + nhãn hóa đơn (invoice.css) --}}
    @assets
        @vite(['resources/css/quotation.css', 'resources/css/order.css', 'resources/css/invoice.css'])
    @endassets

    @if ($trailStudent)
        @include('livewire.partials.student-trail', ['class' => 'qt-crumb', 'trail' => $this->studentTrail([['opportunities', $quotation?->opportunity_id], ['quotations', $quotation?->quotation_id], ['orders', $order->order_id]])])
    @else
        <nav class="qt-crumb" aria-label="breadcrumb">
            <a href="{{ $listUrl }}" class="odin-back"><x-icon name="arrow-left" />{{ __('orders.detail.back') }}</a>
            <span class="qt-crumb__path">
                <a href="{{ $listUrl }}">{{ __('orders.title') }}</a>
                <x-icon name="chevron" />
                <span aria-current="page">{{ $order->order_id }}</span>
            </span>
        </nav>
    @endif

    <header class="qt-detail-head">
        <h1 class="qt-detail-head__title">
            <span class="qt-detail-head__icon"><x-icon name="layers" /></span>
            <span class="qt-code__id">{{ $order->order_id }}</span>
        </h1>
        <span class="od-badge od-tone--{{ $tone }}">
            <span class="od-badge__dot"></span>{{ in_array($status, SalesOrder::STATUSES, true) ? __('orders.status.'.$status) : $status }}
        </span>
        <span class="qt-detail-head__meta"><x-icon name="calendar" />{{ __('orders.detail.created_on', ['date' => $order->created_at?->format('d/m/Y') ?? '-']) }}</span>
        <span class="qt-detail-head__meta"><x-icon name="wallet" />{{ __('orders.payment_type.'.$paymentType) }}</span>
    </header>

    @if ($notice)
        <p @class(['qt-notice', 'qt-notice--'.$notice['tone']]) role="status">
            <x-icon name="check-circle" />{{ $notice['text'] }}
        </p>
    @endif

    {{-- ── Tổng quan thanh toán: tổng / đã thanh toán / còn lại / đã lập hóa đơn + tiến độ ── --}}
    <section class="qt-card od-sum" aria-label="{{ __('orders.detail.payment') }}">
        <div class="od-sum__head">
            <h2 class="qt-section-title">{{ __('orders.detail.payment') }}</h2>
            @if ($this->canCreateInvoice)
                <a href="{{ route('salesperson.invoices.create', $order) }}" class="qt-btn qt-btn--primary">
                    <x-icon name="plus" />{{ $invoices->isEmpty() ? __('orders.create_invoice') : __('orders.detail.create_next_invoice') }}
                </a>
            @endif
        </div>

        <dl class="od-sum__stats">
            <div class="od-sum__stat">
                <dt>{{ __('orders.total') }}</dt>
                <dd>{{ $money($order->total_amount) }}</dd>
            </div>
            <div class="od-sum__stat is-paid">
                <dt>{{ __('orders.paid') }}</dt>
                <dd>{{ $money($order->paid_amount) }}</dd>
            </div>
            <div @class(['od-sum__stat', 'is-remaining' => (float) $order->remaining_amount > 0, 'is-zero' => (float) $order->remaining_amount <= 0])>
                <dt>{{ __('orders.remaining') }}</dt>
                <dd>{{ $money($order->remaining_amount) }}</dd>
            </div>
            <div class="od-sum__stat">
                <dt>{{ __('orders.detail.invoiced') }}</dt>
                <dd>{{ $money($invoicedTotal) }}</dd>
            </div>
        </dl>

        <div class="od-sum__progress">
            <span class="od-sum__bar" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('orders.paid') }}">
                <span class="od-sum__fill" style="width: {{ $percent }}%"></span>
            </span>
            <span class="od-sum__pct">{{ $percent }}%</span>
        </div>
    </section>

    <div class="qt-detail-grid">
        {{-- ── Cột trái: khóa học + tổng tiền, các kỳ hóa đơn ── --}}
        <div class="qt-detail-main">
            <section class="qt-card qt-results">
                <div class="qt-section-head">
                    <h2 class="qt-section-title">{{ __('orders.detail.courses') }}</h2>
                </div>

                @if (! $quotation || $quotation->details->isEmpty())
                    <p class="qt-detail-empty">{{ __('orders.detail.no_courses') }}</p>
                @else
                    <div class="qt-table-wrap">
                        <table class="qt-table qt-table--lines">
                            <thead>
                                <tr>
                                    <th>{{ __('orders.detail.course_name') }}</th>
                                    <th class="text-lg-end">{{ __('orders.detail.unit_price') }}</th>
                                    <th class="text-lg-center">{{ __('orders.detail.quantity') }}</th>
                                    <th class="text-lg-end">{{ __('orders.detail.vat') }}</th>
                                    <th class="text-lg-end">{{ __('orders.detail.line_total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quotation->details as $line)
                                    <tr wire:key="line-{{ $line->course_id }}">
                                        <td data-label="{{ __('orders.detail.course_name') }}">
                                            <span class="od-course">
                                                <strong>{{ $dash($line->course?->course_name) }}</strong>
                                                <small><span class="qt-mono">{{ $line->course_id }}</span>@if ($line->course?->duration) · {{ $line->course->duration }}@endif</small>
                                            </span>
                                        </td>
                                        <td data-label="{{ __('orders.detail.unit_price') }}" class="text-lg-end">{{ $money($line->unit_price) }}</td>
                                        <td data-label="{{ __('orders.detail.quantity') }}" class="text-lg-center">{{ $line->quantity }}</td>
                                        <td data-label="{{ __('orders.detail.vat') }}" class="text-lg-end">{{ $vat($line->VAT) }}</td>
                                        <td data-label="{{ __('orders.detail.line_total') }}" class="text-lg-end"><strong>{{ $money($line->line_total) }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <dl class="qt-totals">
                    <div class="qt-totals__row">
                        <dt>{{ __('orders.detail.subtotal') }}</dt>
                        <dd>{{ $money($subtotal) }}</dd>
                    </div>
                    @if ($voucher)
                        <div class="qt-totals__row qt-totals__row--discount">
                            <dt>{{ __('orders.detail.discount', ['code' => $voucher->voucher_id]) }}</dt>
                            <dd>−{{ $money($discount) }}</dd>
                        </div>
                    @endif
                    <div class="qt-totals__row qt-totals__row--grand">
                        <dt>{{ __('orders.total') }}</dt>
                        <dd>{{ $money($order->total_amount) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="qt-card qt-results">
                <div class="qt-section-head">
                    <h2 class="qt-section-title">{{ __('orders.detail.installments') }}</h2>
                </div>

                @if ($invoices->isEmpty())
                    <p class="qt-detail-empty">{{ __('orders.detail.no_invoices') }}</p>
                @else
                    <div class="qt-table-wrap">
                        <table class="qt-table qt-table--lines">
                            <thead>
                                <tr>
                                    <th>{{ __('orders.detail.invoice_code') }}</th>
                                    <th>{{ __('orders.detail.method') }}</th>
                                    <th class="text-lg-end">{{ __('orders.detail.amount') }}</th>
                                    <th>{{ __('orders.detail.due') }}</th>
                                    <th>{{ __('orders.detail.paid_at') }}</th>
                                    <th>{{ __('orders.status_label') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoices as $item)
                                    <tr wire:key="inv-{{ $item->invoice_id }}">
                                        <td data-label="{{ __('orders.detail.invoice_code') }}">
                                            <span class="od-course">
                                                @if ($hasInvoiceShow)
                                                    <a href="{{ route($prefix.'.invoices.show', [$item] + $this->trailQuery()) }}" class="qt-link qt-mono">{{ $item->invoice_id }}</a>
                                                @else
                                                    <span class="qt-mono">{{ $item->invoice_id }}</span>
                                                @endif
                                                <small>{{ __('invoices.installment', ['number' => $item->payment_installment]) }}</small>
                                            </span>
                                        </td>
                                        <td data-label="{{ __('orders.detail.method') }}">{{ $item->payment_method ? __('invoices.method.'.$item->payment_method) : '-' }}</td>
                                        <td data-label="{{ __('orders.detail.amount') }}" class="text-lg-end"><strong>{{ $money($item->payment_amount) }}</strong></td>
                                        <td data-label="{{ __('orders.detail.due') }}">{{ $item->due_date?->format('d/m/Y') ?? '-' }}</td>
                                        <td data-label="{{ __('orders.detail.paid_at') }}">
                                            <span class="od-course">
                                                <span>{{ $item->payment_date?->format('d/m/Y H:i') ?? '-' }}</span>
                                                @if ($item->status === Invoice::STATUS_PAID)
                                                    <small>{{ $item->accountant?->full_name ?? __('invoices.detail.system') }}</small>
                                                @endif
                                            </span>
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
            </section>
        </div>

        {{-- ── Cột phải: khách hàng, thông tin đơn ── --}}
        <aside class="qt-detail-side">
            <section class="qt-card qt-panel-card">
                <h2 class="qt-section-title">{{ __('orders.detail.customer') }}</h2>
                <dl class="qt-info qt-info--stack">
                    <div class="qt-info__row">
                        <dt>{{ __('orders.detail.full_name') }}</dt>
                        <dd><strong>{{ $dash($lead?->full_name) }}</strong></dd>
                    </div>
                    <div class="qt-info__row">
                        <dt>{{ __('orders.detail.phone') }}</dt>
                        <dd>{{ $dash($lead?->phone) }}</dd>
                    </div>
                    <div class="qt-info__row">
                        <dt>{{ __('orders.detail.email') }}</dt>
                        <dd class="od-break">{{ $dash($lead?->email) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="qt-card qt-panel-card">
                <h2 class="qt-section-title">{{ __('orders.detail.general') }}</h2>
                <dl class="qt-info qt-info--stack">
                    <div class="qt-info__row">
                        <dt>{{ __('orders.quotation') }}</dt>
                        <dd>
                            @if ($quotationUrl)
                                <a href="{{ $quotationUrl }}" class="qt-link qt-mono">{{ $quotation->quotation_id }}</a>
                            @else
                                <span class="qt-mono">{{ $quotation?->quotation_id ?? '-' }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="qt-info__row">
                        <dt>{{ __('orders.detail.opportunity') }}</dt>
                        <dd>
                            @if ($opportunityUrl)
                                <a href="{{ $opportunityUrl }}" class="qt-link qt-mono">{{ $quotation->opportunity_id }}</a>
                            @else
                                <span class="qt-mono">{{ $quotation?->opportunity_id ?? '-' }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="qt-info__row">
                        <dt>{{ __('orders.employee') }}</dt>
                        <dd>{{ $dash($quotation?->employee?->full_name) }}</dd>
                    </div>
                    <div class="qt-info__row">
                        <dt>{{ __('orders.detail.created_at') }}</dt>
                        <dd>{{ $order->created_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                    </div>
                    <div class="qt-info__row">
                        <dt>{{ __('orders.payment_type_label') }}</dt>
                        <dd>{{ __('orders.payment_type.'.$paymentType) }}</dd>
                    </div>
                    <div class="qt-info__row">
                        <dt>{{ __('orders.detail.notes') }}</dt>
                        <dd class="qt-notes">{{ filled($order->notes) ? $order->notes : __('orders.detail.no_notes') }}</dd>
                    </div>
                </dl>
            </section>
        </aside>
    </div>
</div>
