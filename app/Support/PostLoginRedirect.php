<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Stocke l'URL cible après connexion pour l'écran de chargement intermédiaire.
 */
final class PostLoginRedirect
{
    public const SESSION_KEY = 'post_login_redirect';

    /**
     * Récupère url.intended si valide, sinon $fallback, et l'enregistre en session.
     */
    public static function storeTarget(Request $request, string $fallback): string
    {
        $intended = $request->session()->pull('url.intended');
        $target = self::sanitize($intended, $fallback, $request);
        $request->session()->put(self::SESSION_KEY, $target);

        return $target;
    }

    private static function sanitize(mixed $url, string $fallback, Request $request): string
    {
        if (! is_string($url) || $url === '') {
            return $fallback;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            if (str_contains($url, "\0") || str_contains($url, '\\')) {
                return $fallback;
            }

            return $url;
        }

        $base = $request->getSchemeAndHttpHost();
        if (str_starts_with($url, $base)) {
            $path = parse_url($url, PHP_URL_PATH);
            if (is_string($path) && str_starts_with($path, '/')) {
                return $path;
            }
        }

        return $fallback;
    }
}
