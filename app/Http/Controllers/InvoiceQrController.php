<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class InvoiceQrController extends Controller
{
    public function __invoke(Invoice $invoice, InvoiceService $service): Response
    {
        abort_unless($service->canDownloadQr($invoice, Auth::user()->employee), 403);

        $response = Http::timeout(10)->get($invoice->qrUrl());
        $type = (string) $response->header('Content-Type');

        if (! $response->successful() || ! str_starts_with($type, 'image/')) {
            Log::channel('payments')->warning('Không tải được ảnh QR hóa đơn', ['invoice' => $invoice->invoice_id, 'status' => $response->status()]);
            abort(502);
        }

        return response($response->body(), 200, [
            'Content-Type'  => $type,
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
