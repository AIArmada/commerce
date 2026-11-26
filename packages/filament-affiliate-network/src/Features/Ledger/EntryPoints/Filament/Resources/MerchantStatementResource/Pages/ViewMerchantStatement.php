<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources\MerchantStatementResource\Pages;

use AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources\MerchantStatementResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewMerchantStatement extends ViewRecord
{
    protected static string $resource = MerchantStatementResource::class;
}
