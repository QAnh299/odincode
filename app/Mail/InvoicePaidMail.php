<?php

namespace App\Mail;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePaidMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Invoice $invoice)
    {
        $this->locale('vi');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('invoices.mail.subject', ['code' => $this->invoice->invoice_id], 'vi'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invoice-paid');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf(), 'hoa-don-'.$this->invoice->invoice_id.'.pdf')->withMime('application/pdf'),
        ];
    }

    public function pdf(): string
    {
        $this->invoice->loadMissing([
            'order.quotation.details' => fn ($q) => $q->orderBy('course_id'),
            'order.quotation.details.course',
            'order.quotation.voucher',
            'order.quotation.employee',
            'order.quotation.opportunity.lead.branch',
            'accountant',
        ]);

        $locale = app()->getLocale();
        app()->setLocale('vi');
        try {
            return Pdf::loadView('pdf.invoice', ['invoice' => $this->invoice])
                ->setPaper('a4')
                ->setOption('isFontSubsettingEnabled', true)
                ->output();
        } finally {
            app()->setLocale($locale);
        }
    }
}
