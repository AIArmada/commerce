<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Models\Reference;
use InvalidArgumentException;
use RuntimeException;

final readonly class TransitionReferenceStatus
{
    public function __construct(private LockReferenceForWrite $lock = new LockReferenceForWrite) {}

    public function handle(Reference $reference, ReferenceStatus $status): Reference
    {
        if (! $reference->exists) {
            throw new InvalidArgumentException('Status transitions require a persisted reference.');
        }

        $reference->getConnection()->transaction(function () use ($reference, $status): void {
            $locked = $this->lock->handle($reference);
            $locked->status = $status;

            if (! $locked->saveOrFail()) {
                throw new RuntimeException('Reference status transition was cancelled.');
            }
        });

        return $reference->refresh();
    }
}
