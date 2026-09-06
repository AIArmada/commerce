<?php

declare(strict_types=1);

namespace AIArmada\Membership\Support;

use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Database\Eloquent\Model;

final class MembershipSubjectGuard
{
    public function validate(Model $subject): void
    {
        if (! method_exists($subject::class, 'ownerScopeConfig') && ! method_exists($subject::class, 'scopeForOwner')) {
            return;
        }

        if (method_exists($subject::class, 'ownerScopeConfig')) {
            /** @var callable(): OwnerScopeConfig $resolveOwnerScopeConfig */
            $resolveOwnerScopeConfig = [$subject::class, 'ownerScopeConfig'];
            $config = $resolveOwnerScopeConfig();

            if (! $config->enabled) {
                return;
            }
        }

        OwnerWriteGuard::findOrFailForOwner($subject::class, $subject->getKey());
    }
}
