<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PlatformRole;
use App\Models\User;
use App\Services\ScopeRegistry;
use Database\Seeders\PlatformPermissionsSeeder;
use Illuminate\Console\Command;
use Laravel\Passport\Passport;

/**
 * Prepares a fresh checkout to run as an Identity Provider.
 *
 * Every step is safe to repeat: nothing here overwrites an application key,
 * a signing key or an existing administrator. Running it again on a live
 * server is a way to check the installation, not a way to reset it.
 */
class InstallCommand extends Command
{
    /** @var string */
    protected $signature = 'sso:install {--skip-migrations : Leave the database alone}';

    /** @var string */
    protected $description = 'Prepare this server to issue OpenID Connect identities';

    /**
     * Execute the console command.
     */
    public function handle(PlatformPermissionsSeeder $permissions): int
    {
        $this->components->info('Installing the Identity Provider.');

        if (! $this->ensureApplicationKey()) {
            return self::FAILURE;
        }

        if (! $this->option('skip-migrations') && ! $this->migrate()) {
            return self::FAILURE;
        }

        $this->ensureSigningKeys();

        $permissions->run();
        $this->components->twoColumnDetail('Platform roles and permissions', '<fg=green;options=bold>SEEDED</>');

        $this->ensureAdministrator();

        $this->newLine();
        $this->reportConfiguration();
        $this->reportWarnings();

        return self::SUCCESS;
    }

    /**
     * Make sure the application has an encryption key.
     *
     * Without one, sessions and the encrypted two-factor columns cannot be
     * read, so this is the one step whose failure stops the install.
     */
    private function ensureApplicationKey(): bool
    {
        if (filled(config('app.key'))) {
            $this->components->twoColumnDetail('Application key', '<fg=green;options=bold>PRESENT</>');

            return true;
        }

        if (! file_exists(base_path('.env'))) {
            $this->components->error('No .env file. Copy .env.example to .env and run this again.');

            return false;
        }

        if ($this->callSilently('key:generate', ['--force' => true]) !== self::SUCCESS) {
            $this->components->error('Could not write an application key. Check that .env is writable.');

            return false;
        }

        $this->components->twoColumnDetail('Application key', '<fg=green;options=bold>GENERATED</>');

        return true;
    }

    /**
     * Bring the schema up to date.
     */
    private function migrate(): bool
    {
        /*
         * --force only matters in production, where the migrate command asks
         * for confirmation; everywhere else its own guard lets it through.
         */
        $arguments = $this->laravel->isProduction() ? ['--force' => true] : [];

        $exitCode = $this->call('migrate', $arguments);

        if ($exitCode !== self::SUCCESS) {
            $this->components->error('Migrations did not complete. Check the database connection and run "php artisan migrate".');

            return false;
        }

        return true;
    }

    /**
     * Create the OAuth2 signing keys, but never replace them.
     *
     * Replacing the private key invalidates every ID Token already issued and
     * every relying party's cached key set, so an existing pair is reported
     * and left exactly as it is.
     *
     * Passport refuses to overwrite either file, so its exit code is what
     * decides the result: reporting GENERATED on the strength of the guard
     * alone would announce success over a half-written key directory.
     */
    private function ensureSigningKeys(): void
    {
        if (file_exists(Passport::keyPath('oauth-private.key'))) {
            $this->components->twoColumnDetail('OAuth signing keys', '<fg=green;options=bold>PRESENT</>');

            return;
        }

        if ($this->callSilently('passport:keys') !== self::SUCCESS) {
            $this->components->twoColumnDetail('OAuth signing keys', '<fg=red;options=bold>FAILED</>');
            $this->components->warn('Could not create the signing keys. Run "php artisan passport:keys" to see why.');

            return;
        }

        $this->components->twoColumnDetail('OAuth signing keys', '<fg=green;options=bold>GENERATED</>');
    }

    /**
     * Offer to create the first administrator, if nobody can administer yet.
     */
    private function ensureAdministrator(): void
    {
        if (User::role(PlatformRole::SuperAdmin->value)->exists()) {
            $this->components->twoColumnDetail('Administrator', '<fg=green;options=bold>PRESENT</>');

            return;
        }

        if (! $this->input->isInteractive()) {
            $this->components->twoColumnDetail('Administrator', '<fg=yellow;options=bold>NONE — run "php artisan sso:admin"</>');

            return;
        }

        $this->newLine();

        if (! $this->confirm('No administrator exists yet. Create one now?', true)) {
            $this->components->warn('Create one later with "php artisan sso:admin".');

            return;
        }

        $this->call('sso:admin');
    }

    /**
     * Show the values a relying party needs in order to connect.
     *
     * The paths come from the router and the host from the issuer, because
     * the two have different authorities: a package upgrade may move a path,
     * while the public host is the operator's and may differ from APP_URL
     * behind a proxy. Generating relative and prefixing keeps both.
     */
    private function reportConfiguration(): void
    {
        $issuer = rtrim((string) config('sso.issuer'), '/');

        $endpoint = fn (string $name): string => $issuer.route($name, [], false);

        $this->components->twoColumnDetail('<fg=cyan;options=bold>Issuer</>', $issuer);
        $this->components->twoColumnDetail('Discovery', (string) config('sso.discovery_url'));
        $this->components->twoColumnDetail('Authorization', $endpoint('passport.authorizations.authorize'));
        $this->components->twoColumnDetail('Token', $endpoint('passport.token'));
        $this->components->twoColumnDetail('UserInfo', $endpoint('oidc.userinfo'));
        $this->components->twoColumnDetail('Key set', $endpoint('oidc.jwks'));
        $this->newLine();
        $this->components->info('Register an application in the interface, then hand the client its issuer and credentials.');
    }

    /**
     * Report anything about the configuration that deserves attention.
     *
     * These are warnings rather than failures, and never change the exit
     * code: an operator installing behind a terminating proxy, or working
     * locally, may legitimately trip several of them.
     */
    private function reportWarnings(): void
    {
        $issuer = (string) config('sso.issuer');

        $warnings = [];

        if ($issuer === '') {
            $warnings[] = 'SSO_ISSUER is not set. Relying parties validate the "iss" claim against it.';
        } elseif (! str_starts_with($issuer, 'https://') && ! $this->laravel->environment('local', 'testing')) {
            $warnings[] = "The issuer ({$issuer}) is not HTTPS. Tokens and authorization codes travel over it.";
        }

        if ($issuer !== '' && $issuer !== rtrim((string) config('app.url'), '/')) {
            $warnings[] = 'SSO_ISSUER and APP_URL differ. That is correct behind a proxy, and a mistake otherwise.';
        }

        if (! in_array('openid', app(ScopeRegistry::class)->ids(), true)) {
            $warnings[] = 'The "openid" scope is not configured, so this server cannot issue an ID Token.';
        }

        if ($this->laravel->isProduction() && config('app.debug') === true) {
            $warnings[] = 'APP_DEBUG is on in production. Stack traces will be shown to anyone who triggers an error.';
        }

        foreach ($warnings as $warning) {
            $this->components->warn($warning);
        }
    }
}
