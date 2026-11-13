<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Services\CompositePackageService;
use Illuminate\Support\Facades\DB;

/**
 * Validates the complete package and moves the parent to Active.
 */
final class PublishCompositeProduct
{
    public function __construct(
        private readonly CompositePackageService $service,
    ) {}

    public function handle(Product $product): Product
    {
        return DB::transaction(function () use ($product): Product {
            $parent = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->first();

            if (! $parent instanceof Product) {
                throw new InvalidCompositePackageException('Composite package is not accessible in the current owner scope.');
            }

            if (! $parent->type instanceof ProductType || $parent->type !== ProductType::Composite) {
                throw new InvalidCompositePackageException('Only composite products can be published as packages.');
            }

            $this->service->assertPublishable($parent);

            return app(UpdateProductStatus::class)->execute($parent, ProductStatus::Active);
        });
    }

    public function __invoke(Product $product): Product
    {
        return $this->handle($product);
    }
}
