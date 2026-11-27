<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\ContactMethods\Queries;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Contacting\Data\ContactSnapshotSourceData;
use AIArmada\Contacting\Features\ContactMethods\Api\ReadsContactMethodSnapshotSources;
use AIArmada\Contacting\Features\ContactMethods\Models\ContactMethod;

final readonly class ReadContactMethodSnapshotSourcesQuery implements ReadsContactMethodSnapshotSources
{
    public function read(iterable $ids): array
    {
        $sources = [];

        foreach ($ids as $id) {
            $source = ContactMethod::ownerScopeConfig()->enabled
                ? OwnerWriteGuard::findOrFailForOwner(ContactMethod::class, $id)
                : ContactMethod::query()->whereKey($id)->firstOrFail();

            $sources[] = new ContactSnapshotSourceData(
                id: $source->id,
                type: $source->getMorphClass(),
                snapshotType: 'contact_method',
                ownerType: $source->owner_type,
                ownerId: $source->owner_id,
                label: $source->label,
                channel: $source->type,
                value: $source->value,
                normalizedValue: $source->normalized_value,
                url: null,
                displayValue: $source->display_value,
                isPublic: $source->is_public,
                payload: $source->attributesToArray(),
            );
        }

        return $sources;
    }
}
