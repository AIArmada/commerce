<?php

declare(strict_types=1);

namespace AIArmada\Stock\Events;

use AIArmada\Stock\Models\StockTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when stock is deducted (after successful purchase).
 */
final class StockDeducted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Model $stockable,
        public readonly int $quantity,
        public readonly string $reason,
        public readonly ?string $orderId,
        public readonly StockTransaction $transaction
    ) {}
}
