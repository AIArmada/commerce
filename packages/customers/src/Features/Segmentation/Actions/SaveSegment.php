<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Features\Segmentation\Models\Segment;

final readonly class SaveSegment
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Segment
    {
        $segment = new Segment;

        return $segment->getConnection()->transaction(fn (): Segment => $segment->newQuery()->create($attributes));
    }

    /** @param array<string, mixed> $attributes */
    public function update(Segment $segment, array $attributes): Segment
    {
        if (Segment::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Segment::class, $segment->getKey());
        }

        return $segment->getConnection()->transaction(function () use ($segment, $attributes): Segment {
            $locked = Segment::query()->whereKey($segment->getKey())->lockForUpdate()->firstOrFail();
            $locked->fill($attributes)->save();

            return $locked;
        });
    }
}
