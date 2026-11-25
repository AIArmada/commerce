<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\CreatorPostbackResource\Pages;

use AIArmada\FilamentAffiliateNetwork\Resources\CreatorPostbackResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListCreatorPostbacks extends ListRecords
{
    protected static string $resource = CreatorPostbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
