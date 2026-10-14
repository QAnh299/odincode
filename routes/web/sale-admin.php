<?php

use App\Livewire\Courses\Index as CourseIndex;
use App\Livewire\Courses\Show as CourseShow;
use App\Livewire\Employees\Index as EmployeeIndex;
use App\Livewire\Employees\Show as EmployeeShow;
use App\Livewire\Invoices\Index as InvoiceIndex;
use App\Livewire\Leads\Index as LeadIndex;
use App\Livewire\Orders\Index as OrderIndex;
use App\Livewire\Quotations\Index as QuotationIndex;
use App\Livewire\Quotations\Show as QuotationShow;
use App\Livewire\Reports\LeadSources as LeadSourceReport;
use App\Livewire\Reports\SalesPerformance as SalesPerformanceReport;
use App\Livewire\SalesTeams\Index as SalesTeamIndex;
use App\Livewire\SaleAdmin\Home;
use App\Livewire\Students\Index as StudentIndex;
use App\Livewire\Students\Show as StudentShow;
use App\Livewire\Vouchers\Index as VoucherIndex;
use App\Livewire\Vouchers\Show as VoucherShow;
use Illuminate\Support\Facades\Route;

// Sale Admin – prefix /sale-admin, name sale_admin.*

Route::get('/', Home::class)->name('home');

// Quản lý Lead (dùng chung Giám đốc, Sale Admin, Sale Leader)
Route::get('/leads', LeadIndex::class)->name('leads');

// Quản lý nhân viên – xem danh sách, chi tiết (Sale Leader chỉ thấy nhân viên trong đội của mình)
Route::get('/employees', EmployeeIndex::class)->name('employees');
Route::get('/employees/{employee}', EmployeeShow::class)->name('employees.show');

// Đội kinh doanh – xem danh sách + tra cứu (Giám đốc, Sale Admin)
Route::get('/sales-teams', SalesTeamIndex::class)->name('sales-teams');

// Báo giá – danh sách + tra cứu, chi tiết (Giám đốc, Sale Admin, Kế toán, Sale Leader, Salesperson; Sale Leader xem của đội, Salesperson xem của mình)
Route::get('/quotations', QuotationIndex::class)->name('quotations');
Route::get('/quotations/{quotation}', QuotationShow::class)->name('quotations.show');

// Đơn hàng – danh sách + tra cứu (Giám đốc, Sale Admin, Kế toán, Sale Leader, Salesperson; Sale Leader xem của đội, Salesperson xem của mình)
Route::get('/orders', OrderIndex::class)->name('orders');

// Hóa đơn – danh sách + tra cứu (Giám đốc, Sale Admin, Kế toán, Sale Leader, Salesperson; Sale Leader xem của đội, Salesperson xem của mình)
Route::get('/invoices', InvoiceIndex::class)->name('invoices');

// Voucher (dùng chung với các vai trò khác, trừ Kế toán)
Route::get('/vouchers', VoucherIndex::class)->name('vouchers');
Route::get('/vouchers/{voucher}', VoucherShow::class)->name('vouchers.show');

// Khóa học – chỉ xem (dùng chung với các vai trò khác, trừ Kế toán)
Route::get('/courses', CourseIndex::class)->name('courses');
Route::get('/courses/{course}', CourseShow::class)->name('courses.show');

// Báo cáo – Phân tích chất lượng nguồn Lead (chỉ Giám đốc, Sale Admin)
Route::get('/reports/lead-sources', LeadSourceReport::class)->name('reports.lead-sources');

// Báo cáo – Phân tích hiệu quả nhân viên Sales (Giám đốc, Sale Admin: tất cả đội; Sale Leader: đội mình)
Route::get('/reports/sales-performance', SalesPerformanceReport::class)->name('reports.sales-performance');

// Học viên – danh sách + tra cứu, chi tiết (mọi vai trò; Sale Leader xem của đội, Salesperson xem của mình;
// Sale Leader, Salesperson được chỉnh sửa)
Route::get('/students', StudentIndex::class)->name('students');
Route::get('/students/{student}', StudentShow::class)->name('students.show');
