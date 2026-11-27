<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;

final class ChipOwnerAccess
{
    public static function hasContext(): bool
    {
        return ! (bool) config('chip.owner.enabled', false)
            || OwnerContext::resolve() !== null
            || OwnerContext::isExplicitGlobal();
    }
}
