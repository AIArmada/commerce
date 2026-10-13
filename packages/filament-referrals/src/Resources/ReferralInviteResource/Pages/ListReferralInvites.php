<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralInviteResource\Pages;

use AIArmada\FilamentReferrals\Resources\ReferralInviteResource\ReferralInviteResource;
use Filament\Resources\Pages\ListRecords;

class ListReferralInvites extends ListRecords
{
    protected static string $resource = ReferralInviteResource::class;
}
