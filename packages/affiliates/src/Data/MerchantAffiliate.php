<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

/**
 * A recognized merchant affiliate: identity plus owner coordinates.
 * Never a user record, never a gate — unknown ids resolve to null.
 */
final readonly class MerchantAffiliate
{
    public function __construct(
        public string $id,
        public string $code,
        public ?string $email,
        public ?string $ownerType,
        public string|int|null $ownerId,
    ) {}
}
