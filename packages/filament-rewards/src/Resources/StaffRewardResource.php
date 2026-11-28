<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources;

use AIArmada\FilamentRewards\Support\RewardAdminPresenter;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Override;

abstract class StaffRewardResource extends Resource
{
    #[Override]
    public static function canViewAny(): bool
    {
        return RewardAdminPresenter::staffActor() !== null && parent::canViewAny();
    }

    #[Override]
    public static function canView(Model $record): bool
    {
        return RewardAdminPresenter::staffActor() !== null
            && is_a($record, static::getModel())
            && $record->exists
            && static::getEloquentQuery()->whereKey($record->getKey())->exists()
            && parent::canView($record);
    }
}
