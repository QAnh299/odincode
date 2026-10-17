<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// HĐ15: tự từ chối báo giá Nháp quá hạn, mỗi ngày lúc 00:05 (cần chạy `php artisan schedule:work` hoặc cron / Task Scheduler)
Schedule::command('quotations:expire')->dailyAt('00:05');
