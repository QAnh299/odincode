<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        if ($data['transferType'] !== 'in') {
            return response()->json(['success' => true, 'matched' => false, 'invoice' => null]);
        }

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
