<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\UserDisabled;
use App\Events\UserEnabled;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Withdraws and restores a user's ability to authenticate.
 *
 * Disabling is preferred over deletion so that audit history keeps pointing at
 * a real actor.
 */
class UserStatusController extends Controller
{
    /**
     * Disable or re-enable a user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('updateStatus', $user);

        $request->validate(['enabled' => ['required', 'boolean']]);

        if ($request->boolean('enabled')) {
            $user->enable();

            UserEnabled::dispatch($user);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('User enabled.')]);
        } else {
            $user->disable();

            UserDisabled::dispatch($user);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('User disabled.')]);
        }

        return back();
    }
}
