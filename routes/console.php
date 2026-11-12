<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Kết thúc cơ hội quá hạn, mỗi ngày lúc 00:10: Data đã có lịch hẹn quá 7 ngày từ buổi khách không đến mà chưa hẹn lại;
// Data đã xử lý quá hạn quyết định (14 ngày kể từ khi chuyển sang stage) mà khách chưa đồng ý
Schedule::command('opportunities:expire-undecided')->dailyAt('00:10');

// Hóa đơn Chờ thanh toán đã qua hạn → Quá hạn, mỗi ngày lúc 00:15 (Salesperson gia hạn để mở lại)
Schedule::command('invoices:mark-overdue')->dailyAt('00:15');

// NFR06 – Sao lưu tự động (spatie/laravel-backup), RPO ≤ 24 giờ:
// 01:00 xoá bản cũ theo chính sách giữ lại, 01:30 sao lưu CSDL + file tải lên, 02:00 kiểm tra bản mới nhất không quá 1 ngày.
// Bản sao lưu nằm ở storage/app/private/odin-backup/*.zip; nhật ký ở storage/logs/backup.log
Schedule::command('backup:clean')->dailyAt('01:00')->appendOutputTo(storage_path('logs/backup.log'));
Schedule::command('backup:run')->dailyAt('01:30')->appendOutputTo(storage_path('logs/backup.log'));
Schedule::command('backup:monitor')->dailyAt('02:00')->appendOutputTo(storage_path('logs/backup.log'));
