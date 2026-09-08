<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * Every component a template uses has to be imported by the file using it.
 *
 * Vue resolves components in a single-file component from its own script
 * bindings. One that is not imported is not an error: it renders as an unknown
 * element, so the control silently becomes inert markup and any text inside it
 * — a tooltip's words, for instance — appears on the page. Neither vue-tsc nor
 * the linter reports it, and a screenshot is the only other way to notice.
 *
 * This has happened once, to the regenerate-secret button on an application's
 * credentials, after an extraction left the old markup behind and removed the
 * imports it needed.
 */
test('every component used in a template is imported by the file using it', function () {
    /*
     * Vue's own elements, and the ones registered globally by Inertia.
     */
    $provided = ['component', 'template', 'slot', 'Transition', 'TransitionGroup', 'Teleport', 'Suspense', 'KeepAlive', 'Head', 'Link'];

    $offenders = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 2).'/resources/js')->name('*.vue') as $file) {
        $source = $file->getContents();
        $position = strpos($source, '<template>');

        if ($position === false) {
            continue;
        }

        $script = substr($source, 0, $position);
        $template = substr($source, $position);

        preg_match_all('/<([A-Z][A-Za-z0-9]*)/', $template, $matches);

        foreach (array_unique($matches[1]) as $used) {
            if (in_array($used, $provided, true)) {
                continue;
            }

            if (preg_match('/\b'.preg_quote($used, '/').'\b/', $script) !== 1) {
                $offenders[] = $file->getRelativePathname().' uses <'.$used.'>';
            }
        }
    }

    expect($offenders)->toBe([]);
});
