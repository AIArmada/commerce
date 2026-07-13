<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Enums;

enum CheckoutFinalizationPhase: string
{
    case Pending = 'pending';
    case CommitInventory = 'commit_inventory';
    case CommitDiscounts = 'commit_discounts';
    case Complete = 'complete';
    case ClearCart = 'clear_cart';
    case Done = 'done';

    public function next(): ?self
    {
        return match ($this) {
            self::Pending => self::CommitInventory,
            self::CommitInventory => self::CommitDiscounts,
            self::CommitDiscounts => self::Complete,
            self::Complete => self::ClearCart,
            self::ClearCart => self::Done,
            self::Done => null,
        };
    }
}
