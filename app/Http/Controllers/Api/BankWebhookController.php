<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * HĐ29: ngân hàng / cổng QR (SePay, Casso...) gọi vào khi có giao dịch chuyển khoản tới tài khoản nhận.
 * Xác thực bằng header X-Webhook-Secret = BANK_WEBHOOK_SECRET (chưa cấu hình thì tắt, trả 503).
 * Body JSON: amount (số tiền), content (nội dung chuyển khoản, chứa mã hóa đơn), reference (mã giao dịch), paid_at (tùy chọn).
 * Luôn trả 200 khi đã nhận (khớp hay không) để cổng không gửi lại; giao dịch không khớp được ghi log 'payments'.
 */
class BankWebhookController extends Controller
{
    public function __invoke(Request $request, InvoiceService $service): JsonResponse
    {
        $secret = (string) config('services.vietqr.webhook_secret');

        abort_if($secret === '', 503, 'Webhook chưa được cấu hình');
        abort_unless(hash_equals($secret, (string) $request->header('X-Webhook-Secret')), 401);

        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:0'],
            'content'   => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at'   => ['nullable', 'date'],
        ]);

        $invoice = $service->handleBankTransfer(
            (float) $data['amount'],
            $data['content'],
            $data['reference'] ?? null,
            isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : null,
        );

        return response()->json([
            'matched' => $invoice !== null,
            'invoice' => $invoice?->invoice_id,
        ]);
    }
}
