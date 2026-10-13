<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardPayoutResource\Pages;

use AIArmada\FilamentRewards\Resources\RewardPayoutResource\RewardPayoutResource;
use Filament\Resources\Pages\ListRecords;

class ListRewardPayouts extends ListRecords
{
    protected static string $resource = RewardPayoutResource::class;
}
