<?php

use App\Livewire\Courses\Index as CourseIndex;
use App\Livewire\Courses\Show as CourseShow;
use App\Livewire\SaleLeader\Home;
use App\Livewire\Vouchers\Index as VoucherIndex;
use App\Livewire\Vouchers\Show as VoucherShow;
use Illuminate\Support\Facades\Route;

// Sale Leader – prefix /sale-leader, name sale_leader.*

Route::get('/', Home::class)->name('home');

// Voucher (dùng chung với các vai trò khác, trừ Kế toán)
Route::get('/vouchers', VoucherIndex::class)->name('vouchers');
Route::get('/vouchers/{voucher}', VoucherShow::class)->name('vouchers.show');

// Khóa học – chỉ xem (dùng chung với các vai trò khác, trừ Kế toán)
Route::get('/courses', CourseIndex::class)->name('courses');
Route::get('/courses/{course}', CourseShow::class)->name('courses.show');
