<?php

declare(strict_types=1);

namespace App\Oidc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Oidc\Contracts\DiscoversProvider;
use Illuminate\Http\JsonResponse;

/**
 * Serves the document relying parties configure themselves from.
 */
class DiscoveryController extends Controller
{
    public function __construct(private readonly DiscoversProvider $discovery) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json($this->discovery->metadata())
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
