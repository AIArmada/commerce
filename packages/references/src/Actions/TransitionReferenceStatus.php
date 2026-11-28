<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Models\Reference;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final readonly class TransitionReferenceStatus
{
    public function handle(Reference $reference, ReferenceStatus $status): Reference
    {
        if (! $reference->exists) {
            throw new InvalidArgumentException('Status transitions require a persisted reference.');
        }

        $previous = $reference->status;
        $reference->status = $status;

        try {
            $saved = $reference->save();
        } catch (Throwable $exception) {
            $reference->status = $previous;

            throw $exception;
        }

        if (! $saved) {
            $reference->status = $previous;

            throw new RuntimeException('Reference status transition was cancelled.');
        }

        return $reference;
    }
}
