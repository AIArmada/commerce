<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support;

/**
 * One-way pseudonymisation for stored IP addresses.
 *
 * Deterministic so equality-based fraud checks (unique-visitor counts,
 * IP-change detection) keep working without persisting raw addresses.
 */
final class IpHasher
{
    public static function hash(?string $ip): ?string
    {
        if (! is_string($ip) || $ip === '') {
            return null;
        }

        return hash('sha256', 'affiliates-ip-v1|' . $ip);
    }
}
