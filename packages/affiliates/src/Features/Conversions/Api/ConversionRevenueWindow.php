<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

final readonly class ConversionRevenueWindow
{
    /**
     * @param array<string, int> $personalByCurrency
     * @param array<string, int> $lifetimeByCurrency
     */
    public function __construct(
        public array $personalByCurrency,
        public array $lifetimeByCurrency,
    ) {}
}
