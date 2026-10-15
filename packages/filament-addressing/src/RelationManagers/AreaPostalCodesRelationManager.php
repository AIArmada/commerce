<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class AreaPostalCodesRelationManager extends RelationManager
{
    protected static string $relationship = 'postalCodeCoverage';

    protected static ?string $title = 'Postcode Coverage';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('postalCode.code')
            ->columns([
                TextColumn::make('postalCode.code')->label('Postcode')->searchable()->sortable(),
                TextColumn::make('postalCode.country_code')->label('Country')->badge(),
                TextColumn::make('relationship_type')->label('Relationship')->badge(),
                IconColumn::make('is_primary')->label('Primary')->boolean(),
                TextColumn::make('source')->label('Source')->badge(),
                TextColumn::make('source_id')->label('Source ID')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('postalCode.code')
            ->paginated([10, 25, 50]);
    }
}
