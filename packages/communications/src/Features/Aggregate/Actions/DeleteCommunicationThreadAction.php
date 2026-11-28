<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\Communications\Features\Aggregate\Models\CommunicationThread;
use AIArmada\Communications\Models\Communication;
use RuntimeException;

final readonly class DeleteCommunicationThreadAction
{
    public function execute(CommunicationThread $thread): bool
    {
        return $thread->getConnection()->transaction(function () use ($thread): bool {
            $locked = CommunicationThread::query()->whereKey($thread->getKey())->lockForUpdate()->firstOrFail();

            $locked->communications()->eachById(static function (Communication $communication): void {
                if (! $communication->delete()) {
                    throw new RuntimeException('Communication thread dependent deletion was cancelled.');
                }
            }, 100);

            if (! $locked->delete()) {
                throw new RuntimeException('Communication thread deletion was cancelled.');
            }

            return true;
        });
    }

    public function deleteDependents(CommunicationThread $thread): void
    {
        $thread->getConnection()->transaction(function () use ($thread): void {
            $thread->communications()->eachById(static function (Communication $communication): void {
                if (! $communication->delete()) {
                    throw new RuntimeException('Communication thread dependent deletion was cancelled.');
                }
            }, 100);
        });
    }
}
