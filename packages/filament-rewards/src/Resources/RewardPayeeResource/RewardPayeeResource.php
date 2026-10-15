<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardPayeeResource;

use AIArmada\FilamentRewards\Resources\RewardPayeeResource\Pages\ListRewardPayees;
use AIArmada\FilamentRewards\Resources\RewardPayeeResource\Tables\RewardPayeesTable;
use AIArmada\FilamentRewards\Support\RewardAdminPresenter;
use AIArmada\Rewards\Models\RewardPayee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

class RewardPayeeResource extends Resource
{
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-rewards.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-rewards.navigation.sort.payees');
    }

    protected static ?string $model = RewardPayee::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'Payees';

    /**
     * Payee rows are recipient-keyed by design and carry no program/owner
     * column to scope on, so panel access is gated on the staff role instead.
     */
    #[Override]
    public static function canViewAny(): bool
    {
        return RewardAdminPresenter::staffActor() !== null;
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return RewardPayeesTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListRewardPayees::route('/'),
        ];
    }
}
