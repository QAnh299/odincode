<?php

use App\Livewire\Auth\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ── Ngôn ngữ ─────────────────────────────────────────────────────────
Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, ['vi', 'en'])) {
        session(['locale' => $locale]);
    }

    return redirect()->back();
})->name('lang.switch');

// ── Root: chuyển tới trang home đúng vai trò ─────────────────────────
Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    $route = Auth::user()->homeRoute();
    abort_unless($route, 403, __('auth.no_role'));

    return redirect()->route($route);
})->name('home');

// ── Auth ──────────────────────────────────────────────────────────────
Route::get('/login', Login::class)->name('login')->middleware('guest');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout')->middleware('auth');

// ── Route theo vai trò (mỗi vai trò một file trong routes/web/) ──────
Route::middleware(['auth', 'role:director'])->prefix('director')->name('director.')
    ->group(base_path('routes/web/director.php'));

Route::middleware(['auth', 'role:sale_admin'])->prefix('sale-admin')->name('sale_admin.')
    ->group(base_path('routes/web/sale-admin.php'));

Route::middleware(['auth', 'role:sale_leader'])->prefix('sale-leader')->name('sale_leader.')
    ->group(base_path('routes/web/sale-leader.php'));

Route::middleware(['auth', 'role:salesperson'])->prefix('salesperson')->name('salesperson.')
    ->group(base_path('routes/web/salesperson.php'));

Route::middleware(['auth', 'role:accountant'])->prefix('accountant')->name('accountant.')
    ->group(base_path('routes/web/accountant.php'));
