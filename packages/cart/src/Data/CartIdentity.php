<?php

declare(strict_types=1);

namespace AIArmada\Cart\Data;

final readonly class CartIdentity
{
    public function __construct(
        public string $identifier,
        public string $instance,
    ) {}
}
