<?php

declare(strict_types=1);

namespace AIArmada\Cart\Enums;

/**
 * Defines what happens when a cart becomes empty (all items removed).
 *
 * Use cases for each behavior:
 * - Destroy: Default behavior, removes cart entirely from storage
 * - Clear: Keeps cart row but removes items, conditions, and metadata
 * - Preserve: Keeps cart with conditions and metadata intact (e.g., voucher codes, affiliate tracking)
 */
enum EmptyCartBehavior: string
{
    /**
     * Remove cart entirely from storage.
     * Items, conditions, metadata, and cart row are all deleted.
     */
    case Destroy = 'destroy';

    /**
     * Keep cart row but clear all data.
     * Items, conditions, and metadata are removed, but cart structure remains.
     */
    case Clear = 'clear';

    /**
     * Keep cart with conditions and metadata intact.
     * Only items are removed. Useful for preserving voucher codes or affiliate tracking.
     */
    case Preserve = 'preserve';
}
