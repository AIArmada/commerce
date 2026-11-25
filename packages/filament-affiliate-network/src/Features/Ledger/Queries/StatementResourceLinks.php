<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Ledger\Queries;

use AIArmada\FilamentAffiliateNetwork\Features\Ledger\Api\StatementLinks;
use AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources\MerchantStatementResource;

final class StatementResourceLinks implements StatementLinks
{
    public function view(string $statementId): string
    {
        return MerchantStatementResource::getUrl('view', ['record' => $statementId]);
    }
}
