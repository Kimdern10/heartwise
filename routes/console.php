<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled jobs
|--------------------------------------------------------------------------
|
| On the live server add ONE cron job (cPanel → Cron Jobs, every minute):
|   * * * * * cd /home/USER/heartwise && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Monday afternoon (US Eastern): "new people near you" email.
Schedule::command('app:send-weekly-digest')->weeklyOn(1, '15:00')->timezone('America/New_York')->withoutOverlapping();

// Date check-ins: alert the trusted friend if a member hasn't checked in after their date.
Schedule::command('app:check-date-safety')->everyFiveMinutes()->withoutOverlapping();

// Queued emails (likes, matches, messages): send them every minute. Shared hosting has no
// always-on worker, so the same cron job runs a short worker that stops when the queue is empty.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();
