<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

interface CommissionTemplateReader
{
    /** @return array<string, string> */
    public function search(string $search): array;

    public function find(string $id): ?CommissionTemplateSelection;
}
