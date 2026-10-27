<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// HĐ23: kết thúc cơ hội ở Data đã xử lý quá hạn quyết định (14 ngày) mà khách chưa đồng ý, mỗi ngày lúc 00:10
Schedule::command('opportunities:expire-undecided')->dailyAt('00:10');
