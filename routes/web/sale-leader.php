<?php

use App\Livewire\Courses\Index as CourseIndex;
use App\Livewire\Courses\Show as CourseShow;
use App\Livewire\Employees\Index as EmployeeIndex;
use App\Livewire\Employees\Show as EmployeeShow;
use App\Livewire\Invoices\Index as InvoiceIndex;
use App\Livewire\Leads\Index as LeadIndex;
use App\Livewire\Opportunities\Kanban as OpportunityKanban;
use App\Livewire\Opportunities\Show as OpportunityShow;
use App\Livewire\Orders\Index as OrderIndex;
use App\Livewire\Quotations\Index as QuotationIndex;
use App\Livewire\Quotations\Show as QuotationShow;
use App\Livewire\Reports\LeadConversion as LeadConversionReport;
use App\Livewire\Reports\SalesPerformance as SalesPerformanceReport;
use App\Livewire\SaleLeader\Home;
use App\Livewire\Students\Index as StudentIndex;
use App\Livewire\Students\Show as StudentShow;
use App\Livewire\Vouchers\Index as VoucherIndex;
use App\Livewire\Vouchers\Show as VoucherShow;
use Illuminate\Support\Facades\Route;

// Sale Leader - prefix /sale-leader, name sale_leader.*

Route::get('/', Home::class)->name('home');

// Quản lý Lead (dùng chung Giám đốc, Sale Admin, Sale Leader)
Route::get('/leads', LeadIndex::class)->name('leads');

// Quản lý cơ hội - Kanban theo Stage + chi tiết (Sale Leader: đội mình, Giám đốc: tất cả; chỉ xem,
// Sale Leader được chuyển người phụ trách)
Route::get('/opportunities', OpportunityKanban::class)->name('opportunities');
Route::get('/opportunities/{opportunity}', OpportunityShow::class)->name('opportunities.show');

// Quản lý nhân viên - xem danh sách, chi tiết (Sale Leader chỉ thấy nhân viên trong đội của mình)
Route::get('/employees', EmployeeIndex::class)->name('employees');
Route::get('/employees/{employee}', EmployeeShow::class)->name('employees.show');

// Báo giá - danh sách + tra cứu, chi tiết (Giám đốc, Sale Admin, Kế toán, Sale Leader, Salesperson; Sale Leader xem của đội, Salesperson xem của mình)
Route::get('/quotations', QuotationIndex::class)->name('quotations');
Route::get('/quotations/{quotation}', QuotationShow::class)->name('quotations.show');

// Đơn hàng - danh sách + tra cứu (Giám đốc, Sale Admin, Kế toán, Sale Leader, Salesperson; Sale Leader xem của đội, Salesperson xem của mình)
Route::get('/orders', OrderIndex::class)->name('orders');

// Hóa đơn - danh sách + tra cứu (Giám đốc, Sale Admin, Kế toán, Sale Leader, Salesperson; Sale Leader xem của đội, Salesperson xem của mình)
Route::get('/invoices', InvoiceIndex::class)->name('invoices');

// Voucher (dùng chung với các vai trò khác, trừ Kế toán)
Route::get('/vouchers', VoucherIndex::class)->name('vouchers');
Route::get('/vouchers/{voucher}', VoucherShow::class)->name('vouchers.show');

// Khóa học - chỉ xem (dùng chung với các vai trò khác, trừ Kế toán)
Route::get('/courses', CourseIndex::class)->name('courses');
Route::get('/courses/{course}', CourseShow::class)->name('courses.show');

// Báo cáo - Phân tích hiệu quả nhân viên Sales (Giám đốc, Sale Admin: tất cả đội; Sale Leader: đội mình)
Route::get('/reports/sales-performance', SalesPerformanceReport::class)->name('reports.sales-performance');

// Báo cáo - Phân tích hiệu quả chuyển đổi Lead (Giám đốc, Sale Admin: toàn bộ Lead; Sale Leader: Lead của đội; Salesperson: Lead mình phụ trách)
Route::get('/reports/lead-conversion', LeadConversionReport::class)->name('reports.lead-conversion');

// Học viên - danh sách + tra cứu, chi tiết (mọi vai trò; Sale Leader xem của đội, Salesperson xem của mình;
// Sale Leader, Salesperson được chỉnh sửa)
Route::get('/students', StudentIndex::class)->name('students');
Route::get('/students/{student}', StudentShow::class)->name('students.show');
