<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

interface PromotionUsage
{
    public function consume(string $promotionId): bool;
}
