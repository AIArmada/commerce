<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\SocialProfiles\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Contacting\Features\SocialProfiles\Models\SocialProfile;

final class SetPrimarySocialProfileAction
{
    public function execute(SocialProfile $profile): SocialProfile
    {
        $profile = SocialProfile::ownerScopeConfig()->enabled
            ? OwnerWriteGuard::findOrFailForOwner(SocialProfile::class, $profile->getKey())
            : SocialProfile::query()->whereKey($profile->getKey())->firstOrFail();

        $profile->getConnection()->transaction(function () use ($profile): void {
            $profile->is_primary = true;
            if (! $profile->save()) {
                throw new \RuntimeException('The contact write was cancelled.');
            }
        });

        return $profile->refresh();
    }
}
