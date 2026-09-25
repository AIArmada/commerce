<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

/**
 * Read-only program snapshot for mirrors and storefronts.
 */
final readonly class ProgramSnapshot
{
    /**
     * @param  array<string, mixed>  $payload  Versioned snapshot payload.
     */
    public function __construct(
        public string $programId,
        public string $currency,
        public array $payload,
    ) {}
}
