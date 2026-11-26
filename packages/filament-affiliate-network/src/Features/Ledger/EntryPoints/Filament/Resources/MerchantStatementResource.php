<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources;

use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources\MerchantStatementResource\Pages\ListMerchantStatements;
use AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources\MerchantStatementResource\Pages\ViewMerchantStatement;
use AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources\MerchantStatementResource\Schemas\MerchantStatementInfolist;
use AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources\MerchantStatementResource\Tables\MerchantStatementsTable;
use AIArmada\FilamentAffiliateNetwork\Platform\NetworkAdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class MerchantStatementResource extends Resource
{
    protected static ?string $model = MerchantStatement::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static ?string $navigationLabel = 'Statements';

    protected static ?string $modelLabel = 'Statement';

    protected static ?string $pluralModelLabel = 'Statements';

    public static function canViewAny(): bool
    {
        return NetworkAdminAccess::allows();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliate-network.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliate-network.navigation.sort', 50) + 4;
    }

    public static function infolist(Schema $schema): Schema
    {
        return MerchantStatementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MerchantStatementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    /**
     * @return Builder<MerchantStatement>
     */
    public static function getEloquentQuery(): Builder
    {
        // Admin resource: bypass owner scope to show all statements network-wide.
        /** @var Builder<MerchantStatement> $query */
        $query = parent::getEloquentQuery();

        return $query->withoutOwnerScope();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMerchantStatements::route('/'),
            'view' => ViewMerchantStatement::route('/{record}'),
        ];
    }
}
