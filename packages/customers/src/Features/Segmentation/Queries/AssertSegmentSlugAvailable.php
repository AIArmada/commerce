<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Queries;

use AIArmada\Customers\Features\Segmentation\Models\Segment;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class AssertSegmentSlugAvailable
{
    public function execute(Segment $segment): void
    {
        $slug = $segment->getAttribute('slug');

        if (! is_string($slug) || mb_trim($slug) === '') {
            return;
        }

        $ownerType = $segment->getAttribute('owner_type');
        $ownerId = $segment->getAttribute('owner_id');

        if (($ownerType === null) !== ($ownerId === null)) {
            throw new InvalidArgumentException('Owner type and owner id must both be present or both be null.');
        }

        $query = $segment->newQuery()
            ->withoutOwnerScope()
            ->where('slug', $slug);

        if ($ownerType === null) {
            $query->whereNull('owner_type')->whereNull('owner_id');
        } else {
            $query->where('owner_type', $ownerType)->where('owner_id', $ownerId);
        }

        if ($segment->exists) {
            $query->whereKeyNot($segment->getKey());
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'The segment slug has already been taken within this owner scope.',
            ]);
        }
    }
}
