<?php

use App\Console\Commands\GenerateRecurringAppointments;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the recurring appointments generation command to run daily
Schedule::command(GenerateRecurringAppointments::class, ['--days=30'])
    ->daily()
    ->at('00:30') // Run at 12:30 AM daily
    ->description('Generate recurring appointment instances for the next 30 days');

// Schedule the due reminders processing command to run every 5 minutes
Schedule::command('app:process-due-reminders', ['--limit=50'])
    ->everyFiveMinutes()
    ->description('Process due appointment reminders and send notifications');
