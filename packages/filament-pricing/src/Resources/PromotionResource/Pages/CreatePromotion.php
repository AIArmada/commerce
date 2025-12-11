<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PromotionResource\Pages;

use AIArmada\FilamentPricing\Resources\PromotionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;
}
