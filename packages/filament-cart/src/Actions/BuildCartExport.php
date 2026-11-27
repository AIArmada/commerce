<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Actions;

use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentCart\Data\CartExport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use JsonException;

final readonly class BuildCartExport
{
    /**
     * Build the cart export. Callers must authorize first; the cart is
     * revalidated against the current owner scope as defense in depth.
     *
     * @throws JsonException
     */
    public function execute(CartSnapshot $cart): CartExport
    {
        $cart = $this->authorizeCart($cart);
        $json = json_encode($this->payload($cart), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return new CartExport($this->filename($cart), $json);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CartSnapshot $cart): array
    {
        return [
            'id' => $cart->id,
            'identifier' => $cart->identifier,
            'instance' => $cart->instance,
            'owner_type' => $cart->owner_type,
            'owner_id' => $cart->owner_id,
            'items' => $cart->items,
            'conditions' => $cart->conditions,
            'metadata' => $cart->metadata,
            'exported_at' => CarbonImmutable::now()->toISOString(),
        ];
    }

    private function filename(CartSnapshot $cart): string
    {
        $instance = $this->normalizeFileComponent($cart->instance, 'default');
        $identifier = $this->normalizeFileComponent($cart->identifier, 'cart');

        return sprintf('cart-%s-%s-%s.json', $instance, $identifier, $cart->id);
    }

    private function authorizeCart(CartSnapshot $cart): CartSnapshot
    {
        if (! CartSnapshot::ownerScopingEnabled()) {
            return $cart;
        }

        /** @var CartSnapshot $validated */
        $validated = OwnerWriteGuard::findOrFailForOwner(
            CartSnapshot::class,
            (string) $cart->getKey(),
            includeGlobal: false,
            message: 'Cart is not accessible in the current owner scope.',
        );

        return $validated;
    }

    private function normalizeFileComponent(string $value, string $fallback): string
    {
        $normalized = Str::of($value)
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-_.')
            ->toString();

        return $normalized === '' ? $fallback : $normalized;
    }
}
