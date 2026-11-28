<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\Communications\Features\Aggregate\Models\CommunicationBatch;
use AIArmada\Communications\Models\Communication;
use RuntimeException;

final readonly class DeleteCommunicationBatchAction
{
    public function execute(CommunicationBatch $batch): bool
    {
        return $batch->getConnection()->transaction(function () use ($batch): bool {
            $locked = CommunicationBatch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();

            $locked->communications()->eachById(static function (Communication $communication): void {
                if (! $communication->delete()) {
                    throw new RuntimeException('Communication batch dependent deletion was cancelled.');
                }
            }, 100);

            if (! $locked->delete()) {
                throw new RuntimeException('Communication batch deletion was cancelled.');
            }

            return true;
        });
    }

    public function deleteDependents(CommunicationBatch $batch): void
    {
        $batch->getConnection()->transaction(function () use ($batch): void {
            $batch->communications()->eachById(static function (Communication $communication): void {
                if (! $communication->delete()) {
                    throw new RuntimeException('Communication batch dependent deletion was cancelled.');
                }
            }, 100);
        });
    }
}
