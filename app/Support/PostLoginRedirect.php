<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Stocke l'URL cible après connexion pour l'écran de chargement intermédiaire.
 */
final class PostLoginRedirect
{
    public const SESSION_KEY = 'post_login_redirect';

    /**
     * Récupère url.intended si valide et autorisé pour le rôle, sinon $fallback.
     */
    public static function storeTarget(Request $request, string $fallback): string
    {
        $user = $request->user();
        $intended = $request->session()->pull('url.intended');
        $target = self::sanitize($intended, $fallback, $request);

        if (! AuthRedirectPaths::pathAllowedForUser($user, $target)) {
            $target = AuthRedirectPaths::homeForUser($user);
        }

        $request->session()->put(self::SESSION_KEY, $target);

        return $target;
    }

    public static function resolveStoredTarget(Request $request, ?User $user): string
    {
        $stored = $request->session()->pull(self::SESSION_KEY);
        $fallback = AuthRedirectPaths::homeForUser($user);

        if (! is_string($stored) || $stored === '') {
            return $fallback;
        }

        if (! AuthRedirectPaths::pathAllowedForUser($user, $stored)) {
            return $fallback;
        }

        return $stored;
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
