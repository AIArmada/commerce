<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Programs\Api;

final readonly class RewardPolicySnapshot
{
    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $terms
     * @param  list<string>  $compatibleWith
     */
    public function __construct(
        public string $id,
        public int $version,
        public string $materialTermsHash,
        public array $rules,
        public array $terms,
        public string $publishedAt,
        public string $effectiveFrom,
        public array $compatibleWith,
    ) {}
}
