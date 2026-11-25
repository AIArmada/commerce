<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Api;

interface RankDirectory
{
    /** @return array<string, string> */
    public function options(string $search, int $limit = 50): array;

    public function label(?string $id): ?string;

    /** @return array{type: string, value: int}|null */
    public function customerDiscount(?string $id): ?array;
}
