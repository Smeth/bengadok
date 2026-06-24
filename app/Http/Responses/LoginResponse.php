<?php

namespace App\Http\Responses;

use App\Support\AuthRedirectPaths;
use App\Support\PostLoginRedirect;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Passe par l’écran de chargement Figma, puis destination autorisée pour le rôle.
     */
    public function toResponse($request): Response
    {
        $fallback = AuthRedirectPaths::homeForUser($request->user());
        PostLoginRedirect::storeTarget($request, $fallback);

        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false, 'redirect' => url('/chargement')]);
        }

        return redirect('/chargement');
    }
}
