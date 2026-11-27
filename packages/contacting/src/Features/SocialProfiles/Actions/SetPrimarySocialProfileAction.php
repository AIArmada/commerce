<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\SocialProfiles\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Contacting\Features\SocialProfiles\Models\SocialProfile;
use RuntimeException;

final class SetPrimarySocialProfileAction
{
    public function execute(SocialProfile $profile): SocialProfile
    {
        return $profile->getConnection()->transaction(function () use ($profile): SocialProfile {
            if (SocialProfile::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(SocialProfile::class, $profile->getKey());
            }

            $profile = SocialProfile::query()->whereKey($profile->getKey())->lockForUpdate()->firstOrFail();
            $profile->is_primary = true;
            if (! app(SynchronizePrimarySocialProfileAction::class)->execute($profile)) {
                throw new RuntimeException('The contact write was cancelled.');
            }

            return $profile->refresh();
        }, attempts: 3);
    }
}
