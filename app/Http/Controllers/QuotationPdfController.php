<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Services\QuotationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * HĐ7 – HĐ8: Salesperson xuất PDF báo giá để tự gửi khách qua kênh khác (Zalo, email cá nhân, in giấy).
 * Chỉ người lập, báo giá còn Nháp và chưa hết hạn; trạng thái vẫn là Nháp (hệ thống không biết đã gửi hay chưa).
 */
class QuotationPdfController extends Controller
{
    public function __invoke(Quotation $quotation, QuotationService $service): Response
    {
        abort_unless($service->canEdit($quotation, Auth::user()->employee), 403);

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
