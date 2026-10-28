<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * HĐ29: SePay gọi vào khi tài khoản nhận của trung tâm có biến động số dư (webhook SePay, https://docs.sepay.vn/tich-hop-webhooks.html).
 * Xác thực kiểu API Key: header "Authorization: Apikey <BANK_WEBHOOK_SECRET>" (chưa cấu hình thì tắt, trả 503).
 * Body JSON của SePay: id, transferType (in/out), transferAmount, content, code, referenceCode, transactionDate...
 * Chỉ xử lý tiền vào; nội dung chứa mã hóa đơn thì đối chiếu như HĐ29 (khớp → tự ghi nhận, lệch → cảnh báo).
 * Luôn trả 200 + {"success": true} khi đã nhận (khớp hay không) để SePay không gửi lại; giao dịch không khớp ghi log 'payments'.
 * SePay có thể gửi lại cùng một giao dịch: hóa đơn đã Đã thanh toán thì lần gửi sau chỉ ghi log, không ghi nhận lại.
 */
class BankWebhookController extends Controller
{
    public function __invoke(Request $request, InvoiceService $service): JsonResponse
    {
        $secret = (string) config('services.vietqr.webhook_secret');

        abort_if($secret === '', 503, 'Webhook chưa được cấu hình');
        abort_unless(hash_equals('Apikey '.$secret, trim((string) $request->header('Authorization'))), 401);

        $data = $request->validate([
            'id'              => ['required'],
            'transferType'    => ['required', 'string'],
            'transferAmount'  => ['required', 'numeric', 'min:0'],
            'content'         => ['nullable', 'string', 'max:1000'],
            'code'            => ['nullable', 'string', 'max:100'],
            'referenceCode'   => ['nullable', 'string', 'max:100'],
            'transactionDate' => ['nullable', 'date'],
        ]);

        // Tiền ra khỏi tài khoản không liên quan đến hóa đơn
        if ($data['transferType'] !== 'in') {
            return response()->json(['success' => true, 'matched' => false, 'invoice' => null]);
        }

        // Mã thanh toán SePay nhận diện được (nếu có cấu hình) + nội dung chuyển khoản: tìm mã hóa đơn INV...
        $content = trim(($data['code'] ?? '').' '.($data['content'] ?? ''));

        $invoice = $service->handleBankTransfer(
            (float) $data['transferAmount'],
            $content,
            'SePay#'.$data['id'].(filled($data['referenceCode'] ?? null) ? ' / '.$data['referenceCode'] : ''),
            filled($data['transactionDate'] ?? null) ? Carbon::parse($data['transactionDate'], config('app.timezone')) : null,
        );

        return response()->json([
            'success' => true,
            'matched' => $invoice !== null,
            'invoice' => $invoice?->invoice_id,
        ]);
    }
}
