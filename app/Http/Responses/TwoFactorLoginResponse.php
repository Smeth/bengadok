<?php

namespace App\Http\Responses;

use App\Support\AuthRedirectPaths;
use App\Support\PostLoginRedirect;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    /**
     * Après 2FA : écran de chargement puis destination autorisée pour le rôle.
     */
    public function toResponse($request): Response
    {
        $fallback = AuthRedirectPaths::homeForUser($request->user());
        PostLoginRedirect::storeTarget($request, $fallback);

        if ($request->wantsJson()) {
            return response()->json(['two_factor' => true, 'redirect' => url('/chargement')]);
        }

        return redirect('/chargement');
    }
}
