<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Contracts\AffiliateLookup;
use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Cart\Cart;
use Lorisleiva\Actions\Concerns\AsAction;

final class AttachAffiliateFromCookie
{
    use AsAction;

    public function __construct(
        private readonly AttachAffiliateToCart $attachAffiliate,
        private readonly AffiliateLookup $affiliateLookup,
    ) {}

    public function handle(Cart $cart, string $cookieValue, array $context = []): ?AffiliateAttributionData
    {
        $attribution = $this->affiliateLookup->findActiveAttributionByCookie($cookieValue);

        if (! $attribution || ! $attribution->affiliate || ! $attribution->affiliate->isActive()) {
            return null;
        }

        $context = array_merge([
            'cookie_value' => $cookieValue,
            'source' => $context['source'] ?? $attribution->source,
            'medium' => $context['medium'] ?? $attribution->medium,
            'campaign' => $context['campaign'] ?? $attribution->campaign,
            'term' => $context['term'] ?? $attribution->term,
            'content' => $context['content'] ?? $attribution->content,
            'landing_url' => $context['landing_url'] ?? $attribution->landing_url,
            'referrer_url' => $context['referrer_url'] ?? $attribution->referrer_url,
        ], $context);

        return $this->attachAffiliate->handle($attribution->affiliate, $cart, $context);
    }
}
