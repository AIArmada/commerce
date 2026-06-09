<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Concerns;

use Filament\Pages\Page;
use UnitEnum;

abstract class PortalPage extends Page
{
    use InteractsWithAffiliate;

    protected static bool $shouldRegisterNavigation = true;

    protected static string | UnitEnum | null $navigationGroup = 'Affiliate Portal';
}
