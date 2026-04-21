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
        $isVendeurSeul = in_array('vendeur', $roles) && ! in_array('gerant', $roles);
        $isAdmin = in_array('admin', $roles) || in_array('super_admin', $roles);
        $isAgentCallCenterOnly =
            in_array('agent_call_center', $roles) && ! $isAdmin;

        $fallback = $isVendeurSeul
            ? '/dok-pharma/commandes'
            : ($isPharmacie
                ? '/dok-pharma'
                : ($isAgentCallCenterOnly ? '/commandes' : config('fortify.home')));
        PostLoginRedirect::storeTarget($request, $fallback);

        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false, 'redirect' => url('/chargement')]);
        }

        return redirect('/chargement');
    }
}
