<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Kết thúc cơ hội quá hạn, mỗi ngày lúc 00:10: Data đã có lịch hẹn quá 7 ngày từ buổi khách không đến mà chưa hẹn lại;
// Data đã xử lý quá hạn quyết định (14 ngày) mà khách chưa đồng ý
Schedule::command('opportunities:expire-undecided')->dailyAt('00:10');
