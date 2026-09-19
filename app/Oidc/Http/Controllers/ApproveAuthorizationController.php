<?php

declare(strict_types=1);

namespace App\Oidc\Http\Controllers;

use App\Oidc\Passport\AuthorizationContext;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController as PassportApproveAuthorizationController;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passport's consent approval, restoring the context the authorization started with.
 */
class ApproveAuthorizationController extends PassportApproveAuthorizationController
{
    public function approve(Request $request, ResponseInterface $psrResponse): Response
    {
        $pending = $request->session()->pull(AuthorizationController::PENDING_SESSION_KEY);
        $context = AuthorizationContext::current();

        if (is_array($pending)) {
            $context->remember($pending['nonce'], $pending['auth_time']);
        }

        try {
            return parent::approve($request, $psrResponse);
        } finally {
            $context->pull();
        }
    }
}
