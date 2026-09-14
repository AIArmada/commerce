<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxZoneResource\RelationManagers;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentTax\Resources\TaxZoneResource\RelationManagers\RatesRelationManager\Schemas\RatesForm;
use AIArmada\FilamentTax\Resources\TaxZoneResource\RelationManagers\RatesRelationManager\Tables\RatesTable;
use AIArmada\Tax\Models\TaxRate;
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
     * Owner-scoped like the rates resource table so foreign-tenant rates
     * neither render nor resolve for record actions.
     *
     * @return Builder<TaxRate>
     */
    protected function getTableQuery(): Builder
    {
        /** @phpstan-ignore return.type (template type not preserved through helper) */
        return OwnerUiScope::apply(parent::getTableQuery(), includeGlobal: false);
    }

    public function table(Table $table): Table
    {
        return RatesTable::configure($table);
    }
}
