<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Support\CompositeComponents;
use AIArmada\Orders\Support\CartLineSnapshot;
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Models\Product;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Rechecks a composite cart immediately before payment.
 *
 * The shopper is charged against a frozen composition. If the live cart
 * contents, current catalog composition, or a computed price has drifted, the
 * checkout must stop and ask the shopper to review instead of charging a
 * total they have not seen.
 */
final class CompositeCartRevalidator
{
    public const string ERROR_KEY = 'cart';

    public const string CONTENTS_CHANGED = 'Cart contents changed since checkout started. Please review your cart before payment.';

    public const string PRICE_CHANGED = 'Cart price changed since checkout started. Please review your cart before payment.';

    public function __construct(
        private readonly CartManagerInterface $cartManager,
    ) {}

    public static function supports(CheckoutSession $session): bool
    {
        foreach (self::items($session->cart_snapshot ?? []) as $item) {
            if (array_key_exists('components', (array) ($item['attributes'] ?? []))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public function validate(CheckoutSession $session): array
    {
        $snapshot = is_array($session->cart_snapshot ?? null) ? $session->cart_snapshot : [];
        $snapshotItems = self::items($snapshot);

        if (! is_string($session->cart_id) || mb_trim($session->cart_id) === '') {
            return [self::ERROR_KEY => self::CONTENTS_CHANGED];
        }

        $cart = $this->cartManager->getById($session->cart_id);

        if ($cart === null) {
            return [self::ERROR_KEY => self::CONTENTS_CHANGED];
        }

        $liveSnapshot = $cart->content();
        $liveItems = self::items($liveSnapshot);

        if (($liveSnapshot['metadata']['currency'] ?? $session->currency) !== $session->currency
            || $this->contentSignature($liveItems) !== $this->contentSignature($snapshotItems)) {
            return [self::ERROR_KEY => self::CONTENTS_CHANGED];
        }

        if ($this->priceChanged($liveSnapshot, $snapshot, $liveItems, $snapshotItems)) {
            return [self::ERROR_KEY => self::PRICE_CHANGED];
        }

        return match ($this->catalogChange($session, $snapshotItems)) {
            'contents' => [self::ERROR_KEY => self::CONTENTS_CHANGED],
            'price' => [self::ERROR_KEY => self::PRICE_CHANGED],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<int, array<string, mixed>>
     */
    private static function items(array $snapshot): array
    {
        $items = $snapshot['items'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, static fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function contentSignature(array $items): string
    {
        $fingerprints = array_map(
            static fn (array $item): string => CartLineSnapshot::fromArray($item)->contentFingerprint(),
            $items,
        );

        sort($fingerprints);

        return hash('sha256', implode('|', $fingerprints));
    }

    /**
     * @param  array<string, mixed>  $liveSnapshot
     * @param  array<string, mixed>  $snapshot
     * @param  array<int, array<string, mixed>>  $liveItems
     * @param  array<int, array<string, mixed>>  $snapshotItems
     */
    private function priceChanged(
        array $liveSnapshot,
        array $snapshot,
        array $liveItems,
        array $snapshotItems,
    ): bool {
        $livePrices = array_map(
            static fn (array $item): string => CartLineSnapshot::fromArray($item)->priceFingerprint(),
            $liveItems,
        );
        $snapshotPrices = array_map(
            static fn (array $item): string => CartLineSnapshot::fromArray($item)->priceFingerprint(),
            $snapshotItems,
        );

        sort($livePrices);
        sort($snapshotPrices);

        if ($livePrices !== $snapshotPrices) {
            return true;
        }

        return (int) ($snapshot['subtotal'] ?? 0) !== (int) ($liveSnapshot['subtotal'] ?? 0)
            || (int) ($snapshot['total'] ?? 0) !== (int) ($liveSnapshot['total'] ?? 0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshotItems
     * @return 'contents'|'price'|null
     */
    private function catalogChange(CheckoutSession $session, array $snapshotItems): ?string
    {
        $quotedPrices = [];

        foreach ($session->pricing_data['items'] ?? [] as $pricingItem) {
            if (isset($pricingItem['item_id'], $pricingItem['unit_price'])) {
                $quotedPrices[(string) $pricingItem['item_id']] = (int) $pricingItem['unit_price'];
            }
        }

        foreach ($snapshotItems as $item) {
            $line = CartLineSnapshot::fromArray($item);

            if (! $line->hasComponents()) {
                continue;
            }

            $product = $this->resolveProduct($line);

            if (! $product instanceof Product || ! $product->isCompositeProduct() || ! $product->isPurchasable()) {
                return 'contents';
            }

            try {
                $fresh = $product->compositeSnapshot();
            } catch (InvalidCompositePackageException) {
                return 'contents';
            }

            if ($fresh->entries === []) {
                return 'contents';
            }

            $entries = CompositeComponents::read($fresh->entries);

            if ($entries !== $line->components
                || $fresh->currency !== $line->currency
                || (bool) $product->requires_shipping !== $line->requiresShipping
                || (bool) $product->is_taxable !== $line->isTaxable
                || (string) $product->tax_class !== (string) $line->taxClass) {
                return 'contents';
            }

            $context = ['customer_id' => $session->customer_id, 'currency' => $session->currency];
            $currentPrice = app()->bound(PriceCalculatorInterface::class)
                ? app(PriceCalculatorInterface::class)->calculate($product, $line->quantity, $context)->finalPrice
                : $fresh->price;

            if ($currentPrice !== $line->unitPrice
                || (isset($quotedPrices[$line->lineId]) && $currentPrice !== $quotedPrices[$line->lineId])) {
                return 'price';
            }
        }

        return null;
    }

    private function resolveProduct(CartLineSnapshot $line): ?Product
    {
        $type = $line->purchasableType;

        if ($type === null || $line->purchasableId === null) {
            return null;
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_a($class, Product::class, true)) {
            return null;
        }

        $product = $class::query()->find($line->purchasableId);

        return $product instanceof Product ? $product : null;
    }
}
