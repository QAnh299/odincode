<?php

use App\Livewire\Accountant\Home;
use App\Livewire\Quotations\Index as QuotationIndex;
use App\Livewire\Quotations\Show as QuotationShow;
use Illuminate\Support\Facades\Route;

// Kế toán – prefix /accountant, name accountant.*

Route::get('/', Home::class)->name('home');

// Báo giá – danh sách + tra cứu, chi tiết (chỉ xem; kế toán xem tất cả báo giá)
Route::get('/quotations', QuotationIndex::class)->name('quotations');
Route::get('/quotations/{quotation}', QuotationShow::class)->name('quotations.show');
