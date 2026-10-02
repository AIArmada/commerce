<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Links;

final class AffiliateLinkDestination
{
    /** @return list<string> */
    public static function allowedHosts(string $destinationUrl): array
    {
        $allowed = array_values((array) config('affiliates.links.allowed_hosts', []));
        if ($allowed !== []) {
            return $allowed;
        }

        $host = mb_strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $targetHost = mb_strtolower((string) parse_url($destinationUrl, PHP_URL_HOST));

        return $host !== '' && ($targetHost === $host || str_ends_with($targetHost, '.' . $host))
            ? [$targetHost] : [$host];
    }
}
