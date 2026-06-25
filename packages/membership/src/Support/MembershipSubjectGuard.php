<?php

declare(strict_types=1);

namespace AIArmada\Membership\Support;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class MembershipSubjectGuard
{
    public function validate(Model $subject): void
    {
        try {
            OwnerWriteGuard::findOrFailForOwner($subject::class, $subject->getKey());
        } catch (InvalidArgumentException) {
        }
    }
}
