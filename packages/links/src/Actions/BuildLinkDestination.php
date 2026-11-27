<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Models\Link;
use AIArmada\Links\Support\LinkAttributes;

final readonly class BuildLinkDestination
{
    /** @param array<string, mixed> $incomingQuery */
    public function handle(Link $link, array $incomingQuery): string
    {
        $parts = parse_url($link->destination_url) ?: [];
        $query = [];

        if (isset($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $defaults = is_array($link->utm_defaults) ? $link->utm_defaults : [];

        foreach (LinkAttributes::UTM_KEYS as $key) {
            if (! isset($query[$key]) && isset($defaults[$key]) && is_string($defaults[$key]) && $defaults[$key] !== '') {
                $query[$key] = $defaults[$key];
            }
        }

        foreach ($incomingQuery as $key => $value) {
            if (
                ! is_string($key)
                || mb_strlen($key) > 100
                || preg_match('/\Autm_[A-Za-z0-9_]+\z/i', $key) !== 1
                || ! is_string($value)
                || $value === ''
                || mb_strlen($value) > 500
            ) {
                continue;
            }

            $query[$key] = $value;
        }

        // Ad click IDs pass through so merchants keep their own ad attribution.
        foreach (LinkAttributes::CLICK_ID_KEYS as $key) {
            $incoming = $incomingQuery[$key] ?? null;

            if (is_string($incoming) && $incoming !== '' && mb_strlen($incoming) <= 500) {
                $query[$key] = $incoming;
            }
        }

        // Dynamic sub IDs let one link carry per-affiliate tracking through to
        // the merchant; explicit link parameters below still win.
        foreach (['sub1', 'sub2', 'sub3'] as $key) {
            $incoming = $incomingQuery[$key] ?? null;

            if (is_string($incoming) && $incoming !== '' && mb_strlen($incoming) <= 255) {
                $query[$key] = $incoming;
            }
        }

        // Link parameters are the owner's explicit intent: they always win,
        // so request query strings can never spoof attribution values.
        $parameters = is_array($link->parameters) ? $link->parameters : [];

        foreach ($parameters as $key => $value) {
            if (is_string($key) && $key !== '' && is_string($value) && $value !== '') {
                $query[$key] = $value;
            }
        }

        $url = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');

        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }

        $url .= $parts['path'] ?? '';

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        if (isset($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }

        return $url;
    }
}
