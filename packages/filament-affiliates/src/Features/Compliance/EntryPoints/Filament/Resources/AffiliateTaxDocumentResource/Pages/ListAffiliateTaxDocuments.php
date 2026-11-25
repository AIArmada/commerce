<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateTaxDocumentResource\Pages;

use AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateTaxDocumentResource;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateTaxDocuments extends ListRecords
{
    protected static string $resource = AffiliateTaxDocumentResource::class;
}
