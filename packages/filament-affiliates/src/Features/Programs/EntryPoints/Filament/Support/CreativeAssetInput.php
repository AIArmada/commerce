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

        $maxBytes = (int) config('media-library.max_file_size', 10485760);

        if (! $state instanceof TemporaryUploadedFile || $state->getSize() > $maxBytes) {
            throw ValidationException::withMessages(['asset' => 'Choose a valid temporary upload within the configured size limit.']);
        }

        $bytes = $state->get();
        $actualBytes = is_string($bytes) ? mb_strlen($bytes, '8bit') : 0;

        if (! is_string($bytes) || $bytes === '' || $actualBytes > $maxBytes || $actualBytes !== $state->getSize()) {
            throw ValidationException::withMessages(['asset' => 'The asset could not be read or exceeds the configured size limit.']);
        }

        return $bytes;
    }
}
