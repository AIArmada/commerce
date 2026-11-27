<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Features\Segmentation\Models\Segment;
use AIArmada\Customers\Features\Segmentation\Queries\AssertSegmentSlugAvailable;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class SaveSegment
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Segment
    {
        $segment = new Segment;
        $segment->fill($attributes);
        $this->persist($segment);

        return $segment;
    }

    /** @param array<string, mixed> $attributes */
    public function update(Segment $segment, array $attributes): Segment
    {
        if (Segment::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Segment::class, $segment->getKey());
        }

        return $segment->getConnection()->transaction(function () use ($segment, $attributes): Segment {
            $locked = Segment::query()->whereKey($segment->getKey())->lockForUpdate()->firstOrFail();
            $locked->fill($attributes);
            $this->persist($locked);

            return $locked;
        });
    }

    private function persist(Segment $segment): void
    {
        try {
            $segment->getConnection()->transaction(fn (): bool => $segment->save());
        } catch (UniqueConstraintViolationException $exception) {
            app(AssertSegmentSlugAvailable::class)->execute($segment);

            throw $exception;
        }
    }
}
