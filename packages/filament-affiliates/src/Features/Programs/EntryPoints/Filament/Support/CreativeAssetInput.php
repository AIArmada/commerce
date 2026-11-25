<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Support;

use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class CreativeAssetInput
{
    public static function bytes(mixed $state): ?string
    {
        if ($state === null) {
            return null;
        }

        if (! $state instanceof TemporaryUploadedFile || $state->getSize() > (int) config('media-library.max_file_size', 10485760)) {
            throw ValidationException::withMessages(['asset' => 'Choose a valid temporary upload within the configured size limit.']);
        }

        $bytes = $state->get();

        if (! is_string($bytes) || $bytes === '' || mb_strlen($bytes) > (int) config('media-library.max_file_size', 10485760)) {
            throw ValidationException::withMessages(['asset' => 'The asset could not be read or exceeds the configured size limit.']);
        }

        return $bytes;
    }
}
