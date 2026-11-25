<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

interface CommissionRulePreview
{
    /**
     * @param list<array<string, mixed>> $contexts
     * @return list<CommissionRuleQuote|null>
     */
    public function previewCatalogRules(string $programId, array $contexts): array;
}
