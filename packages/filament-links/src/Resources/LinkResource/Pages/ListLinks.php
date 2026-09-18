<?php

declare(strict_types=1);

namespace AIArmada\FilamentLinks\Resources\LinkResource\Pages;

use AIArmada\FilamentLinks\Resources\LinkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListLinks extends ListRecords
{
    protected static string $resource = LinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
