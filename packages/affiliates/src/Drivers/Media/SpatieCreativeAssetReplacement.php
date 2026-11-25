<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Drivers\Media;

use AIArmada\Affiliates\Contracts\CreativeAssetRemoval;
use AIArmada\Affiliates\Contracts\CreativeAssetReplacement;
use ArrayObject;
use finfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final readonly class SpatieCreativeAssetReplacement implements CreativeAssetReplacement
{
    public function __construct(private CreativeAssetRemoval $removal) {}

    public function replace(string $creativeId, string $morphType, int $connectionIdentity, string $bytes): void
    {
        $extension = $this->extension($bytes);
        $class = Relation::getMorphedModel($morphType) ?? $morphType;

        if (! is_a($class, Model::class, true) || ! is_a($class, HasMedia::class, true)) {
            throw new LogicException('Invalid creative media subject.');
        }

        $subject = (new $class)->newModelQuery()->findOrFail($creativeId);
        $connection = $subject->getConnection();

        if (spl_object_id($connection) !== $connectionIdentity || $connection->transactionLevel() < 1) {
            throw new LogicException('Creative asset replacement requires the active creative database transaction.');
        }

        $this->removal->remove($creativeId, $morphType, $connectionIdentity);
        $subject->unsetRelation('media');

        // Track newly inserted media before the enclosing transaction can roll back.
        // The previous asset's database row and file remain recoverable on rollback.
        /** @var ArrayObject<int, Media> $added */
        $added = new ArrayObject;
        $connection->afterRollBack(static function () use ($added): void {
            foreach ($added as $asset) {
                try {
                    app(Filesystem::class)->removeAllFiles($asset);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        });

        try {
            $added[] = $subject->addMediaFromString($bytes)
                ->usingFileName((string) Str::uuid() . '.' . $extension)
                ->toMediaCollection('creative_asset');
        } catch (Throwable $exception) {
            // FileAdder can insert the media row before a later filesystem step fails.
            // Capture the partial rows while they are still visible to this transaction.
            try {
                $added->exchangeArray($subject->media()->where('collection_name', 'creative_asset')->get()->all());
            } catch (Throwable $captureFailure) {
                report($captureFailure);
            }

            throw $exception;
        }
    }

    private function extension(string $bytes): string
    {
        if ($bytes === '' || mb_strlen($bytes) > (int) config('media-library.max_file_size', 10485760)) {
            throw ValidationException::withMessages(['asset' => 'The asset is empty or exceeds the configured upload limit.']);
        }

        // Derive both MIME and extension from content, never from the client name.
        // SVG is excluded because these files are served from a public disk.
        return match ((new finfo(FILEINFO_MIME_TYPE))->buffer($bytes)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            default => throw ValidationException::withMessages(['asset' => 'The asset type is not supported.']),
        };
    }
}
