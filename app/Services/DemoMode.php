<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * What a public demonstration does differently, in one place.
 *
 * A demo is only a demo if a stranger can get in, so the administrator's
 * password is shown on the sign-in page and typed into the form for them.
 * That is the one place in this project where a password is deliberately
 * recoverable, and it is bounded on both sides: nothing is written unless the
 * deployment declared itself a demo, and nothing is read back unless it still
 * says so, so switching the flag off leaves any file behind it inert.
 *
 * A new password on every reset is what stops the demo's credentials becoming
 * a fact of the internet. Whatever the last visitor wrote down stops working
 * the next time the scheduler runs.
 *
 * Everything else a demo forbids is decided in "config/sso.php", which can
 * pin a setting as well as default it. What is left here is what needs a
 * running application to say: the mail transport, and the credentials.
 */
class DemoMode
{
    /**
     * Where the published credentials live, on the private disk.
     */
    private const FILE = 'demo-credentials.json';

    /**
     * Whether this installation declared itself a public demonstration.
     */
    public function enabled(): bool
    {
        return (bool) config('sso.demo.enabled');
    }

    /**
     * Close what a stranger could otherwise abuse.
     *
     * Only the mail transport for now. Everything this server sends goes to an
     * address somebody typed in, so on a demo nothing leaves at all. The
     * "array" transport is chosen over "log" because these messages have no
     * reader here and the log does have a size.
     */
    public function restrict(): void
    {
        if (! $this->enabled()) {
            return;
        }

        config(['mail.default' => 'array']);
    }

    /**
     * Give an account a password nobody has seen, and publish it.
     *
     * Null anywhere that is not a demo, which is what lets the seeder ask for
     * one without first asking whether this is one.
     *
     * No symbols in it: a visitor who retypes the password rather than
     * trusting the filled-in field should not be fighting their keyboard
     * layout to do it.
     */
    public function rotate(string $email): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $password = Str::password(16, symbols: false);

        $this->disk()->put(self::FILE, json_encode([
            'email' => $email,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));

        return $password;
    }

    /**
     * What the sign-in page should fill in, if anything.
     *
     * @return array{email: string, password: string}|null
     */
    public function credentials(): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        /* json() answers null for both a missing file and an undecodable one. */
        $credentials = $this->disk()->json(self::FILE);

        return is_string($credentials['email'] ?? null) && is_string($credentials['password'] ?? null)
            ? ['email' => $credentials['email'], 'password' => $credentials['password']]
            : null;
    }

    /**
     * The private disk, which is "local" and is not web-reachable.
     */
    private function disk(): FilesystemAdapter
    {
        return Storage::disk('local');
    }
}
