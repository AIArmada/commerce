<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\SocialProfiles\Queries;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use AIArmada\Contacting\Data\ContactSnapshotSourceData;
use AIArmada\Contacting\Features\SocialProfiles\Api\ReadsSocialProfileSnapshotSources;
use AIArmada\Contacting\Features\SocialProfiles\Models\SocialProfile;

final readonly class ReadSocialProfileSnapshotSourcesQuery implements ReadsSocialProfileSnapshotSources
{
    public function read(iterable $ids): array
    {
        $ids = is_array($ids) ? array_values($ids) : iterator_to_array($ids, false);
        if ($ids === []) {
            return [];
        }

        $config = SocialProfile::ownerScopeConfig();
        $query = SocialProfile::query();

        if ($config->enabled) {
            $owner = OwnerContext::resolveConfigured($config);
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
            $query = OwnerQuery::applyToEloquentBuilder(
                $query->withoutGlobalScope(OwnerScope::class),
                $owner,
                false,
                $config->ownerTypeColumn,
                $config->ownerIdColumn,
            );
        }

        $sources = [];

        foreach (array_chunk($ids, 100) as $batch) {
            $records = (clone $query)->whereKey($batch)->get()->keyBy('id');

            foreach ($batch as $id) {
                $source = $records->get($id);
                if (! $source instanceof SocialProfile) {
                    throw new AuthorizationException('The contact snapshot source is not accessible.');
                }

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
        }

        return $sources;
    }
}
