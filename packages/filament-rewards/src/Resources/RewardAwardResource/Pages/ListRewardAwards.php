<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardAwardResource\Pages;

use AIArmada\FilamentRewards\Resources\RewardAwardResource\RewardAwardResource;
use Filament\Resources\Pages\ListRecords;

class ListRewardAwards extends ListRecords
{
    protected static string $resource = RewardAwardResource::class;
}
