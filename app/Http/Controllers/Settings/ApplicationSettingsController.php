<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ApplicationSettingsRequest;
use App\Models\Setting;
use App\Services\InterfaceOptions;
use App\Services\Settings;
use App\Services\ThemePalette;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * How this server presents itself.
 *
 * Separate from the per-user settings beside it: everything here is one
 * decision for the whole installation, which is why it needs a platform
 * permission rather than being something each reader sets for themselves.
 */
class ApplicationSettingsController extends Controller
{
    /**
     * Where uploaded imagery lives.
     *
     * The public disk, because the sign-in page shows it before anyone has
     * authenticated and it cannot be behind a session.
     */
    private const IMAGE_DIRECTORY = 'branding';

    /**
     * The settings that hold an uploaded file, and the field each arrives in.
     */
    private const UPLOADS = [
        'brand_logo' => 'logo',
        'auth_background' => 'auth_background',
    ];

    public function __construct(
        private readonly Settings $settings,
        private readonly InterfaceOptions $options,
        private readonly ThemePalette $palette,
    ) {}

    /**
     * Show the settings form.
     */
    public function edit(): Response
    {
        $this->authorize('manage', Setting::class);

        return Inertia::render('settings/Application', [
            'settings' => $this->settings->all(),
            'pinned' => $this->settings->pinned(),
            'options' => $this->options->all($this->palette),
        ]);
    }

    /**
     * Save one section of them.
     */
    public function update(ApplicationSettingsRequest $request): HttpResponse
    {
        $this->settings->put([
            ...$request->settings(),
            ...$this->resolveUploads($request),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return $this->back($request->validated('section') === 'appearance');
    }

    /**
     * Put everything back to the defaults this server shipped with.
     */
    public function destroy(): HttpResponse
    {
        $this->authorize('manage', Setting::class);

        foreach (array_keys(self::UPLOADS) as $setting) {
            $this->forget($this->settings->path($setting));
        }

        $this->settings->reset();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings restored to their defaults.')]);

        return $this->back(reload: true);
    }

    /**
     * Return to the settings page.
     *
     * The palette is a stylesheet in the document head, which only a full page
     * load replaces. Saving a colour therefore has to leave the single-page
     * navigation, or an administrator would be told their choice was saved
     * while still looking at the old one.
     */
    private function back(bool $reload): HttpResponse
    {
        $destination = route('application-settings.edit');

        return $reload
            ? Inertia::location($destination)
            : redirect()->to($destination);
    }

    /**
     * The image settings this request changes, if it changes any.
     *
     * A section that carries no upload field returns nothing, so saving the
     * links tab cannot blank the logo.
     *
     * @return array<string, string|null>
     */
    private function resolveUploads(ApplicationSettingsRequest $request): array
    {
        $resolved = [];

        foreach (self::UPLOADS as $setting => $field) {
            $file = $request->file($field);
            $removing = $request->boolean('remove_'.$field);

            if (! $file instanceof UploadedFile && ! $removing) {
                continue;
            }

            $this->forget($this->settings->path($setting));

            $stored = $file?->store(self::IMAGE_DIRECTORY, 'public');

            $resolved[$setting] = is_string($stored) ? $stored : null;
        }

        return $resolved;
    }

    /**
     * Delete an image this server is done with.
     *
     * A rebranded server would otherwise accumulate every logo it ever had,
     * each of them still publicly readable.
     */
    private function forget(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }
}
