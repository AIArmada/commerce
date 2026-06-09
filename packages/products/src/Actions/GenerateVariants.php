<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\Products\Contracts\VariantGeneratorInterface;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Support\Collection;

final class GenerateVariants
{
    public function __construct(
        private VariantGeneratorInterface $generator
    ) {}

    /**
     * @return Collection<int, Variant>
     */
    public function execute(Product $product): Collection
    {
        return $this->generator->generate($product);
    }

    public function __invoke(Product $product): Collection
    {
        return $this->generator->generate($product);
    }
}
