<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // Laravel passe une seule chaîne pour `role:a|b|c` (ex. vendeur|gerant), pas trois arguments.
        $allowed = collect($roles)
            ->flatMap(fn (string $chunk) => array_filter(array_map('trim', explode('|', $chunk))))
            ->values()
            ->all();

        if ($allowed !== [] && $request->user()->hasAnyRole($allowed)) {
            return $next($request);
        }

        return redirect()->route('dashboard');
    }
}
