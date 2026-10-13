<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardAppealResource;

use AIArmada\FilamentRewards\Resources\RewardAppealResource\Pages\ListRewardAppeals;
use AIArmada\FilamentRewards\Resources\RewardAppealResource\Pages\ViewRewardAppeal;
use AIArmada\FilamentRewards\Resources\RewardAppealResource\Schemas\RewardAppealInfolist;
use AIArmada\FilamentRewards\Resources\RewardAppealResource\Tables\RewardAppealsTable;
use AIArmada\FilamentRewards\Support\RewardFilamentScope;
use AIArmada\Rewards\Models\RewardAppeal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

class RewardAppealResource extends Resource
{
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-rewards.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-rewards.navigation.sort.appeals');
    }

    protected static ?string $model = RewardAppeal::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return RewardAppealInfolist::configure($schema);
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return RewardFilamentScope::applyViaAward(parent::getEloquentQuery());
    }

    public static function table(Table $table): Table
    {
        return RewardAppealsTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListRewardAppeals::route('/'),
            'view' => ViewRewardAppeal::route('/{record}'),
        ];
    }
}
