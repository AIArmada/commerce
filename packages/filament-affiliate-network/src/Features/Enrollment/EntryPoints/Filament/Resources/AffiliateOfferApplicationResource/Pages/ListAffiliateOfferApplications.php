<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Enrollment\EntryPoints\Filament\Resources\AffiliateOfferApplicationResource\Pages;

use AIArmada\FilamentAffiliateNetwork\Features\Enrollment\EntryPoints\Filament\Resources\AffiliateOfferApplicationResource;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateOfferApplications extends ListRecords
{
    protected static string $resource = AffiliateOfferApplicationResource::class;
}
