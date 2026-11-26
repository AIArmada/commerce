<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Actions;

use AIArmada\Affiliates\Contracts\CreativeAssetFilesystem;
use AIArmada\Affiliates\Contracts\CreativeCleanupQueue;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateCreativeAssetCleanup;
use LogicException;
use Throwable;

final readonly class ProcessCreativeAssetCleanup implements CreativeCleanupQueue
{
    public function __construct(private CreativeAssetFilesystem $filesystem) {}

    public function enqueue(string $mediaClass, array $attributes, int $connectionIdentity): void
    {
        $connection = (new AffiliateCreativeAssetCleanup)->getConnection();

        if (spl_object_id($connection) !== $connectionIdentity || $connection->transactionLevel() < 1) {
            throw new LogicException('Creative cleanup must be recorded in the active media transaction.');
        }

        $record = AffiliateCreativeAssetCleanup::query()->create([
            'media_class' => $mediaClass,
            'media_attributes' => $attributes,
        ]);
        $id = (string) $record->getKey();

        $connection->afterCommit(function () use ($id): void {
            $this->attempt($id);
        });
    }

    public function recoverDetached(string $mediaClass, array $attributes, int $connectionIdentity): void
    {
        $connection = (new AffiliateCreativeAssetCleanup)->getConnection();

        if (spl_object_id($connection) !== $connectionIdentity) {
            throw new LogicException('Creative rollback cleanup requires the original database connection.');
        }

        try {
            $this->filesystem->removeDetached($mediaClass, $attributes, $connectionIdentity);

            return;
        } catch (Throwable $exception) {
            report($exception);
        }

        // If a parent transaction rolls back, its queue insert is rolled back as well.
        if ($connection->transactionLevel() > 0) {
            $connection->afterRollBack(function () use ($mediaClass, $attributes, $connectionIdentity): void {
                $this->recoverDetached($mediaClass, $attributes, $connectionIdentity);
            });
        }

        $connection->transaction(function () use ($mediaClass, $attributes, $connectionIdentity): void {
            $this->enqueue($mediaClass, $attributes, $connectionIdentity);
        });
    }

    public function drain(int $limit): int
    {
        $failures = 0;

        $ids = AffiliateCreativeAssetCleanup::query()->orderBy('attempt_count')->oldest('created_at')->orderBy('id')
            ->limit(max(1, min(1000, $limit)))->pluck('id');

        foreach ($ids as $id) {
            if (! $this->attempt((string) $id)) {
                $failures++;
            }
        }

        return $failures;
    }

    private function attempt(string $id): bool
    {
        try {
            $connection = (new AffiliateCreativeAssetCleanup)->getConnection();

            return $connection->transaction(function () use ($id, $connection): bool {
                $record = AffiliateCreativeAssetCleanup::query()->lockForUpdate()->find($id);

                if ($record === null) {
                    return true;
                }

                try {
                    $this->filesystem->removeDetached($record->media_class, $record->media_attributes, spl_object_id($connection));
                } catch (Throwable $exception) {
                    $record->increment('attempt_count');
                    report($exception);

                    return false;
                }

                $record->delete();

                return true;
            });
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
