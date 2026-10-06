<?php

use App\Livewire\Salesperson\Home;
use Illuminate\Support\Facades\Route;

// Salesperson – prefix /salesperson, name salesperson.*

Route::get('/', Home::class)->name('home');
