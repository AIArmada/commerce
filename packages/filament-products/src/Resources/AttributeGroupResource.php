<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use AIArmada\FilamentProducts\Resources\AttributeGroupResource\Pages;
use AIArmada\FilamentProducts\Resources\AttributeGroupResource\Schemas\AttributeGroupForm;
use AIArmada\FilamentProducts\Resources\AttributeGroupResource\Tables\AttributeGroupsTable;
use AIArmada\Products\Models\AttributeGroup;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class AttributeGroupResource extends BaseAttributeResource
{
    protected static ?string $model = AttributeGroup::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationParentItem = 'Attributes';

    protected static function navigationSortKey(): string
    {
        return 'attribute_groups';
    }

    /**
     * @return Builder<AttributeGroup>
     */
    public static function getEloquentQuery(): Builder
    {
        return AttributeGroup::query()
            ->forOwner();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-products::resources.attribute_groups.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament-products::resources.attribute_groups.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-products::resources.attribute_groups.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return AttributeGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttributeGroupsTable::configure($table);
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
            'index' => Pages\ListAttributeGroups::route('/'),
            'create' => Pages\CreateAttributeGroup::route('/create'),
            'edit' => Pages\EditAttributeGroup::route('/{record}/edit'),
        ];
    }
}
