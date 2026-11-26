<?php

use App\Livewire\Courses\Index as CourseIndex;
use App\Livewire\Courses\Show as CourseShow;
use App\Livewire\Employees\Index as EmployeeIndex;
use App\Livewire\Employees\Show as EmployeeShow;
use App\Livewire\Invoices\Index as InvoiceIndex;
use App\Livewire\Invoices\Show as InvoiceShow;
use App\Livewire\Leads\Index as LeadIndex;
use App\Livewire\Leads\Show as LeadShow;
use App\Livewire\Opportunities\Kanban as OpportunityKanban;
use App\Livewire\Opportunities\Show as OpportunityShow;
use App\Livewire\Orders\Index as OrderIndex;
use App\Livewire\Orders\Show as OrderShow;
use App\Livewire\Quotations\Index as QuotationIndex;
use App\Livewire\Quotations\Show as QuotationShow;
use App\Livewire\Reports\LeadConversion as LeadConversionReport;
use App\Livewire\Reports\Revenue as RevenueReport;
use App\Livewire\Reports\SalesPerformance as SalesPerformanceReport;
use App\Livewire\Students\Index as StudentIndex;
use App\Livewire\Students\Show as StudentShow;
use App\Livewire\Vouchers\Index as VoucherIndex;
use App\Livewire\Vouchers\Show as VoucherShow;
use Illuminate\Support\Facades\Route;

Route::get('/leads', LeadIndex::class)->name('leads');
Route::get('/leads/{lead}', LeadShow::class)->name('leads.show');

Route::get('/opportunities', OpportunityKanban::class)->name('opportunities');
Route::get('/opportunities/{opportunity}', OpportunityShow::class)->name('opportunities.show');

Route::get('/employees', EmployeeIndex::class)->name('employees');
Route::get('/employees/{employee}', EmployeeShow::class)->name('employees.show');

Route::get('/quotations', QuotationIndex::class)->name('quotations');
Route::get('/quotations/{quotation}', QuotationShow::class)->name('quotations.show');

Route::get('/orders', OrderIndex::class)->name('orders');
Route::get('/orders/{order}', OrderShow::class)->name('orders.show');

Route::get('/invoices', InvoiceIndex::class)->name('invoices');
Route::get('/invoices/{invoice}', InvoiceShow::class)->name('invoices.show');

Route::get('/vouchers', VoucherIndex::class)->name('vouchers');
Route::get('/vouchers/{voucher}', VoucherShow::class)->name('vouchers.show');

Route::get('/courses', CourseIndex::class)->name('courses');
Route::get('/courses/{course}', CourseShow::class)->name('courses.show');

Route::get('/reports/sales-performance', SalesPerformanceReport::class)->name('reports.sales-performance');

Route::get('/reports/lead-conversion', LeadConversionReport::class)->name('reports.lead-conversion');

Route::get('/reports/revenue', RevenueReport::class)->name('reports.revenue');

Route::get('/students', StudentIndex::class)->name('students');
Route::get('/students/{student}', StudentShow::class)->name('students.show');
