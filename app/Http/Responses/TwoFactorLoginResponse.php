<?php

namespace App\Http\Responses;

use App\Support\PostLoginRedirect;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    /**
     * Après 2FA : écran de chargement puis destination (intended ou Dok Pharma / dashboard).
     */
    public function toResponse($request): Response
    {
        $user = $request->user();
        $roles = $user?->getRoleNames()->toArray() ?? [];
        $isPharmacie = in_array('gerant', $roles) || in_array('vendeur', $roles);

        $fallback = $isPharmacie ? '/dok-pharma' : config('fortify.home');
        PostLoginRedirect::storeTarget($request, $fallback);

        if ($request->wantsJson()) {
            return response()->json(['two_factor' => true, 'redirect' => url('/chargement')]);
        }

        return redirect('/chargement');
    }
}
