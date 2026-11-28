<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class CreateProduct
{
    /**
     * ProductCreated fires once via the model's $dispatchesEvents mapping.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): Product
    {
        return DB::transaction(function () use ($attributes): Product {
            $data = Arr::except($attributes, ['id', 'owner_type', 'owner_id']);

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $data['owner_type'] = $owner?->getMorphClass();
                $data['owner_id'] = $owner?->getKey();
            }

            return Product::query()->create($data);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes): Product
    {
        return $this->execute($attributes);
    }
}
