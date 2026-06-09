<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use AIArmada\FilamentProducts\Resources\AttributeSetResource\Pages;
use AIArmada\FilamentProducts\Resources\AttributeSetResource\Schemas\AttributeSetForm;
use AIArmada\FilamentProducts\Resources\AttributeSetResource\Tables\AttributeSetsTable;
use AIArmada\Products\Models\AttributeSet;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class AttributeSetResource extends BaseAttributeResource
{
    protected static ?string $model = AttributeSet::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationParentItem = 'Attributes';

    protected static function navigationSortKey(): string
    {
        return 'attribute_sets';
    }

    /**
     * @return Builder<AttributeSet>
     */
    public static function getEloquentQuery(): Builder
    {
        return AttributeSet::query()
            ->forOwner();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-products::resources.attribute_sets.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament-products::resources.attribute_sets.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-products::resources.attribute_sets.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return AttributeSetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttributeSetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttributeSets::route('/'),
            'create' => Pages\CreateAttributeSet::route('/create'),
            'edit' => Pages\EditAttributeSet::route('/{record}/edit'),
        ];
    }
}
