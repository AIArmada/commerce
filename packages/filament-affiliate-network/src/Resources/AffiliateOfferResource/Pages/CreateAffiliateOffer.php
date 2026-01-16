<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages;

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAffiliateOffer extends CreateRecord
{
    protected static string $resource = AffiliateOfferResource::class;
}
