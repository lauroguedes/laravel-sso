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
