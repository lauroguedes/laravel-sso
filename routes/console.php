<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The audit trail grows with every action and never shrinks on its own.
 * Retention is set by SSO_AUDIT_RETENTION_DAYS in "config/activitylog.php".
 */
Schedule::command('activitylog:clean')->daily();

/*
 * A public demonstration is rebuilt on a cycle, so that what a visitor finds
 * is the demo rather than whatever the last visitor left behind. Scheduled
 * only when this installation says it is one; the command refuses anyway.
 */
if (config('sso.demo.enabled')) {
    Schedule::command('sso:demo-reset', ['--force'])
        ->cron('0 */'.max(1, min(23, (int) config('sso.demo.reset_hours'))).' * * *')
        ->withoutOverlapping()
        ->runInBackground();
}
