<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Contracts\ConsentResolver;
use AIArmada\Communications\Contracts\PreferenceResolver;
use AIArmada\Communications\Contracts\QuietHoursResolver;
use AIArmada\Communications\Contracts\RateLimiter;
use AIArmada\Communications\Contracts\SuppressionResolver;
use AIArmada\Communications\Data\ConsentDecisionData;
use AIArmada\Communications\Data\SuppressionDecisionData;

final class ResolveCommunicationEligibilityAction
{
    public function __construct(
        private readonly SuppressionResolver $suppressionResolver,
        private readonly ConsentResolver $consentResolver,
        private readonly PreferenceResolver $preferenceResolver,
        private readonly QuietHoursResolver $quietHoursResolver,
        private readonly RateLimiter $rateLimiter,
    ) {}

    /**
     * @return array{suppression: SuppressionDecisionData, consent: ConsentDecisionData, channelEnabled: bool, optedIn: ?bool, inQuietHours: bool, rateLimited: bool, nextAllowedAt: ?string, eligible: bool}
     */
    public function handle(
        ?string $recipientType,
        ?string $recipientId,
        ?string $destinationHash,
        string $channel,
        string $category,
    ): array {
        $suppression = $this->suppressionResolver->resolve(
            recipientType: $recipientType,
            recipientId: $recipientId,
            destinationHash: $destinationHash,
            channel: $channel,
            category: $category,
        );

        $consent = $this->consentResolver->resolve(
            recipientType: $recipientType,
            recipientId: $recipientId,
            channel: $channel,
            category: $category,
        );

        $channelEnabled = $this->preferenceResolver->isEnabled(
            recipientType: $recipientType,
            recipientId: $recipientId,
            channel: $channel,
            category: $category,
        );

        $optedIn = $this->preferenceResolver->isOptedIn(
            recipientType: $recipientType,
            recipientId: $recipientId,
            channel: $channel,
            category: $category,
        );

        $inQuietHours = $this->quietHoursResolver->isInQuietHours(
            recipientType: $recipientType,
            recipientId: $recipientId,
        );

        $rateLimited = $this->rateLimiter->isRateLimited(
            recipientType: $recipientType,
            recipientId: $recipientId,
            channel: $channel,
            category: $category,
        );

        $nextAllowedAt = $this->rateLimiter->nextAllowedAt(
            recipientType: $recipientType,
            recipientId: $recipientId,
            channel: $channel,
            category: $category,
        );

        $eligible = ! $suppression->suppressed
            && $consent->consented
            && $channelEnabled
            && ($optedIn !== false)
            && ! $inQuietHours
            && ! $rateLimited;

        return [
            'suppression' => $suppression,
            'consent' => $consent,
            'channelEnabled' => $channelEnabled,
            'optedIn' => $optedIn,
            'inQuietHours' => $inQuietHours,
            'rateLimited' => $rateLimited,
            'nextAllowedAt' => $nextAllowedAt,
            'eligible' => $eligible,
        ];
    }
}
