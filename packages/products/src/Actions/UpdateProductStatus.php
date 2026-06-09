<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Events\ProductStatusChanged;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Carbon;

final class UpdateProductStatus
{
    /**
     * Tracks whether a status transition is already being handled.
     * Prevents the model booted listener from dispatching a duplicate event.
     */
    private static bool $handlingStatusChange = false;

    public function execute(Product $product, ProductStatus $newStatus): Product
    {
        $oldStatus = $product->status;

        if ($oldStatus === $newStatus) {
            return $product;
        }

        $product->status = $newStatus;

        if ($newStatus === ProductStatus::Active && $product->published_at === null) {
            $product->published_at = Carbon::now();
        }

        static::$handlingStatusChange = true;

        try {
            $product->save();
        } finally {
            static::$handlingStatusChange = false;
        }

        event(new ProductStatusChanged($product, $oldStatus, $newStatus));

        return $product->fresh();
    }

    public function __invoke(Product $product, ProductStatus $newStatus): Product
    {
        return $this->execute($product, $newStatus);
    }

    /**
     * Returns true when a status change is being performed by this Action.
     * Used by the model booted listener to avoid duplicate event dispatch.
     */
    public static function isHandlingStatusChange(): bool
    {
        return static::$handlingStatusChange;
    }
}
