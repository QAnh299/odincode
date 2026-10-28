@php
    // HĐ34: email hóa đơn đã thanh toán gửi khách (giống mẫu emails/temporary-password)
    $order = $invoice->order;
    $lead = $order?->quotation?->opportunity?->lead;
    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
@endphp
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>{{ __('invoices.mail.subject', ['code' => $invoice->invoice_id]) }}</title>
</head>

<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:28px;">
        <h2 style="margin:0 0 16px;color:#0f5132;">ODIN Language Center</h2>

        <p>{{ __('invoices.mail.greeting', ['name' => $lead?->full_name ?? '']) }}</p>
        <p>{{ __('invoices.mail.intro') }}</p>

        <table style="margin:16px 0;border-collapse:collapse;width:100%;">
            @foreach ([
                __('invoices.code')           => $invoice->invoice_id,
                __('invoices.order')          => $order?->order_id,
                __('invoices.mail.installment') => __('invoices.installment', ['number' => $invoice->payment_installment]),
                __('invoices.method_label')   => __('invoices.method.'.$invoice->payment_method),
                __('invoices.mail.paid_at')   => $invoice->payment_date?->format('d/m/Y H:i'),
            ] as $label => $value)
                <tr>
                    <td style="padding:6px 16px 6px 0;color:#6b7280;">{{ $label }}</td>
                    <td style="padding:6px 0;">{{ $value }}</td>
                </tr>
            @endforeach
            <tr>
                <td style="padding:10px 16px 6px 0;color:#6b7280;border-top:1px solid #e5e7eb;">{{ __('invoices.amount') }}</td>
                <td style="padding:10px 0 6px;border-top:1px solid #e5e7eb;font-size:18px;font-weight:bold;color:#0f5132;">{{ $money($invoice->payment_amount) }}</td>
            </tr>
        </table>

        @if ($order)
            <p style="margin:0 0 4px;">{{ __('invoices.mail.order_summary', ['paid' => $money($order->paid_amount), 'total' => $money($order->total_amount)]) }}</p>
            <p style="margin:0;">
                {{ (float) $order->remaining_amount > 0
                    ? __('invoices.mail.remaining', ['amount' => $money($order->remaining_amount)])
                    : __('invoices.mail.completed') }}
            </p>
        @endif

        <p style="padding:10px 12px;background:#f3f6f2;border-radius:8px;">📎 {{ __('invoices.pdf.attachment') }}</p>
        <p style="margin-top:20px;color:#6b7280;font-size:13px;">{{ __('invoices.mail.contact', ['name' => $invoice->createdBy?->full_name ?? '']) }}</p>
    </div>
</body>

</html>
