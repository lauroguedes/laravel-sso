<?php

use App\Services\GitHubStars;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('the star count comes from GitHub, asked once and then remembered', function () {
    Http::fake(['api.github.com/repos/lauroguedes/laravel-sso' => Http::response(['stargazers_count' => 42])]);

    $stars = app(GitHubStars::class);

    expect($stars->count('https://github.com/lauroguedes/laravel-sso'))->toBe(42)
        ->and($stars->count('https://github.com/lauroguedes/laravel-sso'))->toBe(42);

    Http::assertSentCount(1);
});

test('a refresh that GitHub fails keeps the last good count', function () {
    $this->withoutDefer();

    Http::fakeSequence()
        ->push(['stargazers_count' => 42])
        ->push([], 503);

    $stars = app(GitHubStars::class);

    expect($stars->count('https://github.com/lauroguedes/laravel-sso'))->toBe(42);

    $this->travel(2)->hours();

    expect($stars->count('https://github.com/lauroguedes/laravel-sso'))->toBe(42)
        ->and($stars->count('https://github.com/lauroguedes/laravel-sso'))->toBe(42);

    Http::assertSentCount(2);
});

test('a count the cache hands back as text is still a number', function () {
    /*
     * The array store the suite runs on keeps the int, so without this the
     * suite passes while every cached page on redis fails.
     */
    Cache::shouldReceive('flexible')->andReturn('7');

    expect(app(GitHubStars::class)->count('https://github.com/lauroguedes/laravel-sso'))->toBe(7);
});

test('an error from GitHub hides the count and is not retried on every page', function () {
    Http::fake(['api.github.com/*' => Http::response([], 503)]);

    $stars = app(GitHubStars::class);

    expect($stars->count('https://github.com/lauroguedes/laravel-sso'))->toBe(0)
        ->and($stars->count('https://github.com/lauroguedes/laravel-sso'))->toBe(0);

    Http::assertSentCount(1);
});

test('an unreachable GitHub hides the count rather than failing the page', function () {
    Http::fake(fn () => throw new ConnectionException('GitHub is unreachable.'));

    expect(app(GitHubStars::class)->count('https://github.com/lauroguedes/laravel-sso'))->toBe(0);
});

test('a link that is not a GitHub repository is never looked up', function () {
    Http::fake();

    expect(app(GitHubStars::class)->count('https://example.com/handbook'))->toBe(0);

    Http::assertNothingSent();
});

test('the documentation header shows the stars beside the GitHub link', function () {
    Http::fake(['api.github.com/*' => Http::response(['stargazers_count' => 1234])]);

    $this->get(route('laradocs.index'))
        ->assertOk()
        ->assertSee('class="laradocs-stars"', false)
        ->assertSee('aria-label="1234 GitHub stars"', false)
        ->assertSee('1.2K');
});

test('the documentation header shows no count for a repository nobody has starred', function () {
    Http::fake(['api.github.com/*' => Http::response(['stargazers_count' => 0])]);

    $this->get(route('laradocs.index'))
        ->assertOk()
        ->assertDontSee('class="laradocs-stars"', false);
});

test('the documentation footer carries the credit', function () {
    Http::fake();

    $this->get(route('laradocs.index'))
        ->assertOk()
        ->assertSee('Crafted by an Artisan ♥ Lauro Guedes', false)
        ->assertSee('href="https://lauroguedes.dev"', false);
});
