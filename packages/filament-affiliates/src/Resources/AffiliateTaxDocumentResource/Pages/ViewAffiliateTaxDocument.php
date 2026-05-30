<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateTaxDocumentResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateTaxDocumentResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewAffiliateTaxDocument extends ViewRecord
{
    protected static string $resource = AffiliateTaxDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
