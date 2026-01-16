<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource\Pages;

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAffiliateOfferCategory extends CreateRecord
{
    protected static string $resource = AffiliateOfferCategoryResource::class;
}
