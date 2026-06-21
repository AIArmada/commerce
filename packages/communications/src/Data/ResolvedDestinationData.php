<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

use Spatie\LaravelData\Data;

final class ResolvedDestinationData extends Data
{
    public function __construct(
        public readonly string $destination,
        public readonly string $channel,
        public readonly string $ciphertext,
        public readonly string $hash,
        public readonly string $hint,
    ) {}
}
