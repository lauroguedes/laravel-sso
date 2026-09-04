<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Application;

/**
 * Reads the application a nested request belongs to from the route binding.
 *
 * Every request under "applications/{application}/..." needs it to scope its
 * validation, so the narrowing lives here once rather than as an inline @var
 * in each request class.
 *
 * The fallback branch is unreachable in practice — SubstituteBindings has
 * already resolved the binding by the time a form request validates — but it
 * is what makes the return type honest rather than asserted.
 */
trait ResolvesApplicationFromRoute
{
    /**
     * The application this request is scoped to.
     */
    public function application(): Application
    {
        $application = $this->route('application');

        return $application instanceof Application
            ? $application
            : Application::findOrFail((string) $application);
    }
}
