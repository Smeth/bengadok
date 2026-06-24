<?php

namespace App\Http\Middleware;

use App\Support\AuthRedirectPaths;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque l’accès au back-office pour vendeur / gérant (sauf dashboard gérant).
 */
class EnsureBackofficeAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $path = '/'.$request->path();

        if ($user && AuthRedirectPaths::pathAllowedForUser($user, $path)) {
            return $next($request);
        }

        if ($user) {
            return redirect(AuthRedirectPaths::homeForUser($user));
        }

        return redirect()->route('login');
    }
}
