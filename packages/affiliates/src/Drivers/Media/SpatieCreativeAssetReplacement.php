<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Drivers\Media;

use AIArmada\Affiliates\Contracts\CreativeAssetRemoval;
use AIArmada\Affiliates\Contracts\CreativeAssetReplacement;
use AIArmada\Affiliates\Contracts\CreativeCleanupQueue;
use AIArmada\Affiliates\Data\PreparedCreativeAsset;
use ArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final readonly class SpatieCreativeAssetReplacement implements CreativeAssetReplacement
{
    public function __construct(private CreativeAssetRemoval $removal, private CreativeCleanupQueue $cleanup) {}

    public function replace(string $creativeId, string $morphType, int $connectionIdentity, PreparedCreativeAsset $asset): void
    {
        $class = Relation::getMorphedModel($morphType) ?? $morphType;

        if (! is_a($class, Model::class, true) || ! is_a($class, HasMedia::class, true)) {
            throw new LogicException('Invalid creative media subject.');
        }

        $subject = (new $class)->newModelQuery()->findOrFail($creativeId);
        $connection = $subject->getConnection();

        if (spl_object_id($connection) !== $connectionIdentity || $connection->transactionLevel() < 1) {
            throw new LogicException('Creative asset replacement requires the active creative database transaction.');
        }

        $this->removal->remove($creativeId, $morphType, $connectionIdentity, 'creative_asset');
        $subject->unsetRelation('media');

        /** @var ArrayObject<int, Media> $added */
        $added = new ArrayObject;
        $connection->afterRollBack(function () use ($added, $connectionIdentity): void {
            foreach ($added as $item) {
                try {
                    $this->cleanup->recoverDetached($item::class, $item->getAttributes(), $connectionIdentity);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        });

        $path = tempnam(sys_get_temp_dir(), 'affiliate-creative');

        if ($path === false) {
            throw new RuntimeException('Creative asset staging failed.');
        }

        try {
            if (file_put_contents($path, $asset->bytes) !== mb_strlen($asset->bytes, '8bit')) {
                throw new RuntimeException('Creative asset staging failed.');
            }

            try {
                $added[] = $subject->addMedia($path)
                    ->usingFileName((string) Str::uuid() . '.' . $asset->extension)
                    ->toMediaCollection('creative_asset');
            } catch (Throwable $exception) {
                try {
                    $added->exchangeArray($subject->media()->where('collection_name', 'creative_asset')->get()->all());
                } catch (Throwable $captureFailure) {
                    report($captureFailure);
                }

                throw $exception;
            }
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
