<?php

declare(strict_types=1);

namespace AIArmada\Stock\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when stock reaches zero.
 */
final class OutOfStock
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Model $stockable
    ) {}
}
