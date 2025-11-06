<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Support\Integrations;

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Resources\CartResource;
use Illuminate\Database\Eloquent\Model;

final class FilamentCartBridge
{
    private bool $available;

    /** @var class-string<Model>|null */
    private ?string $cartModel = null;

    /** @var class-string|null */
    private ?string $cartResource = null;

    public function __construct()
    {
        $this->available = class_exists(Cart::class) && class_exists(CartResource::class);

        if ($this->available) {
            $this->cartModel = Cart::class;
            $this->cartResource = CartResource::class;
        }
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function warm(): void
    {
        // When Filament Cart is available, we can add runtime integration hooks here.
        // For now, the integration is handled via manual inclusion of actions in CartResource.

        if (! $this->available) {
            return;
        }

        // Future: Automatically inject voucher actions into CartResource
        // Future: Register voucher-related cart events
        // Future: Add voucher widgets to cart dashboard
    }

    /**
     * @return class-string<Model>|null
     */
    public function getCartModel(): ?string
    {
        return $this->cartModel;
    }

    /**
     * @return class-string|null
     */
    public function getCartResource(): ?string
    {
        return $this->cartResource;
    }

    public function resolveCartUrl(?string $identifier): ?string
    {
        if (! $this->available || $identifier === null || $identifier === '') {
            return null;
        }

        $model = $this->getCartModel();
        $resource = $this->getCartResource();

        if (! $model || ! $resource) {
            return null;
        }

        /** @var Model|null $cart */
        $cart = $model::query()
            ->where('identifier', $identifier)
            ->latest('created_at')
            ->first();

        if (! $cart instanceof Model) {
            return null;
        }

        return $resource::getUrl('view', ['record' => $cart]);
    }
}
