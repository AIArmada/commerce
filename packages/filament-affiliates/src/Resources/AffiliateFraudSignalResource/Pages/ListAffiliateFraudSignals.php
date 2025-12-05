<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateFraudSignals extends ListRecords
{
    protected static string $resource = AffiliateFraudSignalResource::class;
}
