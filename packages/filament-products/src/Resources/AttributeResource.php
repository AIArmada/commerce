<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use AIArmada\FilamentProducts\Resources\AttributeResource\Pages;
use AIArmada\FilamentProducts\Resources\AttributeResource\Schemas\AttributeForm;
use AIArmada\FilamentProducts\Resources\AttributeResource\Tables\AttributesTable;
use AIArmada\Products\Models\Attribute;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class AttributeResource extends BaseAttributeResource
{
    protected static ?string $model = Attribute::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static function navigationSortKey(): string
    {
        return 'attributes';
    }

    /**
     * @return Builder<Attribute>
     */
    public static function getEloquentQuery(): Builder
    {
        return Attribute::query()
            ->forOwner();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-products::resources.attributes.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament-products::resources.attributes.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-products::resources.attributes.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return AttributeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttributesTable::configure($table);
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
            'index' => Pages\ListAttributes::route('/'),
            'create' => Pages\CreateAttribute::route('/create'),
            'edit' => Pages\EditAttribute::route('/{record}/edit'),
        ];
    }
}
