<?php

namespace App\Support;

/**
 * Limites d'upload alignées avec la validation Laravel (max:10240 = 10 Mo)
 * et les plafonds PHP / nginx réellement applicables.
 */
final class UploadLimits
{
    /** Kilooctets — doit correspondre aux règles `max:10240` des FormRequests. */
    public const APP_MAX_KILOBYTES = 10240;

    public static function appMaxBytes(): int
    {
        return self::APP_MAX_KILOBYTES * 1024;
    }

    public static function effectiveMaxBytes(): int
    {
        $upload = self::parseIniSize(ini_get('upload_max_filesize'));
        $post = self::parseIniSize(ini_get('post_max_size'));

        $phpLimit = min(
            $upload > 0 ? $upload : self::appMaxBytes(),
            $post > 0 ? $post : self::appMaxBytes(),
        );

        return min(self::appMaxBytes(), $phpLimit);
    }

    public static function effectiveMaxMegabytesLabel(): string
    {
        return self::formatMegabytesLabel(self::effectiveMaxBytes());
    }

    public static function appMaxMegabytesLabel(): string
    {
        return self::formatMegabytesLabel(self::appMaxBytes());
    }

    public static function payloadTooLargeMessage(): string
    {
        $effective = self::effectiveMaxMegabytesLabel();
        $app = self::appMaxMegabytesLabel();

        if (self::effectiveMaxBytes() < self::appMaxBytes()) {
            return "Fichier ou formulaire trop volumineux. Sur ce serveur, la limite effective est {$effective} (application : {$app} max.). Réduisez le fichier ou contactez l'administrateur.";
        }

        return "Fichier ou formulaire trop volumineux. Taille maximale acceptée : {$effective}.";
    }

    /**
     * @return array{max_bytes: int, max_label: string, app_max_bytes: int, app_max_label: string, message: string}
     */
    public static function inertiaPayload(): array
    {
        return [
            'max_bytes' => self::effectiveMaxBytes(),
            'max_label' => self::effectiveMaxMegabytesLabel(),
            'app_max_bytes' => self::appMaxBytes(),
            'app_max_label' => self::appMaxMegabytesLabel(),
            'message' => self::payloadTooLargeMessage(),
        ];
    }

    public static function parseIniSize(mixed $value): int
    {
        if ($value === false || $value === null) {
            return 0;
        }

        $value = trim(strtolower((string) $value));
        if ($value === '') {
            return 0;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*([gmk])?$/', $value, $matches) !== 1) {
            return (int) $value;
        }

        $number = (float) $matches[1];

        return (int) match ($matches[2] ?? '') {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private static function formatMegabytesLabel(int $bytes): string
    {
        $mb = $bytes / 1024 / 1024;
        $formatted = rtrim(rtrim(number_format($mb, 1, '.', ''), '0'), '.');

        return $formatted.' Mo';
    }
}
