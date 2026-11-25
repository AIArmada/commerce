<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use AIArmada\Affiliates\Data\NetworkPostbackResult;
use Carbon\CarbonImmutable;

interface NetworkPostbackGateway
{
    /** @param array<string, string> $subIds */
    public function report(string $linkCode, string $externalReference, int $revenueMinor, CarbonImmutable $networkClickedAt, ?CarbonImmutable $engineTouchAt = null, ?string $currency = null, ?string $couponCode = null, string $eventType = 'purchase', array $subIds = []): NetworkPostbackResult;

    public function adjustConversion(string $linkCode, string $externalReference, string $adjustmentReference, ?int $deltaRevenueMinor = null, ?int $newRevenueMinor = null, ?string $reason = null, ?string $couponCode = null, string $eventType = 'purchase'): NetworkPostbackResult;

    public function voidConversion(string $linkCode, string $externalReference, ?string $reason = null, ?string $couponCode = null, string $eventType = 'purchase'): NetworkPostbackResult;
}
