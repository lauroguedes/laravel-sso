<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Settings;
use App\Services\ThemePalette;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Features;
use Throwable;

/**
 * Puts the operator's settings into the configuration the rest of the
 * framework reads.
 *
 * Everything here exists because the thing being configured does not know this
 * settings table exists: notifications read "app.name", the session guard
 * reads "session.lifetime", Fortify decides which authentication routes exist
 * from "fortify.features", the issuer reads "oidc-server.tokens". Copying the
 * values across once, at boot, is what lets an administrator change them
 * without a redeploy.
 *
 * The destination is always the key its consumer reads. Writing to this
 * project's own mirror of a setting instead would save cleanly and change
 * nothing, which is the one failure an operator cannot see.
 *
 * A setting pinned through the environment never gets a stored value, so
 * copying the settings across unconditionally still leaves a deployment that
 * manages its own configuration in charge of it.
 */
class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(ThemePalette::class);

        /*
         * In register(), not boot(): Fortify's own provider registers its
         * routes while booting, and by then a change to the feature list is
         * too late to add or remove one.
         */
        $this->withSettings($this->applyAccess(...));
    }

    public function boot(): void
    {
        $this->withSettings($this->applyBrand(...));

        $this->sharePalette();
    }

    /**
     * Run something with the stored settings, if they can be read at all.
     *
     * Guarded, because "migrate" on a fresh checkout boots before the table
     * exists. A settings read that cannot reach the database leaves the
     * configured defaults in place rather than failing the command that is
     * about to create the table.
     *
     * @param  callable(array<string, mixed>): mixed  $apply
     */
    private function withSettings(callable $apply): void
    {
        try {
            $settings = $this->app->make(Settings::class)->all();
        } catch (Throwable) {
            return;
        }

        $apply($settings);
    }

    /**
     * Hand the chosen palette to the page shell.
     *
     * Rendered into the document rather than swapped in by the client, so the
     * first paint is already the right colour and the sign-in page is themed
     * before anybody has authenticated.
     *
     * Read when the page renders rather than when this provider boots: an
     * administrator saving a new palette is answered with a redirect, and the
     * page they land on has to be the colour they just chose.
     */
    private function sharePalette(): void
    {
        View::composer('app', function ($view): void {
            /*
             * Read here rather than at boot, and without the guard the boot
             * callers need: a view only renders once the application is
             * serving, by which point the table exists.
             */
            $settings = $this->app->make(Settings::class);

            $view->with('themeStylesheet', $this->app->make(ThemePalette::class)->stylesheet(
                (string) $settings->get('base_color'),
                (string) $settings->get('accent'),
            ));
        });
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function applyBrand(array $settings): void
    {
        $name = $settings['brand_name'] ?? null;

        /*
         * Not skipped in the console: a queue worker sending a verification
         * message is a console process, and skipping there would put the
         * placeholder name in the one place the brand matters most.
         */
        if (is_string($name) && $name !== '') {
            config(['app.name' => $name]);
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function applyAccess(array $settings): void
    {
        config([
            /*
             * "oidc-server.tokens" and not a copy under "sso": that block is
             * what the issuer reads when it mints a token, and a mirror the
             * interface wrote to instead would save cleanly and change
             * nothing.
             */
            'oidc-server.tokens.access_token_ttl' => $settings['access_token_ttl'],
            'oidc-server.tokens.refresh_token_ttl' => $settings['refresh_token_ttl'],
            'oidc-server.tokens.id_token_ttl' => $settings['id_token_ttl'],
            'session.lifetime' => $settings['session_lifetime'],
            'activitylog.clean_after_days' => $settings['audit_retention_days'],
        ]);

        /*
         * Fortify decides which authentication routes exist from this list, so
         * turning registration off has to remove the feature rather than hide
         * a link: otherwise the page stays reachable by URL.
         */
        /** @var list<mixed> $configured */
        $configured = config('fortify.features', []);

        $switchable = [Features::registration(), Features::emailVerification()];

        $features = array_values(array_filter(
            $configured,
            fn (mixed $feature): bool => ! in_array($feature, $switchable, true),
        ));

        if ($settings['allow_registration'] === true) {
            $features[] = Features::registration();
        }

        if ($settings['require_email_verification'] === true) {
            $features[] = Features::emailVerification();
        }

        config(['fortify.features' => $features]);
    }
}
