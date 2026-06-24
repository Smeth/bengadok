<?php

namespace App\Http\Responses;

use App\Support\AuthRedirectPaths;
use Illuminate\Contracts\Support\Responsable;
use Laravel\Fortify\Fortify;

/**
 * Comme RedirectAsIntended Fortify, mais filtre url.intended selon le rôle.
 */
class SafeRedirectAsIntended implements Responsable
{
    public function __construct(public string $name) {}

    public function toResponse($request)
    {
        $fallback = Fortify::redirects($this->name);

        return redirect(AuthRedirectPaths::resolveDestination($request, fallback: $fallback));
    }
}
