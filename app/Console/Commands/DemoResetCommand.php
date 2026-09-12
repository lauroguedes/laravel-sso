<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DemoMode;
use App\Services\Settings;
use Database\Seeders\SsoDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Puts a public demo back the way it started.
 *
 * A demonstration server is signed into by strangers, and everything they can
 * reach they can also change: rename the applications, revoke the tokens,
 * disable the accounts. Rebuilding on a schedule is what keeps it a
 * demonstration rather than whatever the last visitor left behind.
 *
 * Guarded twice. It drops every table, so it refuses to run unless a
 * deployment has explicitly said this installation is a demo, and it refuses
 * outright in production — the seeder it runs creates accounts with a
 * published password.
 */
class DemoResetCommand extends Command
{
    protected $signature = 'sso:demo-reset {--force : Reset without asking, for a scheduled run}';

    protected $description = 'Drop everything and rebuild the demonstration data';

    public function handle(): int
    {
        if (! app(DemoMode::class)->enabled()) {
            $this->components->error('This installation is not a demo. Set SSO_DEMO_MODE=true to allow it.');

            return self::FAILURE;
        }

        /*
         * The seeder makes the same check, but reaching it would mean the
         * tables had already been dropped. Failing first leaves the data
         * alone.
         */
        if (app()->isProduction()) {
            $this->components->error('APP_ENV is production. The demo seeder publishes its passwords and will not run here.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This deletes every user, application and token. Continue?')) {
            return self::SUCCESS;
        }

        $this->components->task('Rebuilding the database', function (): void {
            Artisan::call('migrate:fresh', ['--force' => true], $this->output);
        });

        $this->components->task('Seeding the demonstration', function (): void {
            Artisan::call('db:seed', [
                '--class' => SsoDemoSeeder::class,
                '--force' => true,
            ], $this->output);
        });

        /*
         * The settings are read through a cache that outlives the table they
         * came from: on redis or a file store, dropping the rows leaves the
         * server still wearing whatever the last visitor set.
         */
        $this->components->task('Forgetting the cached settings', function (): void {
            app(Settings::class)->flush();
        });

        $this->newLine();
        $this->components->info('The demonstration is back to its starting state.');

        return self::SUCCESS;
    }
}
