<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One operator-changed setting.
 *
 * Read through App\Services\Settings rather than directly: that class holds
 * the defaults and the cache, so nothing else has to know that a missing row
 * means "unchanged".
 *
 * @property string $key
 * @property mixed $value
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
