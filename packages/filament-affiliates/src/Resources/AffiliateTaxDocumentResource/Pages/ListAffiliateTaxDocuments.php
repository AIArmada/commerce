<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateTaxDocumentResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateTaxDocumentResource;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateTaxDocuments extends ListRecords
{
    protected static string $resource = AffiliateTaxDocumentResource::class;
}
