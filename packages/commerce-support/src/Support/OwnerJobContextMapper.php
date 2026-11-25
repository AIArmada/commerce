<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Data\OwnerJobContext;
use Illuminate\Database\Eloquent\Model;

final class OwnerJobContextMapper
{
    public static function fromModel(Model $owner): OwnerJobContext
    {
        return new OwnerJobContext($owner->getMorphClass(), $owner->getKey());
    }

    public static function resolve(?OwnerJobContext $context): ?Model
    {
        if ($context === null || $context->ownerType === null || $context->ownerId === null) {
            return null;
        }

        return OwnerContext::fromTypeAndIdOrFail($context->ownerType, $context->ownerId);
    }
}
