<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\CampaignResource\Pages;

use AIArmada\FilamentVouchers\Resources\CampaignResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;
}
