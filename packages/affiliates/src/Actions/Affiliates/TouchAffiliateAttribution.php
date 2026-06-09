<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use Illuminate\Contracts\Encryption\DecryptException;
use Lorisleiva\Actions\Concerns\AsAction;

final class TouchAffiliateAttribution
{
    use AsAction;

    public function handle(string $cookieValue, array $context = []): ?AffiliateAttributionData
    {
        $attribution = $this->findAttributionByCookie($cookieValue);

        if (! $attribution) {
            return null;
        }

        $attribution->loadMissing('affiliate');

        if (! $attribution->affiliate) {
            return null;
        }

        $payload = $this->buildTouchPayload($attribution, $context);
        $this->fillAttribution($attribution, $payload);

        $attribution->last_cookie_seen_at = now();
        $attribution->save();

        return AffiliateAttributionData::fromModel($attribution);
    }

    private function buildTouchPayload(AffiliateAttribution $attribution, array $context): array
    {
        $ttl = (int) config('affiliates.tracking.attribution_ttl_days', 30);
        $expiresAt = $ttl > 0 ? now()->addDays($ttl) : null;

        $payload = [
            'cart_identifier' => $context['cart_identifier'] ?? $attribution->cart_identifier,
            'cart_instance' => $context['cart_instance'] ?? $attribution->cart_instance,
            'cookie_value' => $attribution->cookie_value,
            'voucher_code' => $context['voucher_code'] ?? $attribution->voucher_code,
            'source' => $context['source'] ?? $context['utm_source'] ?? $attribution->source,
            'medium' => $context['medium'] ?? $context['utm_medium'] ?? $attribution->medium,
            'campaign' => $context['campaign'] ?? $context['utm_campaign'] ?? $attribution->campaign,
            'term' => $context['term'] ?? $context['utm_term'] ?? $attribution->term,
            'content' => $context['content'] ?? $context['utm_content'] ?? $attribution->content,
            'landing_url' => $context['landing_url'] ?? $attribution->landing_url,
            'referrer_url' => $context['referrer_url'] ?? $attribution->referrer_url,
            'user_agent' => $context['user_agent'] ?? $attribution->user_agent,
            'ip_address' => $context['ip_address'] ?? $attribution->ip_address,
            'expires_at' => $expiresAt,
        ];

        return $payload;
    }

    private function fillAttribution(AffiliateAttribution $attribution, array $payload): void
    {
        $nullableKeys = ['expires_at'];

        foreach ($payload as $key => $value) {
            if ($value === null && ! in_array($key, $nullableKeys, true)) {
                unset($payload[$key]);
            }
        }

        if ($payload !== []) {
            $attribution->fill($payload);
        }
    }

    private function findAttributionByCookie(?string $cookieValue): ?AffiliateAttribution
    {
        if (! is_string($cookieValue) || $cookieValue === '') {
            return null;
        }

        $candidates = [$cookieValue];

        try {
            $decrypted = decrypt($cookieValue);

            if ($decrypted !== '') {
                $candidates[] = $decrypted;
            }
        } catch (DecryptException) {
        }

        $candidates = array_values(array_unique($candidates));

        return AffiliateAttribution::query()
            ->with('affiliate')
            ->whereIn('cookie_value', $candidates)
            ->latest('last_cookie_seen_at')
            ->first();
    }
}
