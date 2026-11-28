<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\Products\Enums\CompositePriceMode;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Creates a composite parent and its initial component rows in one transaction.
 */
final readonly class CreateCompositeProduct
{
    public function __construct(
        private CreateProduct $create,
        private ReplaceProductComponents $replace,
        private PublishCompositeProduct $publish,
        private UpdateProductStatus $updateStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{
     *     component_product_id: string,
     *     variant_id?: string|null,
     *     quantity?: int,
     *     position?: int
     * }>  $components
     */
    public function handle(array $attributes, array $components = []): Product
    {
        return DB::transaction(function () use ($attributes, $components): Product {
            $requestedStatus = $attributes['status'] ?? ProductStatus::Draft;
            $requestedStatus = $requestedStatus instanceof ProductStatus
                ? $requestedStatus
                : ProductStatus::tryFrom((string) $requestedStatus);

            if (! $requestedStatus instanceof ProductStatus) {
                throw new InvalidCompositePackageException('Composite package status is invalid.');
            }

            $requestedModeValue = $attributes['price_mode'] ?? CompositePriceMode::Fixed->value;
            $requestedMode = $requestedModeValue instanceof CompositePriceMode
                ? $requestedModeValue
                : CompositePriceMode::tryFrom((string) $requestedModeValue);

            if (! $requestedMode instanceof CompositePriceMode) {
                throw new InvalidCompositePackageException('Composite package price mode is invalid.');
            }

            unset($attributes['status'], $attributes['price_mode']);

            $parent = $this->create->execute(array_replace($attributes, [
                'type' => ProductType::Composite,
                'status' => ProductStatus::Draft,
                'price_mode' => $requestedMode->value,
                'requires_shipping' => false,
                'supports_variants' => (bool) ($attributes['supports_variants'] ?? false),
                'tracks_inventory' => false,
            ]));

            if ($components !== []) {
                $this->replace->handle($parent, $components);
            }

            if ($requestedStatus === ProductStatus::Active) {
                return $this->publish->handle($parent);
            }

            if ($requestedStatus !== ProductStatus::Draft) {
                return $this->updateStatus->execute($parent, $requestedStatus);
            }

            return $parent->fresh() ?? $parent;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $components
     */
    public function __invoke(array $attributes, array $components = []): Product
    {
        return $this->handle($attributes, $components);
    }
}
