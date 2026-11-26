<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

interface TrackingLinkAdministration
{
    /** @param array<string, mixed> $attributes */
    public function create(string $affiliateId, string $destinationUrl, array $attributes = []): TrackingLinkData;

    /** @param array<string, mixed> $attributes */
    public function update(string $linkId, array $attributes): TrackingLinkData;

    public function delete(string $linkId): bool;

    public function deleteForAffiliate(string $affiliateId): void;

    public function setEnabled(string $linkId, bool $enabled): void;

    public function recordClick(string $linkId): void;

    public function recordConversion(string $linkId): void;
}
