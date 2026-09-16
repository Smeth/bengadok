<?php

namespace App\Support;

final class PharmacyVendeurSelfService
{
    public static function enabled(): bool
    {
        return (bool) config('bengadok.features.pharmacy_vendeur_self_service', false);
    }

    public static function ensureEnabled(): void
    {
        if (! self::enabled()) {
            abort(404);
        }
    }
}
