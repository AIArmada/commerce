<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Actions;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final readonly class DeleteCatalogImportFile
{
    public function execute(string $path): void
    {
        if (preg_match('~^imports/[a-zA-Z0-9_-]+\\.csv$~i', $path) !== 1) {
            throw new InvalidArgumentException('Invalid catalog import path.');
        }

        Storage::disk('local')->delete($path);
    }
}
