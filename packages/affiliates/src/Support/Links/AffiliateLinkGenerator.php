<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Links;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final class AffiliateLinkGenerator
{
    /**
     * @param  array<string, string>  $params
     */
    public function generate(string $affiliateCode, string $url, array $params = [], ?int $ttlSeconds = null): string
    {
        $this->assertHostAllowed($url);

        foreach ($params as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException(sprintf('Link parameter [%s] must be a scalar value.', $key));
            }
        }

        $parameter = (string) config('affiliates.links.parameter', 'aff');
        $expires = $ttlSeconds === null
            ? (int) CarbonImmutable::now()->addMinutes((int) config('affiliates.links.default_ttl_minutes', 60 * 24 * 7))->timestamp
            : (int) CarbonImmutable::now()->addSeconds($ttlSeconds)->timestamp;

        $existing = [];
        $parts = parse_url($url) ?: [];

        if (isset($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $existing);
        }

        $query = array_merge($existing, $params, [
            $parameter => $affiliateCode,
            'aff_exp' => $expires,
        ]);
        $query = array_filter($query, static fn (mixed $value): bool => $value !== null);

        $signature = $this->sign($url, $query);
        $query['aff_sig'] = $signature;

        return $this->buildUrl($url, $query);
    }

    public function verify(string $url): bool
    {
        $parts = parse_url($url) ?: [];
        parse_str($parts['query'] ?? '', $query);

        if (! is_array($query)) {
            return false;
        }

        $signature = Arr::pull($query, 'aff_sig');
        $expires = $query['aff_exp'] ?? 0;

        if (! is_string($signature) || $signature === '' || ! is_numeric($expires)) {
            return false;
        }

        $expires = (int) $expires;
        $query['aff_exp'] = $expires;

        if ($expires < CarbonImmutable::now()->timestamp) {
            return false;
        }

        foreach ($query as $value) {
            if (is_array($value)) {
                return false;
            }
        }

        $baseUrl = $this->stripQuery($url);

        return hash_equals($signature, $this->sign($baseUrl, $query));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function buildUrl(string $url, array $query): string
    {
        $parts = parse_url($url) ?: [];
        $existing = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $existing);
        }

        $merged = array_merge($existing, $query);

        $base = $this->stripQuery($url);

        return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($merged);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function sign(string $url, array $query): string
    {
        $key = (string) config('affiliates.links.signing_key', config('app.key'));

        $normalized = [];

        foreach ($query as $name => $value) {
            $normalized[(string) $name] = is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value, JSON_THROW_ON_ERROR);
        }

        ksort($normalized);

        $payload = [
            'url' => $this->stripQuery($url),
            'query' => $normalized,
        ];

        return hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), $key);
    }

    private function stripQuery(string $url): string
    {
        return strtok($url, '?') ?: $url;
    }

    private function assertHostAllowed(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($scheme) || ! in_array(mb_strtolower($scheme), ['http', 'https'], true)) {
            throw new InvalidArgumentException('Link URL scheme must be http or https.');
        }

        $allowed = config('affiliates.links.allowed_hosts', []);

        if (! is_array($allowed) || $allowed === []) {
            $appHost = parse_url((string) config('app.url', ''), PHP_URL_HOST);
            $allowed = is_string($appHost) && $appHost !== '' ? [$appHost] : [];
        }

        if ($allowed === []) {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if ($host && in_array($host, $allowed, true)) {
            return;
        }

        throw new InvalidArgumentException('Link host is not allowed.');
    }
}
