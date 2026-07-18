<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Traits;

use AIArmada\Affiliates\Actions\Affiliates\AttachAffiliateToCart;
use AIArmada\Affiliates\Actions\Affiliates\DetachAffiliateFromCart;
use AIArmada\Affiliates\Actions\Conversions\RecordAffiliateConversion;
use AIArmada\Affiliates\Contracts\AffiliateLookup;
use AIArmada\Affiliates\Data\AffiliateConversionData;
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

        $affiliate = app(AffiliateLookup::class)->findByCode($code);

        if ($affiliate !== null && $affiliate->isActive()) {
            app(AttachAffiliateToCart::class)->handle($affiliate, $this->getUnderlyingCart(), $context);
        }

        return $this;
    }

    public function detachAffiliate(): self
    {
        app(DetachAffiliateFromCart::class)->handle($this->getUnderlyingCart());

        return $this;
    }

    public function hasAffiliate(): bool
    {
        return app(AffiliateLookup::class)->findAttachedAttribution($this->getUnderlyingCart()) !== null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordAffiliateConversion(array $payload = []): ?AffiliateConversionData
    {
        return app(RecordAffiliateConversion::class)->handle($this->getUnderlyingCart(), $payload);
    }

    public function getAffiliateMetadata(?string $key = null): mixed
    {
        $attribution = app(AffiliateLookup::class)->findAttachedAttribution($this->getUnderlyingCart());

        if ($attribution === null) {
            return null;
        }

        $metadata = $attribution->metadata ?? [];

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
