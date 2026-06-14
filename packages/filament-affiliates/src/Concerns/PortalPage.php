<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Concerns;

use Filament\Pages\Page;

abstract class PortalPage extends Page
{
    use InteractsWithAffiliate;

    protected static bool $shouldRegisterNavigation = true;

    public static function getNavigationGroup(): ?string
    {
        return null;
    }
}
