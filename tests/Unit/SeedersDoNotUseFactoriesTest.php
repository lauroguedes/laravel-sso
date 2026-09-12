<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * Seeders never call a factory or fake().
 *
 * A server installs with "composer install --no-dev", which leaves out Faker,
 * and Laravel only defines fake() when Faker is installed. The suite always has
 * it, so a seeder that calls a factory passes every test and fails on the
 * server, which is how sso:demo-reset first broke on the public demo.
 *
 * Read as PHP tokens with comments dropped, so a comment explaining the rule
 * does not break it.
 */
test('no seeder calls a factory or fake()', function () {
    $offenders = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 2).'/database/seeders')->name('*.php') as $file) {
        $tokens = array_values(array_filter(
            token_get_all($file->getContents()),
            fn ($token): bool => ! (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)),
        ));

        foreach ($tokens as $index => $token) {
            if (! is_array($token) || ! in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            $name = strtolower(ltrim($token[1], '\\'));
            $previous = $tokens[$index - 1] ?? null;
            $afterMember = is_array($previous) && in_array($previous[0], [T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_FUNCTION], true);
            $calls = ($tokens[$index + 1] ?? null) === '(';

            $factory = $name === 'factory' && is_array($previous) && $previous[0] === T_DOUBLE_COLON;
            $fake = $name === 'fake' && ! $afterMember;

            if ($calls && ($factory || $fake)) {
                $offenders[] = $file->getRelativePathname().':'.$token[2].' calls '.$token[1].'()';
            }
        }
    }

    expect($offenders)->toBe([]);
});
