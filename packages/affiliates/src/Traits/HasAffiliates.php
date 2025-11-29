<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Traits;

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Affiliates\Support\CartWithAffiliates;
use AIArmada\Cart\Cart;

trait HasAffiliates
{
    /**
     * Attach an affiliate to the current cart instance using its public code.
     *
     * @param  array<string, mixed>  $context
     */
    public function attachAffiliate(string $code, array $context = []): self
    {
        if (! array_key_exists('cookie_value', $context)) {
            $cookieValue = $this->resolveAffiliateCookie();

            if ($cookieValue) {
                $context['cookie_value'] = $cookieValue;
            }
        }

        app(AffiliateService::class)->attachToCartByCode($code, $this->getUnderlyingCart(), $context);

        return $this;
    }

    public function detachAffiliate(): self
    {
        app(AffiliateService::class)->detachFromCart($this->getUnderlyingCart());

        return $this;
    }

    public function hasAffiliate(): bool
    {
        return $this->getUnderlyingCart()->hasMetadata(config('affiliates.cart.metadata_key', 'affiliate'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordAffiliateConversion(array $payload = []): ?AffiliateConversionData
    {
        return app(AffiliateService::class)->recordConversion($this->getUnderlyingCart(), $payload);
    }

    public function getAffiliateMetadata(?string $key = null): mixed
    {
        $metadata = $this->getUnderlyingCart()->getMetadata(config('affiliates.cart.metadata_key', 'affiliate'));

        if ($key === null) {
            return $metadata;
        }

        return is_array($metadata) ? ($metadata[$key] ?? null) : null;
    }

    private function getUnderlyingCart(): Cart
    {
        if ($this instanceof CartWithAffiliates) {
            return $this->getCart();
        }

        return $this;
    }

    private function resolveAffiliateCookie(): ?string
    {
        if (! config('affiliates.cookies.enabled', true)) {
            return null;
        }

        if (! app()->bound('request')) {
            return null;
        }

        return request()->cookie(config('affiliates.cookies.name', 'affiliate_session'));
    }
}
