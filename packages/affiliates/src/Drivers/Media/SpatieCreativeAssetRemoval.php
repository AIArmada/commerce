<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Drivers\Media;

use AIArmada\Affiliates\Contracts\CreativeAssetRemoval;
use AIArmada\Affiliates\Contracts\CreativeCleanupQueue;
use LogicException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class SpatieCreativeAssetRemoval implements CreativeAssetRemoval
{
    public function __construct(private CreativeCleanupQueue $cleanup) {}

    public function remove(string $creativeId, string $morphType, int $connectionIdentity, ?string $collection): void
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

        $query = $model->newModelQuery()
            ->where('model_type', $morphType)
            ->where('model_id', $creativeId);

        if ($collection !== null) {
            $query->where('collection_name', $collection);
        }

        $media = $query->lockForUpdate()->get();

        foreach ($media as $asset) {
            $this->cleanup->enqueue($mediaClass, $asset->getAttributes(), $connectionIdentity);
            $asset->newModelQuery()->whereKey($asset->getKey())->delete();
        }
    }
}
