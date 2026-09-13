<?php

declare(strict_types=1);

namespace App\Oidc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Oidc\Contracts\ProvidesSigningKeys;
use Illuminate\Http\JsonResponse;

/**
 * Publishes the keys ID Tokens are verified with.
 */
class KeySetController extends Controller
{
    public function __construct(private readonly ProvidesSigningKeys $keys) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json($this->keys->keySet())
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
