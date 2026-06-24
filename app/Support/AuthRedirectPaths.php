<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * URLs d’accueil et chemins autorisés selon le rôle (évite qu’un vendeur/gérant
 * atterrisse sur le back-office via url.intended ou un lien direct).
 */
final class AuthRedirectPaths
{
    public static function homeForUser(?User $user): string
    {
        if (! $user) {
            return '/login';
        }

        $roles = $user->getRoleNames()->toArray();

        if (self::isVendeurSeul($roles)) {
            return '/dok-pharma/commandes';
        }

        if (self::isPharmacyStaff($roles) && ! self::isAdmin($roles)) {
            return '/dok-pharma';
        }

        if (self::isAgentCallCenterOnly($roles)) {
            return '/commandes';
        }

        return (string) config('fortify.home', '/dashboard');
    }

    /**
     * Destination après auth / vérif. e-mail : url.intended si autorisée, sinon accueil rôle.
     */
    public static function resolveDestination(Request $request, ?User $user = null, ?string $fallback = null): string
    {
        $user ??= $request->user();
        $fallback ??= self::homeForUser($user);
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || $intended === '') {
            return $fallback;
        }

        $path = self::pathFromUrl($intended, $request);

        if ($path !== null && self::pathAllowedForUser($user, $path)) {
            return $path;
        }

        return $fallback;
    }

    /** Préfixes URL considérés comme back-office (garde-fou routes + tests). */
    public static function backofficePathPrefixes(): array
    {
        return ['/clients', '/medicaments', '/pharmacies', '/commandes', '/utilisateurs', '/agent', '/dashboard'];
    }

    public static function pathAllowedForUser(?User $user, mixed $path): bool
    {
        if (! $user || ! is_string($path) || $path === '') {
            return false;
        }

        $path = self::normalizePath($path);

        if (in_array($path, ['/chargement', '/logout'], true)) {
            return true;
        }

        if (str_starts_with($path, '/settings') || $path === '/reglages') {
            return true;
        }

        if (str_starts_with($path, '/ordonnances/')) {
            return true;
        }

        $roles = $user->getRoleNames()->toArray();

        if (self::isAdmin($roles)) {
            return true;
        }

        if (str_starts_with($path, '/dok-pharma')) {
            return self::isPharmacyStaff($roles);
        }

        if (str_starts_with($path, '/pharmacie/')) {
            return in_array('gerant', $roles, true);
        }

        if (! self::isBackofficePath($path)) {
            return true;
        }

        if (self::isAgentCallCenterOnly($roles)) {
            return self::isAgentBackofficePath($path);
        }

        if (self::isPharmacyStaff($roles)) {
            return in_array('gerant', $roles, true)
                && ($path === '/dashboard' || str_starts_with($path, '/dashboard/'));
        }

        return false;
    }

    private static function normalizePath(string $path): string
    {
        $parsed = parse_url($path, PHP_URL_PATH);

        if (! is_string($parsed) || $parsed === '') {
            return '/';
        }

        return '/'.ltrim($parsed, '/');
    }

    private static function isBackofficePath(string $path): bool
    {
        foreach (self::backofficePathPrefixes() as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private static function pathFromUrl(string $url, Request $request): ?string
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            if (str_contains($url, "\0") || str_contains($url, '\\')) {
                return null;
            }

            $path = parse_url($url, PHP_URL_PATH);

            return is_string($path) && $path !== '' ? self::normalizePath($path) : self::normalizePath($url);
        }

        $base = $request->getSchemeAndHttpHost();
        if (str_starts_with($url, $base)) {
            $path = parse_url($url, PHP_URL_PATH);
            if (is_string($path) && str_starts_with($path, '/')) {
                return self::normalizePath($path);
            }
        }

        return null;
    }

    private static function isAgentBackofficePath(string $path): bool
    {
        foreach (['/commandes', '/medicaments', '/clients', '/agent', '/dashboard'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $roles */
    private static function isAdmin(array $roles): bool
    {
        return in_array('admin', $roles, true) || in_array('super_admin', $roles, true);
    }

    /** @param  list<string>  $roles */
    private static function isPharmacyStaff(array $roles): bool
    {
        return in_array('gerant', $roles, true) || in_array('vendeur', $roles, true);
    }

    /** @param  list<string>  $roles */
    private static function isVendeurSeul(array $roles): bool
    {
        return in_array('vendeur', $roles, true)
            && ! in_array('gerant', $roles, true)
            && ! self::isAdmin($roles);
    }

    /** @param  list<string>  $roles */
    private static function isAgentCallCenterOnly(array $roles): bool
    {
        return in_array('agent_call_center', $roles, true) && ! self::isAdmin($roles);
    }
}
