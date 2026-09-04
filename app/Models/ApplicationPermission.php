<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ApplicationPermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A capability an application recognises, such as "reports.view".
 *
 * This server stores and reports permissions; it never enforces them. The
 * application receiving them in a token decides what they allow.
 *
 * @property int $id
 * @property string $application_id
 * @property string $name
 * @property string|null $description
 */
#[Fillable(['name', 'description'])]
class ApplicationPermission extends Model
{
    /** @use HasFactory<ApplicationPermissionFactory> */
    use HasFactory;
}
