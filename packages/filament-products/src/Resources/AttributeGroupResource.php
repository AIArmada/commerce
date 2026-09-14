<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentProducts\Resources\AttributeGroupResource\Pages;
use AIArmada\FilamentProducts\Resources\AttributeGroupResource\Schemas\AttributeGroupForm;
use AIArmada\FilamentProducts\Resources\AttributeGroupResource\Tables\AttributeGroupsTable;
use AIArmada\Products\Models\AttributeGroup;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
        return parent::getEloquentQuery();
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

    public static function canViewAny(): bool
    {
        return FilamentPermission::hasAbility('attribute-group.viewAny');
    }

    public static function canView(Model $record): bool
    {
        return FilamentPermission::hasAbility('attribute-group.view');
    }

    public static function canCreate(): bool
    {
        return FilamentPermission::hasAbility('attribute-group.create');
    }

    public static function canEdit(Model $record): bool
    {
        return FilamentPermission::hasAbility('attribute-group.update');
    }

    public static function canDelete(Model $record): bool
    {
        return FilamentPermission::hasAbility('attribute-group.delete');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
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
