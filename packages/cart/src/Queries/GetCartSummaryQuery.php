<?php

declare(strict_types=1);

namespace AIArmada\Cart\Queries;

/**
 * Query to get cart summary by ID.
 */
final readonly class GetCartSummaryQuery
{
    public function __construct(
        public string $cartId
    ) {}
}
