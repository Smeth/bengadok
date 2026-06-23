<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

final class PharmacieUsernameGenerator
{
    /** Prochain identifiant numérique (aperçu formulaire — peut varier si création concurrente). */
    public static function predictNextUserId(): int
    {
        return (int) (User::max('id') ?? 0) + 1;
    }

    public static function build(
        int $userId,
        string $pharmacieDesignation,
        string $role,
        string $userName,
    ): string {
        $slugPharma = Str::slug($pharmacieDesignation !== '' ? $pharmacieDesignation : 'pharmacie');
        $slugNom = Str::slug($userName);
        $roleNorm = in_array($role, ['gerant', 'vendeur'], true) ? $role : 'vendeur';

        return "{$slugPharma}_{$roleNorm}_{$slugNom}_{$userId}";
    }

    public static function assign(
        User $user,
        string $pharmacieDesignation,
        string $role,
        string $userName,
    ): string {
        $username = self::build($user->id, $pharmacieDesignation, $role, $userName);
        $user->update(['username' => $username]);

        return $username;
    }
}
