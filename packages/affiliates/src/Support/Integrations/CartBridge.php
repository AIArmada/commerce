<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Integrations;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\CommerceSupport\Support\OwnerContext;

final class CartBridge
{
    private bool $available;

    public function __construct()
    {
        $this->available = class_exists('AIArmada\\FilamentCart\\Models\\Cart') && class_exists('AIArmada\\FilamentCart\\Resources\\CartResource');
    }

    public function warm(): void {}

    public function isAvailable(): bool
    {
        return $this->available && (bool) config('filament-affiliates.integrations.filament_cart', true);
    }

    public function resolveUrl(?string $identifier, ?string $instance = null): ?string
    {
        if (! $this->isAvailable() || ! $identifier) {
            return null;
        }

        if ((bool) config('affiliates.owner.enabled', false)) {
            $owner = OwnerContext::resolve();

            $hasReference = AffiliateConversion::query()
                ->forOwner($owner, false)
                ->where('cart_identifier', $identifier)
                ->when(
                    $instance !== null,
                    fn ($query) => $query->where('cart_instance', $instance),
                )
                ->exists();

            if (! $hasReference) {
                return null;
            }
        }

        $cartResourceClass = 'AIArmada\\FilamentCart\\Resources\\CartResource';

        $cartQuery = $cartResourceClass::getEloquentQuery()->where('identifier', $identifier);

        if ((bool) config('affiliates.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            $cartQuery->forOwner($owner, false);
        }

        if ($instance) {
            $cartQuery->where('instance', $instance);
        }

        $cart = $cartQuery->latest('created_at')->first();

        if (! $cart) {
            return null;
        }

        if (! $cartResourceClass::canView($cart)) {
            return null;
        }

        return $cartResourceClass::getUrl('view', ['record' => $cart]);
    }
}
