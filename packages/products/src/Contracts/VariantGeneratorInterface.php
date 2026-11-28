<?php

declare(strict_types=1);

namespace AIArmada\Products\Contracts;

interface VariantGeneratorInterface
{
    /** @return list<string> Persisted variant identifiers in generation order; empty when queued. */
    public function generate(string $productId): array;
}
