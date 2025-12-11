<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources;

use AIArmada\FilamentTax\Resources\TaxRateResource\Pages;
use AIArmada\FilamentTax\Resources\TaxRateResource\Schemas\TaxRateForm;
use AIArmada\FilamentTax\Resources\TaxRateResource\Tables\TaxRatesTable;
use AIArmada\Tax\Models\TaxRate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

final class TaxRateResource extends Resource
{
    protected static ?string $model = TaxRate::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-calculator';

    protected static string | UnitEnum | null $navigationGroup = 'Tax';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TaxRateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxRatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxRates::route('/'),
            'create' => Pages\CreateTaxRate::route('/create'),
            'view' => Pages\ViewTaxRate::route('/{record}'),
            'edit' => Pages\EditTaxRate::route('/{record}/edit'),
        ];
    }
}
