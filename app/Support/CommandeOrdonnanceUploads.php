<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class CommandeOrdonnanceUploads
{
    public const MAX_FILES = 10;

    public const FILE_RULE = 'file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240';

    /**
     * @return list<UploadedFile>
     */
    public static function fromRequest(Request $request): array
    {
        $files = [];

        $primary = $request->file('ordonnance');
        if ($primary instanceof UploadedFile) {
            $files[] = $primary;
        }

        $extra = $request->file('ordonnances');
        if ($extra instanceof UploadedFile) {
            $files[] = $extra;
        } elseif (is_array($extra)) {
            foreach ($extra as $file) {
                if ($file instanceof UploadedFile) {
                    $files[] = $file;
                }
            }
        }

        return array_slice($files, 0, self::MAX_FILES);
    }

    /**
     * @return array{0: UploadedFile|null, 1: list<UploadedFile>}
     */
    public static function split(Request $request): array
    {
        $files = self::fromRequest($request);

        return [$files[0] ?? null, array_slice($files, 1)];
    }
}
