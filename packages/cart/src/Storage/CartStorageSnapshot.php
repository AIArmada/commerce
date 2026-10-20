<?php

declare(strict_types=1);

namespace AIArmada\Cart\Storage;

/**
 * Complete cart content read together with the revision it was read at.
 *
 * This is the single coherent read boundary for cart read/modify/write
 * cycles. The revision returned with the content must be passed back to the
 * replacing write so a stale replacement is rejected instead of silently
 * overwriting a concurrent mutation.
 */
final readonly class CartStorageSnapshot
{
    /**
     * @param  array<string, mixed>  $items
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public int $version,
        public array $items = [],
        public array $conditions = [],
        public array $metadata = [],
    ) {}

    public function revision(): CartRevision
    {
        return CartRevision::of($this->id, $this->version);
    }
}
