<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Models\TicketTypeProduct;
use Illuminate\Database\Eloquent\Model;

final class AutoAddRequiredTicketBundlesAction
{
    public function handle(Cart $cart, TicketType $ticketType, int $ticketQuantity): void
    {
        if (! CommerceIntegration::aiArmadaCheckoutAvailable()) {
            return;
        }

        $requiredBundles = $ticketType->requiredBundleProducts;

        if ($requiredBundles->isEmpty()) {
            return;
        }

        $productModel = CommerceIntegration::modelClass('product_model');
        $variantModel = CommerceIntegration::modelClass('variant_model');

        if ($productModel === null && $variantModel === null) {
            return;
        }

        foreach ($requiredBundles as $bundle) {
            $this->addBundleLine($cart, $ticketType, $bundle, $ticketQuantity, $productModel, $variantModel);
        }
    }

    private function addBundleLine(
        Cart $cart,
        TicketType $ticketType,
        TicketTypeProduct $bundle,
        int $ticketQuantity,
        ?string $productModel,
        ?string $variantModel,
    ): void {
        if ($bundle->variant_id !== null && $variantModel !== null) {
            $purchasable = $this->resolveVariant($variantModel, $bundle->variant_id);

            if ($purchasable === null) {
                return;
            }

            $lineKey = 'bundle_variant_' . $bundle->variant_id . '_for_' . $ticketType->getKey();
            $lineQuantity = $bundle->quantity * $ticketQuantity;
            $price = $this->getEffectivePrice($purchasable);

            $this->addOrUpdateCartLine($cart, $lineKey, $purchasable, $lineQuantity, $price, $ticketType, $bundle);

            return;
        }

        if ($bundle->product_id !== null && $productModel !== null) {
            $purchasable = $this->resolveProduct($productModel, $bundle->product_id);

            if ($purchasable === null) {
                return;
            }

            $lineKey = 'bundle_product_' . $bundle->product_id . '_for_' . $ticketType->getKey();
            $lineQuantity = $bundle->quantity * $ticketQuantity;
            $price = $this->getEffectivePrice($purchasable);

            $this->addOrUpdateCartLine($cart, $lineKey, $purchasable, $lineQuantity, $price, $ticketType, $bundle);
        }
    }

    private function addOrUpdateCartLine(
        Cart $cart,
        string $lineKey,
        Model $purchasable,
        int $lineQuantity,
        int $price,
        TicketType $ticketType,
        TicketTypeProduct $bundle,
    ): void {
        if ($cart->has($lineKey)) {
            $existing = $cart->get($lineKey);
            $newQuantity = $existing->quantity + $lineQuantity;

            $cart->update($lineKey, [
                'quantity' => ['value' => $newQuantity],
            ]);

            return;
        }

        $cart->add(
            id: $lineKey,
            name: $purchasable->getAttribute('name') ?? $bundle->ticketType->name . ' Bundle',
            price: $price,
            quantity: $lineQuantity,
            attributes: [
                'auto_added_for_ticket_type_id' => $ticketType->getKey(),
                'bundle_product_id' => $bundle->product_id,
                'bundle_variant_id' => $bundle->variant_id,
                'bundle_inclusion_mode' => $bundle->inclusion_mode,
            ],
            associatedModel: $purchasable,
        );
    }

    private function resolveProduct(string $productModel, string $productId): ?Model
    {
        return $productModel::query()->find($productId);
    }

    private function resolveVariant(string $variantModel, string $variantId): ?Model
    {
        return $variantModel::query()->with('product')->find($variantId);
    }

    private function getEffectivePrice(Model $purchasable): int
    {
        $price = $purchasable->getAttribute('price');

        if ($price !== null) {
            return (int) $price;
        }

        return 0;
    }
}
