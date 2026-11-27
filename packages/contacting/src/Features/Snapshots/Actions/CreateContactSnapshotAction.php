<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\Snapshots\Actions;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Contacting\Data\ContactSnapshotSourceData;
use AIArmada\Contacting\Features\ContactMethods\Api\ReadsContactMethodSnapshotSources;
use AIArmada\Contacting\Features\Snapshots\Exceptions\ContactSnapshotsDisabledException;
use AIArmada\Contacting\Features\Snapshots\Models\ContactSnapshot;
use AIArmada\Contacting\Features\SocialProfiles\Api\ReadsSocialProfileSnapshotSources;
use AIArmada\Contacting\Support\ContactingModelReferenceGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final readonly class CreateContactSnapshotAction
{
    public function __construct(
        private ReadsContactMethodSnapshotSources $contactMethods,
        private ReadsSocialProfileSnapshotSources $socialProfiles,
        private ContactingModelReferenceGuard $references,
    ) {}

    public function fromContactMethod(Model $snapshotable, string $contactMethodId, ?string $reason = null): ContactSnapshot
    {
        return $this->fromBundle($snapshotable, [$contactMethodId], [], $reason)->firstOrFail();
    }

    public function fromSocialProfile(Model $snapshotable, string $socialProfileId, ?string $reason = null): ContactSnapshot
    {
        return $this->fromBundle($snapshotable, [], [$socialProfileId], $reason)->firstOrFail();
    }

    /**
     * @param  iterable<string>  $contactMethodIds
     * @param  iterable<string>  $socialProfileIds
     * @return Collection<int, ContactSnapshot>
     */
    public function fromBundle(Model $snapshotable, iterable $contactMethodIds, iterable $socialProfileIds, ?string $reason = null): Collection
    {
        $this->assertSnapshotsEnabled();

        return (new ContactSnapshot)->getConnection()->transaction(function () use ($snapshotable, $contactMethodIds, $socialProfileIds, $reason): Collection {
            $target = $this->references->resolve($snapshotable->getMorphClass(), (string) $snapshotable->getKey());

            if (! $target instanceof Model) {
                throw new AuthorizationException('The snapshot target must be persisted.');
            }

            [$ownerType, $ownerId] = $this->ownerTuple($target);
            $owner = OwnerTupleParser::fromTypeAndId($ownerType, $ownerId)->toOwnerModelOrFail();
            $sources = [
                ...$this->contactMethods->read($contactMethodIds),
                ...$this->socialProfiles->read($socialProfileIds),
            ];
            $snapshots = new Collection;
            $rows = [];
            $now = Carbon::now();

            foreach ($sources as $source) {
                if ($source->ownerType !== $ownerType || $source->ownerId !== $ownerId) {
                    throw new AuthorizationException('The contact source and snapshotable must share the same owner context.');
                }

                $snapshot = $this->makeSnapshot($target, $source, $reason, $owner);
                $snapshot->id = (string) Str::uuid();
                $snapshot->created_at = $now;
                $snapshot->updated_at = $now;
                $rows[] = $snapshot->getAttributes();
                $snapshot->exists = true;
                $snapshot->wasRecentlyCreated = true;
                $snapshot->syncOriginal();
                $snapshots->push($snapshot);
            }

            foreach (array_chunk($rows, 100) as $batch) {
                ContactSnapshot::query()->insert($batch);
            }

            return $snapshots;
        });
    }

    private function makeSnapshot(Model $target, ContactSnapshotSourceData $source, ?string $reason, ?Model $owner): ContactSnapshot
    {
        $snapshot = new ContactSnapshot;
        $snapshot->snapshotable()->associate($target);
        $snapshot->snapshot_type = $source->snapshotType;
        $snapshot->source_id = $source->id;
        $snapshot->source_type = $source->type;
        $snapshot->reason = $reason;
        $snapshot->label = $source->label;
        $snapshot->channel = $source->channel;
        $snapshot->value = $source->value;
        $snapshot->normalized_value = $source->normalizedValue;
        $snapshot->url = $source->url;
        $snapshot->display_value = $source->displayValue;
        $snapshot->is_public = $source->isPublic;
        $snapshot->payload = $source->payload;

        if ($owner !== null) {
            $snapshot->assignOwner($owner);
        }

        return $snapshot;
    }

    private function assertSnapshotsEnabled(): void
    {
        if ((bool) config('contacting.features.contact_snapshots', true)) {
            return;
        }

        throw new ContactSnapshotsDisabledException(
            'Contact snapshots are disabled; enable contacting.features.contact_snapshots before creating one.',
        );
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function ownerTuple(Model $model): array
    {
        if (! method_exists($model::class, 'ownerScopeConfig')) {
            return [null, null];
        }

        $config = call_user_func([$model::class, 'ownerScopeConfig']);

        if (! $config instanceof OwnerScopeConfig) {
            return [null, null];
        }

        if (! $config->enabled) {
            return [null, null];
        }

        $type = $model->getAttribute($config->ownerTypeColumn);
        $id = $model->getAttribute($config->ownerIdColumn);

        return [
            $type === null ? null : (string) $type,
            $id === null ? null : (string) $id,
        ];
    }
}
