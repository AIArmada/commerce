<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Cart\Cart;
use Carbon\CarbonImmutable;

interface CartConversionRecording
{
    /** @param array<string, mixed> $payload */
    public function record(Cart $cart, array $payload = []): ?AffiliateConversionData;

    /** @param array<string, mixed> $payload */
    public function recordFrozen(Cart $cart, string $attributionId, array $payload = []): ?AffiliateConversionData;

    public function attributionTouchFor(Cart $cart): ?CarbonImmutable;
}
