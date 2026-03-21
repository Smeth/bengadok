<?php

namespace App\Http\Responses;

use App\Support\PostLoginRedirect;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Passe par l’écran de chargement Figma, puis url.intended ou Dok Pharma / dashboard.
     */
    public function toResponse($request): Response
    {
        $user = $request->user();
        $roles = $user?->getRoleNames()->toArray() ?? [];
        $isPharmacie = in_array('gerant', $roles) || in_array('vendeur', $roles);

        $fallback = $isPharmacie ? '/dok-pharma' : config('fortify.home');
        PostLoginRedirect::storeTarget($request, $fallback);

        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false, 'redirect' => url('/chargement')]);
        }

        return redirect('/chargement');
    }
}
