<?php

declare(strict_types=1);

namespace AIArmada\Docs\Data;

final readonly class IssuedShareLink
{
    /** @param list<string> $allowedActions */
    public function __construct(
        public string $id,
        #[\SensitiveParameter]
        private string $token,
        public array $allowedActions,
    ) {}

    public function plainToken(): string
    {
        return $this->token;
    }
}
