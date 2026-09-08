<?php

declare(strict_types=1);

namespace App\Concerns;

/**
 * Removes the empty URI fields a form always submits.
 *
 * RedirectUriFields renders one blank input so there is something to type
 * into, and an untouched list therefore arrives as [""] rather than []. Left
 * alone, that fails the "required" rule on each entry and makes an
 * application with no post-logout URI impossible to save — even though having
 * none is the documented default.
 *
 * Dropping the blanks before validation rather than relaxing the rule keeps
 * "every registered URI is a real URI" true, which is what the exact matching
 * at the authorization and logout endpoints depends on.
 */
trait DiscardsBlankUris
{
    /**
     * The URI lists this request accepts.
     */
    private const URI_FIELDS = ['redirect_uris', 'post_logout_redirect_uris'];

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $cleaned = [];

        foreach (self::URI_FIELDS as $field) {
            $submitted = $this->input($field);

            if (! is_array($submitted)) {
                continue;
            }

            $cleaned[$field] = array_values(array_filter(
                $submitted,
                fn (mixed $uri): bool => is_string($uri) && trim($uri) !== '',
            ));
        }

        $this->merge($cleaned);
    }
}
