<?php

declare(strict_types=1);

namespace AIArmada\Cart\Runtime;

use InvalidArgumentException;

final readonly class CartOwnerIdentity
{
    public function __construct(
        public ?string $ownerType,
        public string | int | null $ownerId,
    ) {
        if ($ownerType === '' || (is_string($ownerId) && $ownerId === '')) {
            throw new InvalidArgumentException('Owner type and owner id must not be empty strings.');
        }

        if (($ownerType === null) !== ($ownerId === null)) {
            throw new InvalidArgumentException('Owner type and owner id must both be present or both be null.');
        }
    }

    public static function fromTypeAndId(?string $ownerType, string | int | null $ownerId): self
    {
        return new self($ownerType, $ownerId);
    }

    public static function global(): self
    {
        return new self(null, null);
    }

    public function isGlobal(): bool
    {
        return $this->ownerType === null && $this->ownerId === null;
    }
}
