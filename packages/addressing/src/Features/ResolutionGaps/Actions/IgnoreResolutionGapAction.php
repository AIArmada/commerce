<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\ResolutionGaps\Actions;

use AIArmada\Addressing\Models\ResolutionGap;
use AIArmada\Addressing\Support\ModelResolver;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;

final readonly class IgnoreResolutionGapAction
{
    /**
     * Runs inside the caller's owner scope; model guards reject writes
     * from any other context.
     */
    public function execute(ResolutionGap $gap): ResolutionGap
    {
        return $gap->getConnection()->transaction(function () use ($gap): ResolutionGap {
            $gapClass = ModelResolver::resolutionGapClass();
            OwnerWriteGuard::findOrFailForOwner($gapClass, $gap->getKey());
            $locked = $gapClass::query()->whereKey($gap->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === 'ignored') {
                return $locked;
            }

            $locked->status = 'ignored';
            $locked->save();

            return $locked;
        });
    }
}
