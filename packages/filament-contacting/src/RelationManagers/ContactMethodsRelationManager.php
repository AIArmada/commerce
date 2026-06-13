<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\RelationManagers;

use AIArmada\FilamentContacting\Schemas\ContactMethodFormSchema;
use AIArmada\FilamentContacting\Support\GuardsContactingUi;
use AIArmada\FilamentContacting\Tables\ContactMethodTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

final class ContactMethodsRelationManager extends RelationManager
{
    protected static string $relationship = 'contactMethods';

    protected static ?string $title = 'Contact Methods';

    protected static ?string $recordTitleAttribute = 'value';

    public function table(Table $table): Table
    {
        $guard = app(GuardsContactingUi::class);

        $tableConfig = ContactMethodTable::table($table);

        $tableConfig
            ->headerActions([
                CreateAction::make()
                    ->form(ContactMethodFormSchema::make())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['country_code'] = isset($data['country_code'])
                            ? mb_strtoupper((string) $data['country_code'])
                            : null;

                        return $data;
                    })
                    ->visible(fn (): bool => ! $guard->contactMethodsReadOnly()),
            ]);

        return $tableConfig;
    }
}
