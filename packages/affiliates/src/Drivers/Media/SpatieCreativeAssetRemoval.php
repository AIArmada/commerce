<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Drivers\Media;

use AIArmada\Affiliates\Contracts\CreativeAssetRemoval;
use LogicException;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class SpatieCreativeAssetRemoval implements CreativeAssetRemoval
{
    public function remove(string $creativeId, string $morphType, int $connectionIdentity): void
    {
        $mediaClass = config('media-library.media_model', Media::class);

        if (! is_string($mediaClass) || ! is_a($mediaClass, Media::class, true)) {
            throw new LogicException('Invalid media model configuration.');
        }

        $model = new $mediaClass;
        $connection = $model->getConnection();

        if (spl_object_id($connection) !== $connectionIdentity || $connection->transactionLevel() < 1) {
            throw new LogicException('Creative media deletion requires the active creative database transaction.');
        }

        $media = $model->newModelQuery()
            ->where('model_type', $morphType)
            ->where('model_id', $creativeId)
            ->lockForUpdate()
            ->get();

        foreach ($media as $asset) {
            // The media observer deletes files immediately; remove the database row
            // transactionally and release its files only after the outer commit.
            $asset->newModelQuery()->whereKey($asset->getKey())->delete();
            $connection->afterCommit(static fn () => app(Filesystem::class)->removeAllFiles($asset));
        }
    }
}
