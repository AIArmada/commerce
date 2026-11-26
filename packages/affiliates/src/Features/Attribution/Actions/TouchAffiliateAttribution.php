<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Actions;

use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionActivity;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateAttribution;
use AIArmada\Affiliates\Features\Attribution\Queries\ReadAttributions;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use AIArmada\Affiliates\Support\IpHasher;
use Carbon\CarbonImmutable;

final readonly class TouchAffiliateAttribution implements AttributionActivity
{
    public function __construct(
        private ReadAttributions $affiliateLookup,
    ) {}

    public function touch(string $cookieValue, array $context = []): ?AffiliateAttributionData
    {
        $attribution = $this->affiliateLookup->findActiveAttributionByCookie($cookieValue);

        if (! $attribution) {
            return null;
        }

        return $attribution->getConnection()->transaction(function () use ($attribution, $context): ?AffiliateAttributionData {
            $affiliate = app(AffiliateProfileReader::class)->lock($attribution->affiliate_id, spl_object_id($attribution->getConnection()));

            if ($affiliate === null || ! $affiliate->canBeAttributed) {
                return null;
            }

            $locked = AffiliateAttribution::query()
                ->forOwner()
                ->where('cookie_value', $attribution->cookie_value)
                ->active()
                ->lockForUpdate()
                ->find($attribution->getKey());

            if ($locked === null || $locked->affiliate_id !== $affiliate->reference->id
                || $locked->owner_type !== $affiliate->reference->ownerType
                || $locked->owner_id !== $affiliate->reference->ownerId) {
                return null;
            }

            $this->fillAttribution($locked, $this->buildTouchPayload($locked, $context));

            $now = CarbonImmutable::now();
            $locked->last_seen_at = $now;
            $locked->last_cookie_seen_at = $now;

            if ($locked->isDirty()) {
                $locked->save();
            }

            return $locked->toData();
        }, attempts: 3);
    }

    private function buildTouchPayload(AffiliateAttribution $attribution, array $context): array
    {
        $ttl = (int) config('affiliates.tracking.attribution_ttl_days', 30);
        $expiresAt = $ttl > 0 ? CarbonImmutable::now()->addDays($ttl) : null;

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
            'ip_address' => array_key_exists('ip_address', $context)
                ? IpHasher::hash($context['ip_address'])
                : $attribution->ip_address,
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
}
