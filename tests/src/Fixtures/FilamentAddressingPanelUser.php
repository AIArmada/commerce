<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Fixtures;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

final class FilamentAddressingPanelUser extends User implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
