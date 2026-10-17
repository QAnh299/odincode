@props(['order', 'invoice' => null, 'installment' => 1, 'issuedDate' => null])

@php
    // Khung phiếu hóa đơn dùng chung cho trang tạo / sửa (livewire/invoices/form) và chi tiết (livewire/invoices/show).
    // Bố cục theo phiếu thu của ODIN: đầu phiếu (logo + chi nhánh), tiêu đề + ngày + số phiếu, thông tin khách,
    // bảng khóa học của đơn, tổng đơn / đã đóng / còn lại; phần riêng của từng trang đặt vào $slot (cuối phiếu).
    // Chỉ hiện trường có trong CSDL của nhóm (không có ngày sinh, CCCD).
    use App\Models\SalesOrder;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $quotation = $order->quotation;
    $lead = $quotation?->opportunity?->lead;
    $branch = $lead?->branch;
    $consultant = $invoice?->createdBy ?? $quotation?->employee;
    $date = Carbon::parse($issuedDate ?? $invoice?->issued_date ?? today());
    $subtotal = (float) ($quotation?->details?->sum('line_total') ?? 0);
    $voucher = $quotation?->voucher;
    $discount = $voucher ? $voucher->discountFor($subtotal) : 0;
    $paymentType = in_array($order->payment_type, SalesOrder::PAYMENT_TYPES, true) ? $order->payment_type : null;

    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
    $percent = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').'%';
@endphp

<article {{ $attributes->merge(['class' => 'iv-doc']) }}>
    {{-- Đầu phiếu: logo + chi nhánh của khách --}}
    <header class="iv-doc__top">
        <img src="{{ asset('images/logoodin-trim.png') }}" alt="ODIN Language Center" class="iv-doc__logo">
        <div class="iv-doc__branch">
            <strong>{{ $branch?->branch_name ?? 'ODIN Language Center' }}</strong>
            @if ($branch?->address)<span>{{ __('invoices.receipt.address') }}: {{ $branch->address }}</span>@endif
            @if ($branch?->phone)<span>{{ __('invoices.receipt.phone') }}: {{ $branch->phone }}</span>@endif
            @if ($branch?->email)<span>{{ __('invoices.receipt.email') }}: {{ $branch->email }}</span>@endif
        </div>
    </header>

    <div class="iv-doc__title">
        <h2>{{ __('invoices.receipt.title') }}</h2>
        <p class="iv-doc__date">{{ Str::ucfirst($date->locale(app()->getLocale())->isoFormat(__('invoices.receipt.date_format'))) }}</p>
        <p class="iv-doc__no">
            {{ __('invoices.receipt.number') }}: <strong>{{ $invoice?->invoice_id ?? __('invoices.receipt.number_pending') }}</strong>
            · {{ __('invoices.installment', ['number' => $installment]) }}
            · {{ __('invoices.order') }} <strong>{{ $order->order_id }}</strong>
        </p>
    </div>

    {{-- Thông tin khách + đơn --}}
    <dl class="iv-doc__info">
        <div><dt>{{ __('invoices.receipt.customer') }}</dt><dd>{{ $lead?->full_name ?? '—' }}</dd></div>
        <div><dt>{{ __('invoices.receipt.phone') }}</dt><dd>{{ $lead?->phone ?: '—' }}</dd></div>
        <div><dt>{{ __('invoices.receipt.email') }}</dt><dd>{{ $lead?->email ?: '—' }}</dd></div>
        <div><dt>{{ __('invoices.receipt.consultant') }}</dt><dd>{{ $consultant?->full_name ?? '—' }}</dd></div>
        <div><dt>{{ __('invoices.receipt.quotation') }}</dt><dd>{{ $quotation?->quotation_id ?? '—' }}</dd></div>
        {{-- Đơn chưa xác định hình thức: không hiện dòng này (SP chọn ở phần nhập bên dưới – HĐ21) --}}
        @if ($paymentType)
            <div><dt>{{ __('invoices.payment_type_label') }}</dt><dd>{{ __('invoices.payment_type.'.$paymentType) }}</dd></div>
        @endif
    </dl>

    {{-- Khóa học của đơn (từ báo giá đã xác nhận) --}}
    <div class="iv-doc__lines">
        <table>
            <thead>
                <tr>
                    <th>{{ __('invoices.receipt.course') }}</th>
                    <th class="num">{{ __('invoices.receipt.unit_price') }}</th>
                    <th class="num">{{ __('invoices.receipt.quantity') }}</th>
                    <th class="num">{{ __('invoices.receipt.vat') }}</th>
                    <th class="num">{{ __('invoices.receipt.line_total') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($quotation?->details ?? [] as $detail)
                    <tr>
                        <td data-label="{{ __('invoices.receipt.course') }}">
                            {{ $detail->course?->course_name ?? $detail->course_id }}
                            @if ($detail->course?->duration)<small>{{ $detail->course->duration }}</small>@endif
                        </td>
                        <td class="num" data-label="{{ __('invoices.receipt.unit_price') }}">{{ $money($detail->unit_price) }}</td>
                        <td class="num" data-label="{{ __('invoices.receipt.quantity') }}">{{ $detail->quantity }}</td>
                        <td class="num" data-label="{{ __('invoices.receipt.vat') }}">{{ $percent($detail->VAT) }}</td>
                        <td class="num" data-label="{{ __('invoices.receipt.line_total') }}">{{ $money($detail->line_total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">—</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Tổng đơn (giá trị đã lưu trong SalesOrders) / đã đóng / còn lại --}}
    <dl class="iv-doc__sum">
        <div><dt>{{ __('invoices.receipt.subtotal') }}</dt><dd>{{ $money($subtotal) }}</dd></div>
        @if ($voucher)
            <div class="is-discount"><dt>{{ __('invoices.receipt.voucher', ['code' => $voucher->voucher_id]) }}</dt><dd>−{{ $money($discount) }}</dd></div>
        @endif
        <div class="is-total"><dt>{{ __('invoices.receipt.order_total') }}</dt><dd>{{ $money($order->total_amount) }}</dd></div>
        <div><dt>{{ __('invoices.receipt.paid') }}</dt><dd>{{ $money($order->paid_amount) }}</dd></div>
        <div class="is-remaining"><dt>{{ __('invoices.receipt.remaining') }}</dt><dd>{{ $money($order->remaining_amount) }}</dd></div>
    </dl>

    {{-- Phần riêng của từng trang: nhập kỳ thanh toán (tạo / sửa) hoặc thanh toán kỳ này (chi tiết) --}}
    @if (trim($slot) !== '')
        <section class="iv-doc__part">
            {{ $slot }}
        </section>
    @endif
</article>
