<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Token\Parser;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Derive the S256 code challenge for a PKCE verifier.
 */
function pkceChallenge(string $verifier): string
{
    return strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_');
}

/**
 * A fresh PKCE verifier and its matching S256 challenge.
 *
 * @return array{0: string, 1: string}
 */
function pkcePair(): array
{
    $verifier = Str::random(64);

    return [$verifier, pkceChallenge($verifier)];
}

/**
 * Decode the claims of an ID Token without verifying its signature.
 *
 * Signature and key handling are asserted separately; callers of this only
 * care what the token says.
 */
function idTokenClaims(string $idToken): DataSet
{
    return (new Parser(new JoseEncoder))->parse($idToken)->claims();
}
