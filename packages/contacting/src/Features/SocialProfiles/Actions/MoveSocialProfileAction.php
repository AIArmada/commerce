<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\SocialProfiles\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Contacting\Support\ContactingModelReferenceGuard;
use AIArmada\Contacting\Features\SocialProfiles\Models\SocialProfile;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final readonly class MoveSocialProfileAction
{
    public function __construct(
        private SynchronizePrimarySocialProfileAction $persist,
        private ContactingModelReferenceGuard $references,
    ) {}

    public function execute(SocialProfile $record, Model $target): SocialProfile
    {
        return $record->getConnection()->transaction(function () use ($record, $target): SocialProfile {
            if (SocialProfile::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(SocialProfile::class, $record->getKey());
            }

            $target = $this->references->resolve($target->getMorphClass(), (string) $target->getKey());
            if (! $target instanceof Model) {
                throw new RuntimeException('Contact target must exist.');
            }

            $target->newQuery()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $record = SocialProfile::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ($record->is_primary && SocialProfile::query()
                ->where('socialable_type', $target->getMorphClass())
                ->where('socialable_id', $target->getKey())
                ->where('platform', $record->platform)
                ->where('purpose', $record->purpose)
                ->whereKeyNot($record->getKey())
                ->where('is_primary', true)
                ->exists()) {
                $record->is_primary = false;
            }

            $record->socialable()->associate($target);
            if (! $this->persist->execute($record)) {
                throw new RuntimeException('Contact move was cancelled.');
            }

            return $record;
        });
    }
}
