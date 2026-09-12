<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * How many people starred a GitHub repository, for the documentation header.
 *
 * GitHub allows an unauthenticated caller sixty requests an hour, and a
 * documentation page should not wait on somebody else's server. The count is
 * fresh for an hour, then served stale while one request refreshes it after
 * the response has gone out.
 *
 * A failed refresh keeps the last good count. Only a server that has never
 * reached GitHub answers zero, which the header treats as "show nothing".
 */
class GitHubStars
{
    /**
     * The star count for a repository URL, or zero when there is nothing to show.
     */
    public function count(string $url): int
    {
        if (preg_match('#^https://github\.com/([\w.-]+/[\w.-]+)/?$#', $url, $matches) !== 1) {
            return 0;
        }

        $repository = $matches[1];
        $key = 'github.stars.'.$repository;

        /*
         * Cast on the way out because the cache does not keep the type: redis
         * stores a plain number unserialized and hands it back as a string.
         */
        return (int) Cache::flexible($key, [3600, 604_800], function () use ($key, $repository): int {
            $stars = rescue(
                fn () => Http::acceptJson()
                    ->connectTimeout(2)
                    ->timeout(3)
                    ->get('https://api.github.com/repos/'.$repository)
                    ->json('stargazers_count'),
                report: false,
            );

            return is_int($stars) ? $stars : (int) Cache::get($key, 0);
        });
    }
}
