<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources;

use AIArmada\FilamentTax\Resources\TaxClassResource\Pages;
use AIArmada\FilamentTax\Resources\TaxClassResource\Schemas\TaxClassForm;
use AIArmada\FilamentTax\Resources\TaxClassResource\Tables\TaxClassesTable;
use AIArmada\Tax\Models\TaxClass;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

final class TaxClassResource extends Resource
{
    protected static ?string $model = TaxClass::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    protected static string | UnitEnum | null $navigationGroup = 'Tax';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TaxClassForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxClassesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxClasses::route('/'),
            'create' => Pages\CreateTaxClass::route('/create'),
            'edit' => Pages\EditTaxClass::route('/{record}/edit'),
        ];
    }
}
