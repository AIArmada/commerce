<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

final readonly class ResolvedDestinationData
{
    public function __construct(
        public string $destination,
        public string $channel,
        public string $ciphertext,
        public string $hash,
        public string $hint,
    ) {}
}
