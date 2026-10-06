<?php

use App\Livewire\SaleAdmin\Home;
use Illuminate\Support\Facades\Route;

// Sale Admin – prefix /sale-admin, name sale_admin.*

Route::get('/', Home::class)->name('home');
