<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * The settings an operator can change from the interface.
 *
 * Every setting has a default in "config/sso.php", so a fresh install works
 * with an empty table and an operator only stores what they actually changed.
 * That also means a setting can be removed from the interface without orphaning
 * a column, and a deployment can still pin one through configuration.
 *
 * The whole set is read on nearly every request — the brand appears in the
 * sidebar, the layout choice decides which shell renders — so it is loaded
 * once and cached until something writes.
 */
class Settings
{
    private const CACHE_KEY = 'sso.settings';

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    /**
     * Every setting, stored values over configured defaults.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        /** @var array<string, mixed> $stored */
        $stored = Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()->pluck('value', 'key')->all(),
        );

        return $this->loaded = [...$this->defaults(), ...$stored];
    }

    /**
     * One setting.
     */
    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Store the settings an operator changed.
     *
     * A value equal to the configured default is deleted rather than written,
     * so the table records decisions rather than a copy of the configuration,
     * and changing a default later still reaches installs that never touched
     * that setting. A pinned setting is ignored outright: the interface shows
     * it as fixed, and a request that names one anyway is a client acting
     * outside the form.
     *
     * @param  array<string, mixed>  $values
     */
    public function put(array $values): void
    {
        $defaults = $this->defaults();
        $pinned = $this->pinned();

        $unchanged = [];
        $changed = [];

        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $defaults) || in_array($key, $pinned, true)) {
                continue;
            }

            if ($value === $defaults[$key]) {
                $unchanged[] = $key;

                continue;
            }

            $changed[] = ['key' => $key, 'value' => json_encode($value)];
        }

        /* Two statements rather than two per key: a tab posts up to eight. */
        if ($unchanged !== []) {
            Setting::query()->whereIn('key', $unchanged)->delete();
        }

        if ($changed !== []) {
            Setting::query()->upsert($changed, ['key'], ['value']);
        }

        $this->flush();
    }

    /**
     * The settings this deployment fixed through the environment.
     *
     * @return list<string>
     */
    public function pinned(): array
    {
        /** @var list<string> */
        return config('sso.pinned', []);
    }

    /**
     * Discard every stored setting, returning the whole server to its defaults.
     */
    public function reset(): void
    {
        Setting::query()->delete();

        $this->flush();
    }

    /**
     * Forget the cached set.
     */
    public function flush(): void
    {
        $this->loaded = null;

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * What every setting is when nobody has changed it.
     *
     * Held in configuration rather than here so that a deployment can raise
     * one, and so that "what shipped" stays a fixed reference: the values an
     * administrator chose are copied into the configuration their consumers
     * read, never back into this block.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        /** @var array<string, mixed> */
        return config('sso.defaults');
    }

    /**
     * Where an uploaded file lives, if that setting holds one.
     */
    public function path(string $key): ?string
    {
        $path = $this->get($key);

        return is_string($path) ? $path : null;
    }
}
