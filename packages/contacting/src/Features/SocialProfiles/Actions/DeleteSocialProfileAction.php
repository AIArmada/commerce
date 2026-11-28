<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\SocialProfiles\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Contacting\Features\SocialProfiles\Models\SocialProfile;

final readonly class DeleteSocialProfileAction
{
    public function execute(SocialProfile $record): bool
    {
        return $record->getConnection()->transaction(function () use ($record): bool {
            if (SocialProfile::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(SocialProfile::class, $record->getKey());
            }

            return (bool) SocialProfile::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail()->delete();
        });
    }
}
