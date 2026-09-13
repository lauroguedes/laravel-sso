<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSigningKeys();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Give the suite the signing keys a real installation has.
     *
     * Access tokens, ID Tokens and the key set read storage/oauth-*.key when
     * PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY are not set, and the admin9
     * adapter always reads that path. Existing keys are never replaced.
     */
    private function ensureSigningKeys(): void
    {
        if (! file_exists(storage_path('oauth-private.key')) && Artisan::call('passport:keys') !== 0) {
            throw new RuntimeException('Could not generate the OAuth signing keys the test suite needs: '.Artisan::output());
        }
    }
}
