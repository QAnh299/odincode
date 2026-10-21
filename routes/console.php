<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// HĐ15: tự từ chối báo giá Nháp quá hạn, mỗi ngày lúc 00:05 (cần chạy `php artisan schedule:work` hoặc cron / Task Scheduler)
Schedule::command('quotations:expire')->dailyAt('00:05');

// HĐ23: kết thúc cơ hội ở Data đã xử lý quá hạn quyết định (14 ngày) mà khách chưa đồng ý, mỗi ngày lúc 00:10
Schedule::command('opportunities:expire-undecided')->dailyAt('00:10');
