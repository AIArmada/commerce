<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\SocialProfiles\Queries;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Contacting\Data\ContactSnapshotSourceData;
use AIArmada\Contacting\Features\SocialProfiles\Api\ReadsSocialProfileSnapshotSources;
use AIArmada\Contacting\Features\SocialProfiles\Models\SocialProfile;

final readonly class ReadSocialProfileSnapshotSourcesQuery implements ReadsSocialProfileSnapshotSources
{
    public function read(iterable $ids): array
    {
        $sources = [];

        foreach ($ids as $id) {
            $source = SocialProfile::ownerScopeConfig()->enabled
                ? OwnerWriteGuard::findOrFailForOwner(SocialProfile::class, $id)
                : SocialProfile::query()->whereKey($id)->firstOrFail();

            $sources[] = new ContactSnapshotSourceData(
                id: $source->id,
                type: $source->getMorphClass(),
                snapshotType: 'social_profile',
                ownerType: $source->owner_type,
                ownerId: $source->owner_id,
                label: $source->label,
                channel: $source->platform,
                value: $source->handle ?? $source->url,
                normalizedValue: $source->normalized_url,
                url: $source->url,
                displayValue: $source->display_name ?? $source->handle,
                isPublic: $source->is_public,
                payload: $source->attributesToArray(),
            );
        }

        return $sources;
    }
}
