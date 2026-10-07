<?php

namespace App\Providers;

use App\Http\Middleware\CheckRole;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Cấu hình pagination sử dụng Bootstrap
        Paginator::useBootstrapFive();

        // Kiểm tra lại vai trò ở mọi request cập nhật của Livewire (lọc, phân trang, ...)
        Livewire::addPersistentMiddleware([CheckRole::class]);
    }
}
