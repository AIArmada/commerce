<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\TitleIssuerResource\Pages;

use AIArmada\FilamentPersons\Resources\TitleIssuerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListTitleIssuers extends ListRecords
{
    protected static string $resource = TitleIssuerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
