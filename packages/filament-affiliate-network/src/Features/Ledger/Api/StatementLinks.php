<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Ledger\Api;

interface StatementLinks
{
    public function view(string $statementId): string;
}
