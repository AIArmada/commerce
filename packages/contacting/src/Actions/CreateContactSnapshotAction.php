<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Contacting\Models\ContactSnapshot;
use AIArmada\Contacting\Models\SocialProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class CreateContactSnapshotAction
{
    public function fromContactMethod(Model $snapshotable, ContactMethod $contactMethod, ?string $reason = null): ContactSnapshot
    {
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
                owner: $contactMethod->owner,
            ),
            $contactMethod->owner,
        );
    }

    public function fromSocialProfile(Model $snapshotable, SocialProfile $socialProfile, ?string $reason = null): ContactSnapshot
    {
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
                owner: $socialProfile->owner,
            ),
            $socialProfile->owner,
        );
    }

    /**
     * @param  iterable<ContactMethod>  $contactMethods
     * @param  iterable<SocialProfile>  $socialProfiles
     * @return Collection<int, ContactSnapshot>
     */
    public function fromBundle(Model $snapshotable, iterable $contactMethods, iterable $socialProfiles, ?string $reason = null): Collection
    {
        $snapshots = new Collection;

        foreach ($contactMethods as $cm) {
            $snapshots->push($this->fromContactMethod($snapshotable, $cm, $reason));
        }

        foreach ($socialProfiles as $sp) {
            $snapshots->push($this->fromSocialProfile($snapshotable, $sp, $reason));
        }

        return $snapshots;
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
        if (! (bool) config('contacting.features.contact_snapshots', true)) {
            return $snapshot;
        }

        return OwnerContext::withOwner($owner, function () use ($snapshot): ContactSnapshot {
            $snapshot->save();

            return $snapshot;
        });
    }
}
