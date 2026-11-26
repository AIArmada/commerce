<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateTaxDocumentResource\Pages;

use AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateTaxDocumentResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewAffiliateTaxDocument extends ViewRecord
{
    protected static string $resource = AffiliateTaxDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
