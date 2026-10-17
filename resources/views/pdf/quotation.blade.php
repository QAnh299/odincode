@php
    // Mẫu PDF báo giá (dompdf). Font DejaVu Sans có sẵn trong dompdf và hiển thị đủ dấu tiếng Việt.
    $lead = $quotation->opportunity?->lead;
    $branch = $lead?->branch;
    $voucher = $quotation->voucher;
    $subtotal = $quotation->subtotal();
    $discount = $quotation->discountAmount();
    $total = $quotation->totalAmount();

    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
    $percent = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').'%';
    // Logo PNG cần extension GD của PHP; máy chưa bật GD thì bỏ logo thay vì lỗi 500
    $logo = extension_loaded('gd') ? public_path('images/logoodin-trim.png') : null;
@endphp
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>{{ __('quotations.pdf.title') }} {{ $quotation->quotation_id }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        .head { width: 100%; border-bottom: 2px solid #005e12; padding-bottom: 10px; margin-bottom: 16px; }
        .head td { vertical-align: middle; }
        .logo { height: 52px; }
        .company { text-align: right; font-size: 10px; color: #4b5563; line-height: 1.5; }
        h1 { margin: 0 0 4px; font-size: 20px; color: #005e12; letter-spacing: 1px; }
        .meta { color: #4b5563; margin-bottom: 14px; }
        .meta strong { color: #1f2937; }
        .boxes { width: 100%; margin-bottom: 16px; border-collapse: separate; border-spacing: 0; }
        .boxes td { width: 50%; vertical-align: top; padding: 10px 12px; background: #f3f6f2; }
        .boxes td + td { border-left: 8px solid #fff; }
        .label { font-size: 9px; text-transform: uppercase; color: #6b7280; letter-spacing: .5px; margin-bottom: 4px; }
        .lines { width: 100%; border-collapse: collapse; }
        .lines th { background: #005e12; color: #fff; font-size: 10px; text-align: left; padding: 7px 8px; }
        .lines td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; }
        .num, .lines th.num { text-align: right; white-space: nowrap; }
        .totals { width: 46%; margin: 12px 0 0 auto; border-collapse: collapse; }
        .totals td { padding: 5px 8px; }
        .totals .grand td { border-top: 1px solid #1f2937; font-size: 13px; font-weight: bold; color: #005e12; }
        .discount { color: #b91c1c; }
        .notes { margin-top: 18px; padding: 10px 12px; border-left: 3px solid #fcda43; background: #fffbea; }
        .foot { margin-top: 26px; font-size: 10px; color: #4b5563; }
        .sign { width: 100%; margin-top: 30px; }
        .sign td { width: 50%; text-align: center; vertical-align: top; }
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

    <h1>{{ mb_strtoupper(__('quotations.pdf.title')) }}</h1>
    <div class="meta">
        {{ __('quotations.pdf.number') }}: <strong>{{ $quotation->quotation_id }}</strong>
        · {{ __('quotations.pdf.date') }}: <strong>{{ $quotation->created_at?->format('d/m/Y') }}</strong>
        · {{ __('quotations.pdf.valid_until') }}: <strong>{{ $quotation->expiry_date?->format('d/m/Y') }}</strong>
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
                <strong>{{ $quotation->employee?->full_name ?? '—' }}</strong><br>
                @if ($quotation->employee?->phone){{ __('quotations.detail.phone') }}: {{ $quotation->employee->phone }}<br>@endif
                @if ($quotation->employee?->email){{ __('quotations.detail.email') }}: {{ $quotation->employee->email }}@endif
            </td>
        </tr>
    </table>

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
                    <td>{{ $detail->course?->course_name ?? $detail->course_id }}@if ($detail->course?->duration) <br><span style="color:#6b7280">{{ $detail->course->duration }}</span>@endif</td>
                    <td class="num">{{ $money($detail->unit_price) }}</td>
                    <td class="num">{{ $detail->quantity }}</td>
                    <td class="num">{{ $percent($detail->VAT) }}</td>
                    <td class="num">{{ $money($detail->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>{{ __('quotations.detail.subtotal') }}</td><td class="num">{{ $money($subtotal) }}</td></tr>
        @if ($voucher)
            <tr class="discount"><td>{{ __('quotations.detail.discount', ['code' => $voucher->voucher_id, 'value' => $voucher->displayValue()]) }}</td><td class="num">−{{ $money($discount) }}</td></tr>
        @endif
        <tr class="grand"><td>{{ __('quotations.detail.grand_total') }}</td><td class="num">{{ $money($total) }}</td></tr>
    </table>

    @if (filled($quotation->notes))
        <div class="notes"><div class="label">{{ __('quotations.detail.notes') }}</div>{!! nl2br(e($quotation->notes)) !!}</div>
    @endif

    <p class="foot">{{ __('quotations.pdf.footer', ['date' => $quotation->expiry_date?->format('d/m/Y')]) }}</p>

    <table class="sign">
        <tr>
            <td><strong>{{ __('quotations.pdf.customer_sign') }}</strong></td>
            <td><strong>{{ __('quotations.pdf.consultant_sign') }}</strong><br><br><br><br>{{ $quotation->employee?->full_name }}</td>
        </tr>
    </table>
</body>
</html>
