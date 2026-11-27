<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Contracts\VariantGeneratorInterface;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final readonly class GenerateVariants
{
    public function __construct(private VariantGeneratorInterface $generator) {}

    /** @return Collection<int, Variant> */
    public function execute(Product $product): Collection
    {
        return DB::transaction(function () use ($product): Collection {
            $query = Product::query();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $locked = $query->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $ids = $this->generator->generate((string) $locked->getKey());

            if ($ids === []) {
                return collect();
            }

            $variants = $locked->variants()->whereKey($ids)->get()->keyBy('id');

            return collect($ids)->map(static function (string $id) use ($variants): Variant {
                $variant = $variants->get($id);

                if (! $variant instanceof Variant) {
                    throw new UnexpectedValueException('Variant generator returned an identifier outside the selected product.');
                }

                return $variant;
            })->values();
        }, 3);
    }

    /** @return Collection<int, Variant> */
    public function __invoke(Product $product): Collection
    {
        return $this->execute($product);
    }
}
