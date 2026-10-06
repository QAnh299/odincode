<?php

use App\Livewire\Director\Home;
use Illuminate\Support\Facades\Route;

// Giám đốc – prefix /director, name director.*

Route::get('/', Home::class)->name('home');
