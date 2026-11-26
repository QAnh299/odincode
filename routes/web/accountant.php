<?php

use App\Livewire\Invoices\Index as InvoiceIndex;
use App\Livewire\Invoices\Show as InvoiceShow;
use App\Livewire\Orders\Index as OrderIndex;
use App\Livewire\Orders\Show as OrderShow;
use App\Livewire\Quotations\Index as QuotationIndex;
use App\Livewire\Quotations\Show as QuotationShow;
use App\Livewire\Reports\Revenue as RevenueReport;
use App\Livewire\Students\Index as StudentIndex;
use App\Livewire\Students\Show as StudentShow;
use Illuminate\Support\Facades\Route;

Route::get('/quotations', QuotationIndex::class)->name('quotations');
Route::get('/quotations/{quotation}', QuotationShow::class)->name('quotations.show');

Route::get('/orders', OrderIndex::class)->name('orders');
Route::get('/orders/{order}', OrderShow::class)->name('orders.show');

Route::get('/invoices', InvoiceIndex::class)->name('invoices');
Route::get('/invoices/{invoice}', InvoiceShow::class)->name('invoices.show');

Route::get('/reports/revenue', RevenueReport::class)->name('reports.revenue');

Route::get('/students', StudentIndex::class)->name('students');
Route::get('/students/{student}', StudentShow::class)->name('students.show');
