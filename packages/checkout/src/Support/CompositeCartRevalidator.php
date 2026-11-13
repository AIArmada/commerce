<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Orders\Support\CartLineSnapshot;
use Illuminate\Database\Eloquent\Model;
use Throwable;

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
            if (CartLineSnapshot::fromArray($item)->hasComponents()) {
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
        if (! self::supports($session)) {
            return [];
        }

        $snapshot = is_array($session->cart_snapshot ?? null) ? $session->cart_snapshot : [];
        $snapshotItems = self::items($snapshot);

        if (! is_string($session->cart_id) || mb_trim($session->cart_id) === '') {
            return [self::ERROR_KEY => self::CONTENTS_CHANGED];
        }

        $cart = $this->cartManager->getById($session->cart_id);

        if ($cart === null) {
            return [self::ERROR_KEY => self::CONTENTS_CHANGED];
        }

        $liveSnapshot = (array) $cart->content();
        $liveItems = self::items($liveSnapshot);

        if ($this->contentSignature($liveItems) !== $this->contentSignature($snapshotItems)) {
            return [self::ERROR_KEY => self::CONTENTS_CHANGED];
        }

        if ($this->priceChanged($cart, $liveSnapshot, $snapshot, $liveItems, $snapshotItems)) {
            return [self::ERROR_KEY => self::PRICE_CHANGED];
        }

        return match ($this->catalogChange($snapshotItems)) {
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
        object $cart,
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

        $snapshotSubtotal = (int) ($snapshot['subtotal'] ?? 0);
        $snapshotTotal = (int) ($snapshot['total'] ?? 0);
        $liveSubtotal = (int) $cart->getRawSubtotal();
        $liveTotal = (int) $cart->getRawTotal();

        if ($snapshotSubtotal > 0 && $liveSubtotal !== $snapshotSubtotal) {
            return true;
        }

        return $snapshotTotal > 0 && $liveTotal !== $snapshotTotal;
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshotItems
     * @return 'contents'|'price'|null
     */
    private function catalogChange(array $snapshotItems): ?string
    {
        foreach ($snapshotItems as $item) {
            $line = CartLineSnapshot::fromArray($item);

            if (! $line->hasComponents()) {
                continue;
            }

            $product = $this->resolveProduct($line);

            if (! $product instanceof Model || ! method_exists($product, 'compositeSnapshot')) {
                continue;
            }

            try {
                $fresh = $product->compositeSnapshot();
            } catch (Throwable) {
                // Owner-scoped catalog lookup failure is not proof of a
                // shopper change. The live cart comparison above remains the
                // authoritative pre-payment check.
                continue;
            }

            $entries = CartLineSnapshot::normalizeComponents($fresh->entries ?? null);

            if ($entries !== $line->components) {
                return 'contents';
            }

            if (isset($fresh->price) && (int) $fresh->price !== $line->unitPrice) {
                return 'price';
            }
        }

        return null;
    }

    private function resolveProduct(CartLineSnapshot $line): ?Model
    {
        $type = $line->purchasableType;
        $id = $line->productId ?? $line->purchasableId;

        if ($type === null || $id === null || ! class_exists($type) || ! is_subclass_of($type, Model::class)) {
            return null;
        }

        /** @var class-string<Model> $type */
        $model = $type::query()->find($id);

        return $model instanceof Model ? $model : null;
    }
}
