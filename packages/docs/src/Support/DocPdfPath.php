<?php

declare(strict_types=1);

namespace AIArmada\Docs\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class DocPdfPath
{
    public static function forDocument(string $docType, string $docId): string
    {
        if (! Str::isUuid($docId)) {
            throw new InvalidArgumentException('A persisted document UUID is required.');
        }

        $key = DocTypeKey::sanitize($docType);
        $path = $key === null ? null : config("docs.types.{$key}.storage.path");
        $base = mb_trim(is_string($path) && $path !== '' ? $path : (string) config('docs.storage.path', 'docs'), '/');
        $filename = $docId . '.pdf';

        return $base === '' ? $filename : "{$base}/{$filename}";
    }

    public static function diskForType(string $docType): string
    {
        $key = DocTypeKey::sanitize($docType);

        if ($key !== null) {
            $disk = config("docs.types.{$key}.storage.disk");

            if (is_string($disk) && $disk !== '') {
                return $disk;
            }
        }

        return config('docs.storage.disk', 'local');
    }
}
