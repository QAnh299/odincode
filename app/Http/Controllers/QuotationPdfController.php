<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Services\QuotationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class QuotationPdfController extends Controller
{
    public function __invoke(Quotation $quotation, QuotationService $service): Response
    {
        abort_unless($service->canExportPdf($quotation, Auth::user()->employee), 403);

        $quotation->load([
            'details' => fn ($q) => $q->orderBy('course_id'),
            'details.course',
            'voucher',
            'employee',
            'opportunity.lead.branch',
        ])->loadSum('details as details_subtotal', 'line_total');

        return Pdf::loadView('pdf.quotation', ['quotation' => $quotation])
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true)
            ->download('bao-gia-'.$quotation->quotation_id.'.pdf');
    }
}
