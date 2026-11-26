<?php

use App\Livewire\Appointments\Index as AppointmentIndex;
use App\Http\Controllers\InvoiceQrController;
use App\Http\Controllers\QuotationPdfController;
use App\Livewire\Courses\Index as CourseIndex;
use App\Livewire\Courses\Show as CourseShow;
use App\Livewire\Invoices\Form as InvoiceForm;
use App\Livewire\Invoices\Index as InvoiceIndex;
use App\Livewire\Invoices\Show as InvoiceShow;
use App\Livewire\Opportunities\Create as OpportunityCreate;
use App\Livewire\Opportunities\Kanban as OpportunityKanban;
use App\Livewire\Opportunities\Show as OpportunityShow;
use App\Livewire\Orders\Index as OrderIndex;
use App\Livewire\Orders\Show as OrderShow;
use App\Livewire\Quotations\Form as QuotationForm;
use App\Livewire\Quotations\Index as QuotationIndex;
use App\Livewire\Quotations\Show as QuotationShow;
use App\Livewire\Reports\LeadConversion as LeadConversionReport;
use App\Livewire\Reports\Revenue as RevenueReport;
use App\Livewire\Students\Index as StudentIndex;
use App\Livewire\Students\Show as StudentShow;
use App\Livewire\Vouchers\Index as VoucherIndex;
use App\Livewire\Vouchers\Show as VoucherShow;
use Illuminate\Support\Facades\Route;

Route::get('/opportunities', OpportunityKanban::class)->name('opportunities');
Route::get('/opportunities/create', OpportunityCreate::class)->name('opportunities.create');
Route::get('/opportunities/{opportunity}', OpportunityShow::class)->name('opportunities.show');

Route::get('/appointments', AppointmentIndex::class)->name('appointments');

Route::get('/quotations', QuotationIndex::class)->name('quotations');

Route::get('/quotations/create/{opportunity}', QuotationForm::class)->name('quotations.create');
Route::get('/quotations/{quotation}/edit', QuotationForm::class)->name('quotations.edit');
Route::get('/quotations/{quotation}/pdf', QuotationPdfController::class)->name('quotations.pdf');
Route::get('/quotations/{quotation}', QuotationShow::class)->name('quotations.show');

Route::get('/orders', OrderIndex::class)->name('orders');
Route::get('/orders/{order}', OrderShow::class)->name('orders.show');

Route::get('/invoices', InvoiceIndex::class)->name('invoices');

Route::get('/invoices/create/{order}', InvoiceForm::class)->name('invoices.create');
Route::get('/invoices/{invoice}/edit', InvoiceForm::class)->name('invoices.edit');

Route::get('/invoices/{invoice}/qr', InvoiceQrController::class)->name('invoices.qr');
Route::get('/invoices/{invoice}', InvoiceShow::class)->name('invoices.show');

Route::get('/vouchers', VoucherIndex::class)->name('vouchers');
Route::get('/vouchers/{voucher}', VoucherShow::class)->name('vouchers.show');

Route::get('/courses', CourseIndex::class)->name('courses');
Route::get('/courses/{course}', CourseShow::class)->name('courses.show');

Route::get('/reports/lead-conversion', LeadConversionReport::class)->name('reports.lead-conversion');

Route::get('/reports/revenue', RevenueReport::class)->name('reports.revenue');

Route::get('/students', StudentIndex::class)->name('students');
Route::get('/students/{student}', StudentShow::class)->name('students.show');
