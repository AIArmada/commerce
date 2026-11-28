<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Api;

interface CustomerMembershipCleaner
{
    public function cleanup(string $customerId): void;
}
