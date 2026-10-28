@php
    // Mẫu PDF hóa đơn đính kèm email gửi khách sau khi thanh toán thành công (dompdf, cùng bố cục PDF báo giá).
    $order = $invoice->order;
    $quotation = $order?->quotation;
    $lead = $quotation?->opportunity?->lead;
    $branch = $lead?->branch;
    $voucher = $quotation?->voucher;
    $isPaid = $invoice->status === \App\Models\Invoice::STATUS_PAID;

    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
    $percent = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').'%';
    $P = fn ($key, $params = []) => __('invoices.pdf.'.$key, $params);
    // Logo PNG cần extension GD của PHP; máy chưa bật GD thì bỏ logo thay vì lỗi
    $logo = extension_loaded('gd') ? public_path('images/logoodin-trim.png') : null;
@endphp
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>{{ $P('title') }} {{ $invoice->invoice_id }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        .head { width: 100%; border-bottom: 2px solid #005e12; padding-bottom: 10px; margin-bottom: 16px; }
        .head td { vertical-align: middle; }
        .logo { height: 52px; }
        .company { text-align: right; font-size: 10px; color: #4b5563; line-height: 1.5; }
        h1 { margin: 0 0 4px; font-size: 20px; color: #005e12; letter-spacing: 1px; }
        .meta { color: #4b5563; margin-bottom: 14px; line-height: 1.6; }
        .meta strong { color: #1f2937; }
        .stamp { float: right; margin-top: -34px; padding: 4px 12px; border: 2px solid #005e12; border-radius: 4px; color: #005e12; font-weight: bold; font-size: 12px; letter-spacing: 1px; }
        .boxes { width: 100%; margin-bottom: 16px; border-collapse: separate; border-spacing: 0; }
        .boxes td { width: 50%; vertical-align: top; padding: 10px 12px; background: #f3f6f2; }
        .boxes td + td { border-left: 8px solid #fff; }
        .label { font-size: 9px; text-transform: uppercase; color: #6b7280; letter-spacing: .5px; margin-bottom: 4px; }
        .lines { width: 100%; border-collapse: collapse; }
        .lines th { background: #005e12; color: #fff; font-size: 10px; text-align: left; padding: 7px 8px; }
        .lines td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; }
        .num, .lines th.num { text-align: right; white-space: nowrap; }
        .totals { width: 52%; margin: 12px 0 0 auto; border-collapse: collapse; }
        .totals td { padding: 5px 8px; }
        .totals .sep td { border-top: 1px solid #d1d5db; }
        .totals .grand td { border-top: 1px solid #1f2937; font-size: 13px; font-weight: bold; color: #005e12; }
        .discount { color: #b91c1c; }
        .pay { margin-top: 18px; padding: 10px 12px; border-left: 3px solid #005e12; background: #f3f6f2; }
        .foot { margin-top: 26px; font-size: 10px; color: #4b5563; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>@if ($logo && is_file($logo))<img class="logo" src="{{ $logo }}" alt="ODIN">@endif</td>
            <td class="company">
                <strong>{{ __('quotations.pdf.company') }}</strong><br>
                @if ($branch)
                    {{ $branch->branch_name }}@if ($branch->address) · {{ $branch->address }}@endif<br>
                    @if ($branch->phone){{ __('quotations.detail.phone') }}: {{ $branch->phone }}@endif
                    @if ($branch->email) · {{ $branch->email }}@endif
                @endif
            </td>
        </tr>
    </table>

    <h1>{{ mb_strtoupper($P('title')) }}</h1>
    @if ($isPaid)<span class="stamp">{{ mb_strtoupper($P('paid_stamp')) }}</span>@endif
    <div class="meta">
        {{ $P('number') }}: <strong>{{ $invoice->invoice_id }}</strong>
        · {{ $P('installment') }}: <strong>{{ $invoice->payment_installment }}</strong>
        · {{ $P('issued') }}: <strong>{{ $invoice->issued_date?->format('d/m/Y') }}</strong><br>
        {{ $P('order') }}: <strong>{{ $order?->order_id }}</strong>
        · {{ $P('quotation') }}: <strong>{{ $quotation?->quotation_id }}</strong>
    </div>

    <table class="boxes">
        <tr>
            <td>
                <div class="label">{{ __('quotations.pdf.customer') }}</div>
                <strong>{{ $lead?->full_name ?? '—' }}</strong><br>
                @if ($lead?->phone){{ __('quotations.detail.phone') }}: {{ $lead->phone }}<br>@endif
                @if ($lead?->email){{ __('quotations.detail.email') }}: {{ $lead->email }}@endif
            </td>
            <td>
                <div class="label">{{ __('quotations.pdf.consultant') }}</div>
                <strong>{{ $quotation?->employee?->full_name ?? '—' }}</strong><br>
                @if ($quotation?->employee?->phone){{ __('quotations.detail.phone') }}: {{ $quotation->employee->phone }}<br>@endif
                @if ($quotation?->employee?->email){{ __('quotations.detail.email') }}: {{ $quotation->employee->email }}@endif
            </td>
        </tr>
    </table>

    {{-- Khóa học của đơn hàng (theo báo giá đã xác nhận) --}}
    @if ($quotation)
        <table class="lines">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('quotations.detail.course_name') }}</th>
                    <th class="num">{{ __('quotations.detail.unit_price') }}</th>
                    <th class="num">{{ __('quotations.detail.quantity') }}</th>
                    <th class="num">{{ __('quotations.detail.vat') }}</th>
                    <th class="num">{{ __('quotations.detail.line_total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quotation->details as $i => $detail)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $detail->course?->course_name ?? $detail->course_id }}</td>
                        <td class="num">{{ $money($detail->unit_price) }}</td>
                        <td class="num">{{ $detail->quantity }}</td>
                        <td class="num">{{ $percent($detail->VAT) }}</td>
                        <td class="num">{{ $money($detail->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="totals">
        @if ($quotation)
            <tr><td>{{ __('quotations.detail.subtotal') }}</td><td class="num">{{ $money($quotation->subtotal()) }}</td></tr>
            @if ($voucher)
                <tr class="discount"><td>{{ __('quotations.detail.discount', ['code' => $voucher->voucher_id, 'value' => $voucher->displayValue()]) }}</td><td class="num">−{{ $money($quotation->discountAmount()) }}</td></tr>
            @endif
        @endif
        <tr class="sep"><td>{{ $P('order_total') }}</td><td class="num">{{ $money($order?->total_amount) }}</td></tr>
        <tr><td>{{ $P('paid_total') }}</td><td class="num">{{ $money($order?->paid_amount) }}</td></tr>
        <tr><td>{{ $P('remaining') }}</td><td class="num">{{ $money($order?->remaining_amount) }}</td></tr>
        <tr class="grand"><td>{{ $P('this_payment', ['number' => $invoice->payment_installment]) }}</td><td class="num">{{ $money($invoice->payment_amount) }}</td></tr>
    </table>

    @if ($isPaid)
        <div class="pay">
            <div class="label">{{ $P('payment') }}</div>
            {{ $P('method') }}: <strong>{{ $invoice->payment_method ? __('invoices.method.'.$invoice->payment_method) : '—' }}</strong>
            · {{ $P('paid_at') }}: <strong>{{ $invoice->payment_date?->format('d/m/Y H:i') }}</strong><br>
            {{ $invoice->payment_method === \App\Models\Invoice::METHOD_CASH
                ? $P('confirmed_cash', ['name' => $invoice->accountant?->full_name ?? '—'])
                : $P('confirmed_transfer') }}
        </div>
    @endif

    <p class="foot">{{ (float) $order?->remaining_amount > 0 ? $P('footer_remaining') : $P('footer_completed') }}</p>
</body>
</html>
