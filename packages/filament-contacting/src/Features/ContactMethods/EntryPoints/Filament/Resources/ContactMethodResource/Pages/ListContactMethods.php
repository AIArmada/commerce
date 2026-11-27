<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Features\ContactMethods\EntryPoints\Filament\Resources\ContactMethodResource\Pages;

use AIArmada\FilamentContacting\Features\ContactMethods\EntryPoints\Filament\Imports\ContactMethodImporter;
use AIArmada\FilamentContacting\Features\ContactMethods\EntryPoints\Filament\Resources\ContactMethodResource;
use AIArmada\FilamentContacting\Support\ContactingFilamentConfig;
use AIArmada\FilamentContacting\Support\GuardsContactingUi;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

final class ListContactMethods extends ListRecords
{
    protected static string $resource = ContactMethodResource::class;

    /**
     * @return array<int, ImportAction>
     */
    protected function getHeaderActions(): array
    {
        if (! app(ContactingFilamentConfig::class)->importsEnabled()
            || app(GuardsContactingUi::class)->contactMethodsReadOnly()) {
            return [];
        }

        return [
            ImportAction::make()
                ->importer(ContactMethodImporter::class)
                ->label('Import Contact Methods')
                ->visible(fn (): bool => ContactMethodResource::canCreate())
                ->authorize(fn (): bool => ContactMethodResource::canCreate()),
        ];
    }
}
