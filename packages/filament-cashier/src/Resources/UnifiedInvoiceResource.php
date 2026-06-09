<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources;

use AIArmada\Chip\Models\Purchase;
use AIArmada\FilamentCashier\FilamentCashierPlugin;
use AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource\Pages;
use AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource\Tables\InvoicesTable;
use BackedEnum;
use Closure;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

final class UnifiedInvoiceResource extends Resource
{
    public static function getModel(): string
    {
        if (class_exists(Purchase::class)) {
            return Purchase::class;
        }

        $userModel = config('auth.providers.users.model');

        if (is_string($userModel)) {
            return $userModel;
        }

        return User::class;
    }

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 20;

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-cashier.resources.navigation_sort.invoices', 20);
    }

    public static function getNavigationGroup(): ?string
    {
        return FilamentCashierPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::invoices.title');
    }

    public static function getModelLabel(): string
    {
        return __('filament-cashier::invoices.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-cashier::invoices.plural');
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
        ];
    }

    public static function resolveRecordRouteBinding(int | string $key, ?Closure $modifyQuery = null): ?Model
    {
        return null;
    }
}
