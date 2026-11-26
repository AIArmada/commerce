<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Integrations;

use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionLookup;
use AIArmada\Affiliates\Features\Attribution\Api\CartAttribution;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionHistoryReader;
use AIArmada\Cart\Cart;
use AIArmada\CommerceSupport\Support\OwnerContext;

final class CartBridge
{
    private bool $available;

    public function __construct()
    {
        $this->available = class_exists('AIArmada\\Cart\\Snapshots\\CartSnapshot') && class_exists('AIArmada\\FilamentCart\\Resources\\CartResource');
    }

    public function warm(): void {}

    public function hydrateAffiliateFromCookie(Cart $cart): ?AffiliateAttributionData
    {
        if (! config('affiliates.cookies.enabled', true) || ! app()->bound('request')) {
            return null;
        }

        if (app(AttributionLookup::class)->attached($cart->getIdentifier(), $cart->instance()) !== null) {
            return null;
        }

        $cookieValue = request()->cookie(config('affiliates.cookies.name', 'affiliate_session'));

        if (! is_string($cookieValue) || $cookieValue === '') {
            return null;
        }

        return app(CartAttribution::class)->attachFromCookie($cart, $cookieValue);
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

        if ((bool) config('affiliates.owner.enabled', false)) {
            $hasReference = app(ConversionHistoryReader::class)->hasSubjectReference($identifier, $instance);

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
