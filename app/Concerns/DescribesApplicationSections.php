<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Application;
use App\Models\AuditRecord;
use App\Models\User;

/**
 * Which sections of one application a reader may open.
 *
 * Decided in one place because five pages share a frame that lists them, and
 * a frame that offers a section the reader cannot open is a frame that lies.
 * Each entry is answered by the same policy that guards the route behind it,
 * so the two cannot drift.
 */
trait DescribesApplicationSections
{
    /**
     * @return list<array{key: string, title: string}>
     */
    protected function applicationSections(User $user, Application $application): array
    {
        $mayView = $user->can('view', $application);

        $sections = [
            ['key' => 'overview', 'title' => 'Overview', 'allowed' => $mayView],
            ['key' => 'roles', 'title' => 'Roles', 'allowed' => $mayView],
            ['key' => 'access', 'title' => 'Access', 'allowed' => $user->can('viewAccess', $application)],
            ['key' => 'managers', 'title' => 'Managers', 'allowed' => $user->can('manageStewards', $application)],
            ['key' => 'audit', 'title' => 'Audit', 'allowed' => $user->can('viewForApplication', [AuditRecord::class, $application])],
        ];

        return array_values(array_map(
            fn (array $section): array => ['key' => $section['key'], 'title' => $section['title']],
            array_filter($sections, fn (array $section): bool => $section['allowed']),
        ));
    }
}
