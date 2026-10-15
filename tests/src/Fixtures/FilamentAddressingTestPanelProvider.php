<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Fixtures;

use AIArmada\Commerce\Tests\TestPanelProvider;
use AIArmada\FilamentAddressing\FilamentAddressingPlugin;
use Filament\Panel;

final class FilamentAddressingTestPanelProvider extends TestPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return parent::panel($panel)
            ->plugin(FilamentAddressingPlugin::make());
    }
}
