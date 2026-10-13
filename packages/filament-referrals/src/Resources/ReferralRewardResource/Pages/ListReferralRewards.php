<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralRewardResource\Pages;

use AIArmada\FilamentReferrals\Resources\ReferralRewardResource\ReferralRewardResource;
use Filament\Resources\Pages\ListRecords;

class ListReferralRewards extends ListRecords
{
    protected static string $resource = ReferralRewardResource::class;
}
