<?php

use App\Livewire\Courses\Index as CourseIndex;
use App\Livewire\Courses\Show as CourseShow;
use App\Livewire\Invoices\Index as InvoiceIndex;
use App\Livewire\Opportunities\Kanban as OpportunityKanban;
use App\Livewire\Opportunities\Show as OpportunityShow;
use App\Livewire\Quotations\Index as QuotationIndex;
use App\Livewire\Quotations\Show as QuotationShow;
use App\Livewire\Salesperson\Home;
use App\Livewire\Vouchers\Index as VoucherIndex;
use App\Livewire\Vouchers\Show as VoucherShow;
use Illuminate\Support\Facades\Route;

// Salesperson – prefix /salesperson, name salesperson.*

Route::get('/', Home::class)->name('home');

// Quản lý Opportunity – Kanban theo Stage (thay cho Quản lý Lead)
Route::get('/opportunities', OpportunityKanban::class)->name('opportunities');
Route::get('/opportunities/{opportunity}', OpportunityShow::class)->name('opportunities.show');

// Báo giá – danh sách + tra cứu, chi tiết (Giám đốc, Sale Admin, Kế toán, Sale Leader, Salesperson; Sale Leader xem của đội, Salesperson xem của mình)
Route::get('/quotations', QuotationIndex::class)->name('quotations');
Route::get('/quotations/{quotation}', QuotationShow::class)->name('quotations.show');

// Hóa đơn – danh sách + tra cứu (Giám đốc, Sale Admin, Kế toán, Sale Leader, Salesperson; Sale Leader xem của đội, Salesperson xem của mình)
Route::get('/invoices', InvoiceIndex::class)->name('invoices');

// Voucher (dùng chung với các vai trò khác, trừ Kế toán)
Route::get('/vouchers', VoucherIndex::class)->name('vouchers');
Route::get('/vouchers/{voucher}', VoucherShow::class)->name('vouchers.show');

// Khóa học – chỉ xem (dùng chung với các vai trò khác, trừ Kế toán)
Route::get('/courses', CourseIndex::class)->name('courses');
Route::get('/courses/{course}', CourseShow::class)->name('courses.show');
