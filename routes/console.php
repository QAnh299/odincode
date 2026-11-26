<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('opportunities:expire-undecided')->dailyAt('00:10');

Schedule::command('invoices:mark-overdue')->dailyAt('00:15');

Schedule::command('backup:clean')->dailyAt('01:00')->appendOutputTo(storage_path('logs/backup.log'));
Schedule::command('backup:run')->dailyAt('01:30')->appendOutputTo(storage_path('logs/backup.log'));
Schedule::command('backup:monitor')->dailyAt('02:00')->appendOutputTo(storage_path('logs/backup.log'));
