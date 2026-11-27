<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets\Concerns;

use AIArmada\FilamentChip\Support\ChipOwnerAccess;

trait RequiresChipOwnerContext
{
    public static function canView(): bool
    {
        return ChipOwnerAccess::hasContext() && parent::canView();
    }
}
