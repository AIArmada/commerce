<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\TrackedPropertyResource\Pages;

use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\TrackedPropertyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListTrackedProperties extends ListRecords
{
    protected static string $resource = TrackedPropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
