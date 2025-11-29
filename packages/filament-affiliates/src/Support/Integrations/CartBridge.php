<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Support\Integrations;

use AIArmada\FilamentCart\Models\Cart as FilamentCart;
use AIArmada\FilamentCart\Resources\CartResource;
use Illuminate\Database\Eloquent\Model;

final class CartBridge
{
    private bool $available;

    public function __construct()
    {
        $this->available = class_exists(FilamentCart::class) && class_exists(CartResource::class);
    }

    public function warm(): void
    {
        // reserved for future runtime hooks
    }

    public function isAvailable(): bool
    {
        return $this->available && (bool) config('filament-affiliates.integrations.filament_cart', true);
    }

    public function resolveUrl(?string $identifier, ?string $instance = null): ?string
    {
        if (! $this->isAvailable() || ! $identifier) {
            return null;
        }

        /** @var Model|null $cart */
        $cartQuery = FilamentCart::query()->where('identifier', $identifier);

        if ($instance) {
            $cartQuery->where('instance', $instance);
        }

        $cart = $cartQuery->latest('created_at')->first();

        if (! $cart) {
            return null;
        }

        return CartResource::getUrl('view', ['record' => $cart]);
    }
}
