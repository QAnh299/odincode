<?php

namespace App\Providers;

use App\Http\Middleware\CheckRole;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Livewire::addPersistentMiddleware([CheckRole::class]);
    }
}
