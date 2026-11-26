<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

final readonly class ConversionTrafficBreakdown
{
    /**
     * @param array<string, int> $sources
     * @param array<string, int> $campaigns
     */
    public function __construct(public array $sources, public array $campaigns) {}

    /** @return array{sources: array<string, int>, campaigns: array<string, int>} */
    public function toArray(): array
    {
        return ['sources' => $this->sources, 'campaigns' => $this->campaigns];
    }
}
