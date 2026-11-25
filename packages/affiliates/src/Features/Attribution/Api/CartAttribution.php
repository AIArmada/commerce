<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Cart\Cart;

interface CartAttribution
{
    /** @param array<string, mixed> $context */
    public function attach(string $affiliateId, Cart $cart, array $context = []): ?AffiliateAttributionData;

    /** @param array<string, mixed> $context */
    public function attachFromCookie(Cart $cart, string $cookieValue, array $context = []): ?AffiliateAttributionData;

    public function detach(Cart $cart): void;
}
