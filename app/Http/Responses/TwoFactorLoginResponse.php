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
            return response()->json(['two_factor' => true, 'redirect' => url('/chargement')]);
        }

        return redirect('/chargement');
    }
}
