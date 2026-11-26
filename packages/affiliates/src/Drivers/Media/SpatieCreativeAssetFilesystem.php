<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Drivers\Media;

use AIArmada\Affiliates\Contracts\CreativeAssetFilesystem;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class SpatieCreativeAssetFilesystem implements CreativeAssetFilesystem
{
    public function removeDetached(string $mediaClass, array $attributes, int $connectionIdentity): void
    {
        if ($mediaClass !== config('media-library.media_model', Media::class) || ! is_a($mediaClass, Media::class, true)) {
            throw new LogicException('Creative cleanup media model no longer matches configuration.');
        }

        $asset = new $mediaClass;
        $asset->setRawAttributes($attributes, true);
        $connection = $asset->getConnection();

        if (spl_object_id($connection) !== $connectionIdentity || $asset->getKey() === null) {
            throw new LogicException('Creative cleanup requires the original media database connection and identity.');
        }

        if ($asset->newModelQuery()->whereKey($asset->getKey())->exists()) {
            throw new RuntimeException('Creative cleanup refused to remove files belonging to a live media record.');
        }

        $originalPath = $asset->getPathRelativeToRoot();

        app(Filesystem::class)->removeAllFiles($asset);

        foreach (array_unique(array_filter([$asset->disk, $asset->conversions_disk])) as $disk) {
            if (Storage::disk($disk)->fileExists($originalPath)) {
                throw new RuntimeException('Detached creative media file still exists after cleanup.');
            }
        }
    }
}
