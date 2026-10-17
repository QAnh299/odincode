<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * HĐ34: email thông tin hóa đơn gửi khách hàng của cơ hội sau mỗi lần thanh toán thành công.
 * Luôn viết bằng tiếng Việt (khách hàng của trung tâm), không theo ngôn ngữ của nhân viên đang thao tác.
 */
class InvoicePaidMail extends Mailable
{
    use Queueable, SerializesModels;

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
}
