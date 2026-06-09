<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\FilamentProducts\Resources\ProductResource\Pages;
use AIArmada\FilamentProducts\Resources\ProductResource\RelationManagers;
use AIArmada\FilamentProducts\Resources\ProductResource\Schemas\ProductForm;
use AIArmada\FilamentProducts\Resources\ProductResource\Schemas\ProductInfolist;
use AIArmada\FilamentProducts\Resources\ProductResource\Tables\ProductsTable;
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;
use AIArmada\Pricing\Data\PriceResultData;
use AIArmada\Pricing\Models\Price;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class ProductResource extends BaseProductResource
{
    protected static ?string $model = Product::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cube';

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-products.navigation.resources.products', 1);
    }

    /**
     * @return Builder<Product>
     */
    public static function getEloquentQuery(): Builder
    {
        return Product::query()
            ->forOwner();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', ProductStatus::Active)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canViewAny(): bool
    {
        return FilamentPermission::hasAbility('product.viewAny');
    }

    public static function canView(Model $record): bool
    {
        return FilamentPermission::hasAbility('product.view');
    }

    public static function canCreate(): bool
    {
        return FilamentPermission::hasAbility('product.create');
    }

    public static function canEdit(Model $record): bool
    {
        return FilamentPermission::hasAbility('product.update');
    }

    public static function canDelete(Model $record): bool
    {
        return FilamentPermission::hasAbility('product.delete');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductInfolist::configure($schema);
    }

    public static function calculatePriceResult(?Product $record, ?string $customerId): ?PriceResultData
    {
        if (! $record instanceof Product) {
            return null;
        }

        try {
            $calculator = app(PriceCalculatorInterface::class);
            $context = ['currency' => $record->currency];

            if (is_string($customerId) && $customerId !== '') {
                $context['customer_id'] = $customerId;
            }

            return $calculator->calculate($record, 1, $context);
        } catch (Throwable) {
            return null;
        }
    }

    public static function formatCurrency(int $amountMinor, ?string $currency): string
    {
        return MoneyFormatter::formatMinor($amountMinor, $currency ?? 'MYR');
    }

    public static function getRelations(): array
    {
        $relations = [
            RelationManagers\VariantsRelationManager::class,
            RelationManagers\OptionsRelationManager::class,
        ];

        if (class_exists(Price::class)) {
            $relations[] = RelationManagers\PricesRelationManager::class;
        }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
