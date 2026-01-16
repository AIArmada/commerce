<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource\Pages;

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateOfferApplications extends ListRecords
{
    protected static string $resource = AffiliateOfferApplicationResource::class;
}
