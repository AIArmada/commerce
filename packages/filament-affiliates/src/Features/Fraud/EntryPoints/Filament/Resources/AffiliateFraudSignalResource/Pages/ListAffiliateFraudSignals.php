<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Resources\AffiliateFraudSignalResource\Pages;

use AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Resources\AffiliateFraudSignalResource;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateFraudSignals extends ListRecords
{
    protected static string $resource = AffiliateFraudSignalResource::class;
}
