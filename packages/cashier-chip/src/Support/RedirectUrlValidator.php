<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Support;

use InvalidArgumentException;

/**
 * Validates merchant redirect/callback URLs handed to CHIP.
 *
 * These URLs drive browser redirects (success/failure/cancel) and CHIP
 * server callbacks, so they must be absolute http(s) URLs. Hosts may be
 * further restricted via `cashier-chip.redirects.allowed_hosts`; an empty
 * list allows any host.
 */
final class RedirectUrlValidator
{
    public static function assertValid(?string $url, string $option): void
    {
        if ($url === null) {
            return;
        }

        $candidate = mb_trim($url);

        if ($candidate === '' || filter_var($candidate, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("The {$option} option must be a valid absolute URL.");
        }

        $scheme = mb_strtolower((string) parse_url($candidate, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException("The {$option} option scheme must be http or https.");
        }

        $allowedHosts = config('cashier-chip.redirects.allowed_hosts', []);

        if (! is_array($allowedHosts) || $allowedHosts === []) {
            return;
        }

        $host = (string) parse_url($candidate, PHP_URL_HOST);

        if ($host === '' || ! in_array($host, $allowedHosts, true)) {
            throw new InvalidArgumentException("The {$option} option host is not allowed.");
        }
    }
}
