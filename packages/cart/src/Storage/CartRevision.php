<?php

declare(strict_types=1);

namespace AIArmada\Cart\Storage;

use InvalidArgumentException;

/**
 * Identifies the exact stored cart revision a replacement was computed from.
 *
 * A revision pairs the cart row UUID with its optimistic-locking version.
 * Because the row UUID is part of the expectation, a snapshot taken before a
 * cart was deleted and recreated can never overwrite the replacement row.
 *
 * {@see CartRevision::none()} means "no row expected": the write is a
 * creation and conflicts when a cart already exists for the
 * identifier/instance pair.
 */
final readonly class CartRevision
{
    private function __construct(
        public ?string $cartId,
        public ?int $version,
    ) {
        if (($cartId === null) !== ($version === null)) {
            throw new InvalidArgumentException('A cart revision requires both a cart ID and a version, or neither.');
        }

        if ($version !== null && $version < 1) {
            throw new InvalidArgumentException('A cart revision version must be a positive integer.');
        }
    }

    /**
     * Revision expectation for a cart that does not exist yet (creation).
     */
    public static function none(): self
    {
        return new self(null, null);
    }

    public static function of(string $cartId, int $version): self
    {
        return new self($cartId, $version);
    }

    public static function fromSnapshot(CartStorageSnapshot $snapshot): self
    {
        return new self($snapshot->id, $snapshot->version);
    }

    /**
     * Whether the write expects to create a brand new cart row.
     */
    public function isCreation(): bool
    {
        return $this->cartId === null;
    }
}
