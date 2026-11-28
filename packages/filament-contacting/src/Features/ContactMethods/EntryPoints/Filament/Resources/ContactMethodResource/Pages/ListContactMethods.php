<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Features\ContactMethods\EntryPoints\Filament\Resources\ContactMethodResource\Pages;

use AIArmada\Contacting\Features\ContactMethods\Models\ContactMethod;
use AIArmada\FilamentContacting\Features\ContactMethods\EntryPoints\Filament\Imports\ContactMethodImporter;
use AIArmada\FilamentContacting\Features\ContactMethods\EntryPoints\Filament\Resources\ContactMethodResource;
use AIArmada\FilamentContacting\Support\ContactingFilamentConfig;
use AIArmada\FilamentContacting\Support\GuardsContactingUi;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

final class ListContactMethods extends ListRecords
{
    protected static string $resource = ContactMethodResource::class;

    /**
     * @return array<int, ImportAction>
     */
    protected function getHeaderActions(): array
    {
        if (! self::canImportContactMethods()) {
            return [];
        }

        return [
            ImportAction::make()
                ->importer(ContactMethodImporter::class)
                ->label('Import Contact Methods')
                ->visible(static fn (): bool => self::canImportContactMethods())
                ->authorize(static fn (): bool => self::canAuthorizeContactMethodImport()),
        ];
    }

    private static function canImportContactMethods(): bool
    {
        return app(ContactingFilamentConfig::class)->importsEnabled()
            && ! app(GuardsContactingUi::class)->contactMethodsReadOnly();
    }

    private static function canAuthorizeContactMethodImport(): bool
    {
        if (! self::canImportContactMethods()) {
            return false;
        }

        return Gate::getPolicyFor(ContactMethod::class) === null
            || Gate::allows('create', ContactMethod::class);
    }
}
