<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use AIArmada\Contacting\Exceptions\ContactSnapshotsDisabledException;
use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Contacting\Models\ContactSnapshot;
use AIArmada\Contacting\Models\SocialProfile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class CreateContactSnapshotAction
{
    public function fromContactMethod(Model $snapshotable, ContactMethod $contactMethod, ?string $reason = null): ContactSnapshot
    {
        $this->assertSnapshotsEnabled();
        $ownerValue = $contactMethod->getRelationValue('owner');
        $owner = $ownerValue instanceof Model ? $ownerValue : null;
        $this->assertOwnerMatches($snapshotable, $owner);

        return $this->persistSnapshot(
            $this->makeSnapshot(
                snapshotable: $snapshotable,
                snapshotType: 'contact_method',
                sourceId: $contactMethod->id,
                sourceType: $contactMethod->getMorphClass(),
                reason: $reason,
                label: $contactMethod->label,
                channel: $contactMethod->type,
                value: $contactMethod->value,
                normalizedValue: $contactMethod->normalized_value,
                url: null,
                displayValue: $contactMethod->display_value,
                isPublic: $contactMethod->is_public,
                payload: $contactMethod->toArray(),
                owner: $owner,
            ),
            $owner,
        );
    }

    public function fromSocialProfile(Model $snapshotable, SocialProfile $socialProfile, ?string $reason = null): ContactSnapshot
    {
        $this->assertSnapshotsEnabled();
        $ownerValue = $socialProfile->getRelationValue('owner');
        $owner = $ownerValue instanceof Model ? $ownerValue : null;
        $this->assertOwnerMatches($snapshotable, $owner);

        return $this->persistSnapshot(
            $this->makeSnapshot(
                snapshotable: $snapshotable,
                snapshotType: 'social_profile',
                sourceId: $socialProfile->id,
                sourceType: $socialProfile->getMorphClass(),
                reason: $reason,
                label: $socialProfile->label,
                channel: $socialProfile->platform,
                value: $socialProfile->handle ?? $socialProfile->url,
                normalizedValue: $socialProfile->normalized_url,
                url: $socialProfile->url,
                displayValue: $socialProfile->display_name ?? $socialProfile->handle,
                isPublic: $socialProfile->is_public,
                payload: $socialProfile->toArray(),
                owner: $owner,
            ),
            $owner,
        );
    }

    /**
     * @param  iterable<ContactMethod>  $contactMethods
     * @param  iterable<SocialProfile>  $socialProfiles
     * @return Collection<int, ContactSnapshot>
     */
    public function fromBundle(Model $snapshotable, iterable $contactMethods, iterable $socialProfiles, ?string $reason = null): Collection
    {
        $this->assertSnapshotsEnabled();

        return DB::transaction(function () use ($contactMethods, $reason, $snapshotable, $socialProfiles): Collection {
            $snapshots = new Collection;

            foreach ($contactMethods as $cm) {
                $snapshots->push($this->fromContactMethod($snapshotable, $cm, $reason));
            }

            foreach ($socialProfiles as $sp) {
                $snapshots->push($this->fromSocialProfile($snapshotable, $sp, $reason));
            }

            return $snapshots;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function makeSnapshot(
        Model $snapshotable,
        string $snapshotType,
        string $sourceId,
        string $sourceType,
        ?string $reason,
        ?string $label,
        ?string $channel,
        ?string $value,
        ?string $normalizedValue,
        ?string $url,
        ?string $displayValue,
        bool $isPublic,
        array $payload,
        ?Model $owner,
    ): ContactSnapshot {
        $snapshot = new ContactSnapshot;
        $snapshot->snapshotable()->associate($snapshotable);
        $snapshot->snapshot_type = $snapshotType;
        $snapshot->source_id = $sourceId;
        $snapshot->source_type = $sourceType;
        $snapshot->reason = $reason;
        $snapshot->label = $label;
        $snapshot->channel = $channel;
        $snapshot->value = $value;
        $snapshot->normalized_value = $normalizedValue;
        $snapshot->url = $url;
        $snapshot->display_value = $displayValue;
        $snapshot->is_public = $isPublic;
        $snapshot->payload = $payload;

        if ($owner !== null) {
            $snapshot->assignOwner($owner);
        }

        return $snapshot;
    }

    private function persistSnapshot(ContactSnapshot $snapshot, ?Model $owner): ContactSnapshot
    {
        $this->assertSnapshotsEnabled();

        return OwnerContext::withOwner($owner, function () use ($snapshot): ContactSnapshot {
            $snapshot->save();

            return $snapshot;
        });
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

    private function assertOwnerMatches(Model $snapshotable, ?Model $sourceOwner): void
    {
        [$sourceType, $sourceId] = $sourceOwner instanceof Model
            ? [$sourceOwner->getMorphClass(), (string) $sourceOwner->getKey()]
            : [null, null];
        [$targetType, $targetId] = $this->ownerTuple($snapshotable);

        if ($sourceType === $targetType && $sourceId === $targetId) {
            return;
        }

        throw new AuthorizationException('The contact source and snapshotable must share the same owner context.');
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
