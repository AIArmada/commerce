<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

final class Dashboard extends BaseDashboard
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::Home;

    protected static ?int $navigationSort = -2;

    public function getTitle(): string
    {
        return 'Commerce Demo Dashboard';
    }

    public function getHeading(): string
    {
        return 'Commerce Demo Dashboard';
    }

    public function getSubheading(): ?string
    {
        return 'A fully functional demo showcasing all AIArmada Commerce packages';
    }
}
