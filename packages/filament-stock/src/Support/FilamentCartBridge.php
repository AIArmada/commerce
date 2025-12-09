<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Support;

use AIArmada\FilamentCart\FilamentCartPlugin;
use AIArmada\FilamentCart\Resources\CartResource;

/**
 * Bridge for integrating with Filament Cart package when available.
 */
final class FilamentCartBridge
{
    private bool $warmed = false;

    /**
     * Check if Filament Cart package is installed.
     */
    public function isAvailable(): bool
    {
        return class_exists(FilamentCartPlugin::class);
    }

    /**
     * Get the cart resource class if available.
     *
     * @return class-string|null
     */
    public function getCartResource(): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        if (class_exists(CartResource::class)) {
            return CartResource::class;
        }

        return null;
    }

    /**
     * Warm up the bridge (resolve any lazy dependencies).
     */
    public function warm(): void
    {
        if ($this->warmed) {
            return;
        }

        $this->warmed = true;
    }

    /**
     * Generate a URL to view a cart record if the resource is available.
     */
    public function getCartUrl(string $cartId): ?string
    {
        $resource = $this->getCartResource();

        if ($resource === null) {
            return null;
        }

        return $resource::getUrl('view', ['record' => $cartId]);
    }
}
