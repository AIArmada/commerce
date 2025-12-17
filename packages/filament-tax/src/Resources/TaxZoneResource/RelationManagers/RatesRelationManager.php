<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxZoneResource\RelationManagers;

use AIArmada\FilamentTax\Resources\TaxZoneResource\RelationManagers\Schemas\RatesForm;
use AIArmada\FilamentTax\Resources\TaxZoneResource\RelationManagers\Tables\RatesTable;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Support\TaxOwnerScope;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class RatesRelationManager extends RelationManager
{
    protected static string $relationship = 'rates';

    protected static ?string $title = 'Tax Rates';

    public function form(Schema $schema): Schema
    {
        return RatesForm::configure($schema);
    }

    /**
     * @return Builder<TaxRate>
     */
    protected function getTableQuery(): Builder
    {
        /** @var Builder<TaxRate> $query */
        $query = parent::getTableQuery();

        return TaxOwnerScope::applyToOwnedQuery($query);
    }

    public function table(Table $table): Table
    {
        return RatesTable::configure($table);
    }
}
