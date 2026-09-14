<?php

declare(strict_types=1);

namespace AIArmada\Products\Jobs;

use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Strategies\MatrixVariantGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class GenerateVariantsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use OwnerContextJob;
    use Queueable;

    public function __construct(
        public string $productId,
        public ?string $ownerType = null,
        public string | int | null $ownerId = null,
        public bool $ownerIsGlobal = false,
    ) {}

    protected function performJob(): void
    {
        // Resolved through the job's owner context (established by
        // OwnerContextJob), so a tampered product id yields nothing instead
        // of generating variants for another owner's product.
        $product = Product::query()
            ->whereKey($this->productId)
            ->first();

        if ($product === null) {
            return;
        }

        app(MatrixVariantGenerator::class)->generateSynchronously($product);
    }
}
