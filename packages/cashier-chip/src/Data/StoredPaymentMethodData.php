<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Data;

use SensitiveParameter;

final readonly class StoredPaymentMethodData
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $id,
        #[SensitiveParameter]
        private string $token,
        public ?string $type,
        public ?string $brand,
        public ?string $last_four,
        public bool $is_default,
        public array $metadata = [],
    ) {}

    public function recurringToken(): string
    {
        return $this->token;
    }
}
