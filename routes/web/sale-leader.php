<?php

use App\Livewire\SaleLeader\Home;
use Illuminate\Support\Facades\Route;

// Sale Leader – prefix /sale-leader, name sale_leader.*

Route::get('/', Home::class)->name('home');
