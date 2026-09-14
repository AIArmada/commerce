<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentProducts\Resources\CategoryResource\Pages;
use AIArmada\FilamentProducts\Resources\CategoryResource\Schemas\CategoryForm;
use AIArmada\FilamentProducts\Resources\CategoryResource\Schemas\CategoryInfolist;
use AIArmada\FilamentProducts\Resources\CategoryResource\Tables\CategoriesTable;
use AIArmada\Products\Models\Category;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CategoryResource extends BaseCatalogResource
{
    protected static ?string $model = Category::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-folder';

    protected static function navigationSortKey(): string
    {
        return 'categories';
    }

    /**
     * @return Builder<Category>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['products', 'children']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = (int) OwnerCache::remember(
            OwnerContext::resolve(),
            'filament-products.nav-badge.categories',
            CarbonImmutable::now()->addSeconds(30),
            function (): int {
                return (int) self::getEloquentQuery()->count();
            }
        );

        return $count > 0 ? (string) $count : null;
    }

    public static function canViewAny(): bool
    {
        return FilamentPermission::hasAbility('category.viewAny');
    }

    public static function canView(Model $record): bool
    {
        return FilamentPermission::hasAbility('category.view');
    }

    public static function canCreate(): bool
    {
        return FilamentPermission::hasAbility('category.create');
    }

    public static function canEdit(Model $record): bool
    {
        return FilamentPermission::hasAbility('category.update');
    }

    public static function canDelete(Model $record): bool
    {
        return FilamentPermission::hasAbility('category.delete');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return CategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CategoryInfolist::configure($schema);
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'view' => Pages\ViewCategory::route('/{record}'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
