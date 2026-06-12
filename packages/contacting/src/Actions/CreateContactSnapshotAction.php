<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Contacting\Models\ContactSnapshot;
use AIArmada\Contacting\Models\SocialProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class CreateContactSnapshotAction
{
    public function fromContactMethod(Model $snapshotable, ContactMethod $contactMethod, ?string $reason = null): ContactSnapshot
    {
        $snapshot = new ContactSnapshot;
        $snapshot->snapshotable()->associate($snapshotable);
        $snapshot->snapshot_type = 'contact_method';
        $snapshot->source_id = $contactMethod->id;
        $snapshot->source_type = $contactMethod->getMorphClass();
        $snapshot->reason = $reason;
        $snapshot->label = $contactMethod->label;
        $snapshot->channel = $contactMethod->type;
        $snapshot->value = $contactMethod->value;
        $snapshot->normalized_value = $contactMethod->normalized_value;
        $snapshot->display_value = $contactMethod->display_value;
        $snapshot->is_public = $contactMethod->is_public;
        $snapshot->payload = $contactMethod->toArray();

        $snapshot->save();

        return $snapshot;
    }

    public function fromSocialProfile(Model $snapshotable, SocialProfile $socialProfile, ?string $reason = null): ContactSnapshot
    {
        $snapshot = new ContactSnapshot;
        $snapshot->snapshotable()->associate($snapshotable);
        $snapshot->snapshot_type = 'social_profile';
        $snapshot->source_id = $socialProfile->id;
        $snapshot->source_type = $socialProfile->getMorphClass();
        $snapshot->reason = $reason;
        $snapshot->label = $socialProfile->label;
        $snapshot->channel = $socialProfile->platform;
        $snapshot->value = $socialProfile->handle ?? $socialProfile->url;
        $snapshot->normalized_value = $socialProfile->normalized_url;
        $snapshot->url = $socialProfile->url;
        $snapshot->display_value = $socialProfile->display_name ?? $socialProfile->handle;
        $snapshot->is_public = $socialProfile->is_public;
        $snapshot->payload = $socialProfile->toArray();

        $snapshot->save();

        return $snapshot;
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
}
