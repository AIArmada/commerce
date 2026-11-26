<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\ResolutionGaps\Actions;

use AIArmada\Addressing\Models\ResolutionGap;
use AIArmada\Addressing\Support\ModelResolver;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use RuntimeException;

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
            if ($gapClass::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner($gapClass, $gap->getKey());
            }
            $locked = $gapClass::query()->whereKey($gap->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === 'ignored') {
                return $locked;
            }

            $locked->status = 'ignored';
            if (! $locked->save()) {
                throw new RuntimeException('The resolution gap could not be ignored.');
            }

            return $locked;
        });
    }
}
