<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Features\Segmentation\Models\Segment;
use AIArmada\Customers\Features\Segmentation\Queries\AssertSegmentSlugAvailable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

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

    public function delete(Segment $segment): bool
    {
        if (! $segment->exists || $segment->getKey() === null) {
            throw new InvalidArgumentException('Segment must be persisted.');
        }

        return $segment->getConnection()->transaction(function () use ($segment): bool {
            if (Segment::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Segment::class, $segment->getKey());
            }

            $locked = $segment->newQuery()->whereKey($segment->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->delete()) {
                throw new RuntimeException('Segment deletion was cancelled.');
            }

            return true;
        });
    }

    private function persist(Segment $segment): void
    {
        try {
            $segment->getConnection()->transaction(function () use ($segment): void {
                if (! $segment->save()) {
                    throw new RuntimeException('Segment persistence was cancelled.');
                }
            });
        } catch (UniqueConstraintViolationException $exception) {
            $detail = (string) ($exception->errorInfo[2] ?? '');
            $table = $segment->getTable();

            if (str_contains($detail, 'customers_segments_owner_slug_unique')
                || str_contains($detail, "UNIQUE constraint failed: {$table}.owner_scope, {$table}.slug")) {
                throw ValidationException::withMessages([
                    'slug' => 'The segment slug has already been taken within this owner scope.',
                ]);
            }

            app(AssertSegmentSlugAvailable::class)->execute($segment);

            throw $exception;
        }
    }
}
