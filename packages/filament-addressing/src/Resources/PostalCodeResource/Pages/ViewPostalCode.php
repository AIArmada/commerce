<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages;

use AIArmada\Addressing\Models\PostalCode;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewPostalCode extends ViewRecord
{
    protected static string $resource = PostalCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (PostalCode $record): bool => ! PostalCodeResource::isReadOnly()
                    && PostalCodeResource::canEdit($record))
                ->authorize(fn (PostalCode $record): bool => ! PostalCodeResource::isReadOnly()
                    && PostalCodeResource::canEdit($record)),
        ];
    }
}
