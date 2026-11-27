<?php

declare(strict_types=1);

namespace AIArmada\Communications\Drivers\Eligibility;

use AIArmada\Communications\Contracts\ConsentResolver;
use AIArmada\Communications\Contracts\PreferenceResolver;
use AIArmada\Communications\Contracts\QuietHoursResolver;
use AIArmada\Communications\Contracts\RateLimiter;
use AIArmada\Communications\Data\ConsentDecisionData;
use AIArmada\Communications\Enums\CommunicationCategory;

final class PermissiveEligibilityResolver implements ConsentResolver, PreferenceResolver, QuietHoursResolver, RateLimiter
{
    public function resolveConsent(
        ?string $recipientType,
        ?string $recipientId,
        string $channel,
        string $category,
    ): ConsentDecisionData {
        if ($category === CommunicationCategory::Marketing->value) {
            return new ConsentDecisionData(
                consented: false,
                reason: 'Marketing requires explicit consent; no resolver configured.',
            );
        }

        return new ConsentDecisionData(consented: true);
    }

    public function isEnabled(
        ?string $recipientType,
        ?string $recipientId,
        string $channel,
        string $category,
    ): bool {
        return true;
    }

    public function isOptedIn(
        ?string $recipientType,
        ?string $recipientId,
        string $channel,
        string $category,
    ): ?bool {
        return null;
    }

    public function isInQuietHours(
        ?string $recipientType,
        ?string $recipientId,
    ): bool {
        return false;
    }

    public function nextAllowedAt(
        ?string $recipientType,
        ?string $recipientId,
        ?string $channel = null,
        ?string $category = null,
    ): ?string {
        return null;
    }

    public function isRateLimited(
        ?string $recipientType,
        ?string $recipientId,
        string $channel,
        string $category,
    ): bool {
        return false;
    }
}
