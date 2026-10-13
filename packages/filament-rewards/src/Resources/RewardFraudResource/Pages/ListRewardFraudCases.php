<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardFraudResource\Pages;

use AIArmada\FilamentRewards\Resources\RewardFraudResource\RewardFraudResource;
use Filament\Resources\Pages\ListRecords;

class ListRewardFraudCases extends ListRecords
{
    protected static string $resource = RewardFraudResource::class;
}
