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
        $product = Product::query()
            ->withoutOwnerScope()
            ->whereKey($this->productId)
            ->first();

        if ($product === null) {
            return;
        }

        app(MatrixVariantGenerator::class)->generateSynchronously($product);
    }
}
