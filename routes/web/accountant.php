<?php

use App\Livewire\Accountant\Home;
use Illuminate\Support\Facades\Route;

// Kế toán – prefix /accountant, name accountant.*

Route::get('/', Home::class)->name('home');
