<?php

namespace App\Http\Controllers;

use App\Support\PostLoginRedirect;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostLoginLoadingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $target = $request->session()->pull(PostLoginRedirect::SESSION_KEY);

        if (! $this->isSafeInternalPath($target)) {
            $user = $request->user();
            $roles = $user?->getRoleNames()->toArray() ?? [];
            $isPharmacie = in_array('gerant', $roles) || in_array('vendeur', $roles);
            $isVendeurSeul = in_array('vendeur', $roles) && ! in_array('gerant', $roles);
            $isAdmin = in_array('admin', $roles) || in_array('super_admin', $roles);
            $isAgentCallCenterOnly =
                in_array('agent_call_center', $roles) && ! $isAdmin;
            $target = $isVendeurSeul
                ? '/dok-pharma/commandes'
                : ($isPharmacie
                    ? '/dok-pharma'
                    : ($isAgentCallCenterOnly
                        ? '/commandes'
                        : (string) config('fortify.home', '/dashboard')));
        }

        return Inertia::render('auth/PostLoginLoading', [
            'redirectTo' => $target,
        ]);
    }

    private function isSafeInternalPath(mixed $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }
        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return false;
        }
        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            return false;
        }

        return true;
    }
}
