<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxZoneResource\RelationManagers;

use AIArmada\FilamentTax\Resources\TaxZoneResource\RelationManagers\Schemas\RatesForm;
use AIArmada\FilamentTax\Resources\TaxZoneResource\RelationManagers\Tables\RatesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

final class RatesRelationManager extends RelationManager
{
    protected static string $relationship = 'rates';

    protected static ?string $title = 'Tax Rates';

    public function form(Schema $schema): Schema
    {
        return RatesForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return RatesTable::configure($table);
    }
}
