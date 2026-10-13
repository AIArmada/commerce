<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardBenefitGrantResource\Pages;

use AIArmada\FilamentRewards\Resources\RewardBenefitGrantResource\RewardBenefitGrantResource;
use Filament\Resources\Pages\ListRecords;

class ListRewardBenefitGrants extends ListRecords
{
    protected static string $resource = RewardBenefitGrantResource::class;
}
