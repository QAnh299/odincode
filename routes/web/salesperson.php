<?php

use App\Livewire\Appointments\Index as AppointmentIndex;
use App\Http\Controllers\QuotationPdfController;
use App\Livewire\Courses\Index as CourseIndex;
use App\Livewire\Courses\Show as CourseShow;
use App\Livewire\Invoices\Form as InvoiceForm;
use App\Livewire\Invoices\Index as InvoiceIndex;
use App\Livewire\Invoices\Show as InvoiceShow;
use App\Livewire\Opportunities\Kanban as OpportunityKanban;
use App\Livewire\Opportunities\Show as OpportunityShow;
use App\Livewire\Orders\Index as OrderIndex;
use App\Livewire\Quotations\Form as QuotationForm;
use App\Livewire\Quotations\Index as QuotationIndex;
use App\Livewire\Quotations\Show as QuotationShow;
use App\Livewire\Reports\LeadConversion as LeadConversionReport;
use App\Livewire\Reports\Revenue as RevenueReport;
use App\Livewire\Salesperson\Home;
use App\Livewire\Students\Index as StudentIndex;
use App\Livewire\Students\Show as StudentShow;
use App\Livewire\Vouchers\Index as VoucherIndex;
use App\Livewire\Vouchers\Show as VoucherShow;
use Illuminate\Support\Facades\Route;

// Salesperson - prefix /salesperson, name salesperson.*

Route::get('/', Home::class)->name('home');

// Quản lý Opportunity - Kanban theo Stage (thay cho Quản lý Lead)
Route::get('/opportunities', OpportunityKanban::class)->name('opportunities');
Route::get('/opportunities/{opportunity}', OpportunityShow::class)->name('opportunities.show');

// Lịch hẹn - các lịch hẹn mình phụ trách (menu Quản lý Opportunity → Lịch hẹn)
Route::get('/appointments', AppointmentIndex::class)->name('appointments');

// Báo giá - danh sách + tra cứu, chi tiết (Giám đốc, Sale Admin, Kế toán, Sale Leader, Salesperson; Sale Leader xem của đội, Salesperson xem của mình)
Route::get('/quotations', QuotationIndex::class)->name('quotations');
// Lập báo giá từ cơ hội (HĐ1 – HĐ6), sửa báo giá Nháp (HĐ10), xuất PDF (HĐ7 – HĐ8) - chỉ Salesperson
Route::get('/quotations/create/{opportunity}', QuotationForm::class)->name('quotations.create');
Route::get('/quotations/{quotation}/edit', QuotationForm::class)->name('quotations.edit');
Route::get('/quotations/{quotation}/pdf', QuotationPdfController::class)->name('quotations.pdf');
Route::get('/quotations/{quotation}', QuotationShow::class)->name('quotations.show');

// Đơn hàng - danh sách + tra cứu (Giám đốc, Sale Admin, Kế toán, Sale Leader, Salesperson; Sale Leader xem của đội, Salesperson xem của mình)
Route::get('/orders', OrderIndex::class)->name('orders');

// Hóa đơn - danh sách + tra cứu (Giám đốc, Sale Admin, Kế toán, Sale Leader, Salesperson; Sale Leader xem của đội, Salesperson xem của mình)
Route::get('/invoices', InvoiceIndex::class)->name('invoices');
// Tạo hóa đơn từ đơn hàng (HĐ18 – HĐ26), sửa hóa đơn Chờ thanh toán (HĐ36) - chỉ Salesperson
Route::get('/invoices/create/{order}', InvoiceForm::class)->name('invoices.create');
Route::get('/invoices/{invoice}/edit', InvoiceForm::class)->name('invoices.edit');
Route::get('/invoices/{invoice}', InvoiceShow::class)->name('invoices.show');

// Voucher (dùng chung với các vai trò khác, trừ Kế toán)
Route::get('/vouchers', VoucherIndex::class)->name('vouchers');
Route::get('/vouchers/{voucher}', VoucherShow::class)->name('vouchers.show');

// Khóa học - chỉ xem (dùng chung với các vai trò khác, trừ Kế toán)
Route::get('/courses', CourseIndex::class)->name('courses');
Route::get('/courses/{course}', CourseShow::class)->name('courses.show');

// Báo cáo - Phân tích hiệu quả chuyển đổi Lead (Giám đốc, Sale Admin: toàn bộ Lead; Sale Leader: Lead của đội; Salesperson: Lead mình phụ trách)
Route::get('/reports/lead-conversion', LeadConversionReport::class)->name('reports.lead-conversion');

// Báo cáo - Phân tích doanh thu & thanh toán (Giám đốc, Sale Admin, Kế toán: toàn bộ; Sale Leader: đội mình; Salesperson: đơn mình phụ trách)
Route::get('/reports/revenue', RevenueReport::class)->name('reports.revenue');

// Học viên - danh sách + tra cứu, chi tiết (mọi vai trò; Sale Leader xem của đội, Salesperson xem của mình;
// Sale Leader, Salesperson được chỉnh sửa)
Route::get('/students', StudentIndex::class)->name('students');
Route::get('/students/{student}', StudentShow::class)->name('students.show');
