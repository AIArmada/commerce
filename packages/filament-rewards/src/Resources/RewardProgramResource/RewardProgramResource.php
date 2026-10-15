<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardProgramResource;

use AIArmada\FilamentRewards\Resources\RewardProgramResource\Pages\CreateRewardProgram;
use AIArmada\FilamentRewards\Resources\RewardProgramResource\Pages\ListRewardPrograms;
use AIArmada\FilamentRewards\Resources\RewardProgramResource\Pages\ViewRewardProgram;
use AIArmada\FilamentRewards\Resources\RewardProgramResource\Schemas\RewardProgramForm;
use AIArmada\FilamentRewards\Resources\RewardProgramResource\Schemas\RewardProgramInfolist;
use AIArmada\FilamentRewards\Resources\RewardProgramResource\Tables\RewardProgramsTable;
use AIArmada\FilamentRewards\Support\RewardAdminPresenter;
use AIArmada\Rewards\Models\RewardProgram;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

class RewardProgramResource extends Resource
{
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-rewards.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-rewards.navigation.sort.programs');
    }

    protected static ?string $model = RewardProgram::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $recordTitleAttribute = 'title';

    /**
     * Program families are the tenant boundary for the whole rewards ledger, so
     * only staff panel users may open the create page (the page handler repeats
     * the check as its own fail-closed boundary).
     */
    #[Override]
    public static function canCreate(): bool
    {
        return RewardAdminPresenter::staffActor() !== null;
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return RewardProgramForm::configure($schema);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return RewardProgramInfolist::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return RewardProgramsTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListRewardPrograms::route('/'),
            'create' => CreateRewardProgram::route('/create'),
            'view' => ViewRewardProgram::route('/{record}'),
        ];
    }
}
