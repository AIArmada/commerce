<?php

declare(strict_types=1);

namespace Facades\Livewire\Features\SupportFileUploads;

use Illuminate\Support\Facades\Facade;

/**
 * Compatibility shim for Livewire's "real-time facade" used during tests.
 *
 * Some test bootstraps (notably in CI shard runs) can execute Livewire's
 * `SupportFileUploads::provide()` before Laravel's facade bootstrapper has
 * registered the real-time facade autoloader.
 */
final class GenerateSignedUploadUrl extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl::class;
    }
}
