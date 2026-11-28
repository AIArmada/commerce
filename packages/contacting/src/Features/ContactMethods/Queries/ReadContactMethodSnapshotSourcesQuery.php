<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\ContactMethods\Queries;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Contacting\Data\ContactSnapshotSourceData;
use AIArmada\Contacting\Features\ContactMethods\Api\ReadsContactMethodSnapshotSources;
use AIArmada\Contacting\Features\ContactMethods\Models\ContactMethod;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class ReadContactMethodSnapshotSourcesQuery implements ReadsContactMethodSnapshotSources
{
    public function read(iterable $ids): array
    {
        $ids = is_array($ids) ? array_values($ids) : iterator_to_array($ids, false);
        if ($ids === []) {
            return [];
        }

        $config = ContactMethod::ownerScopeConfig();
        $query = ContactMethod::query();

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
                if (! $source instanceof ContactMethod) {
                    throw new AuthorizationException('The contact snapshot source is not accessible.');
                }

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
        }

        return $sources;
    }
}
