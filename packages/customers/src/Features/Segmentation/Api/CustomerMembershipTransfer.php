<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Api;

interface CustomerMembershipTransfer
{
    public function transfer(string $sourceId, string $targetId): void;
}
