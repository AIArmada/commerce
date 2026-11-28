<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Services\CompositePackageService;
use Illuminate\Support\Facades\DB;

/**
 * Validates the complete package and moves the parent to Active.
 */
final readonly class PublishCompositeProduct
{
    public function __construct(
        private CompositePackageService $service,
        private UpdateProductStatus $updateStatus,
    ) {}

    public function handle(Product $product): Product
    {
        return DB::transaction(function () use ($product): Product {
            $query = Product::query();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $parent = $query->whereKey($product->getKey())
                ->lockForUpdate()
                ->first();

            if (! $parent instanceof Product) {
                throw new InvalidCompositePackageException('Composite package is not accessible in the current owner scope.');
            }

            if (! $parent->type instanceof ProductType || $parent->type !== ProductType::Composite) {
                throw new InvalidCompositePackageException('Only composite products can be published as packages.');
            }

            if ($parent->status === ProductStatus::Active) {
                $this->service->assertPublishable($parent);
            }

            return $this->updateStatus->execute($parent, ProductStatus::Active);
        });
    }

    public function __invoke(Product $product): Product
    {
        return $this->handle($product);
    }
}
