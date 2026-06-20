<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Support;

final class SocialProfileConfig
{
    public function prefix(string $platform): ?string
    {
        return $this->platformValue($platform, 'prefix');
    }

    public function suffix(string $platform): ?string
    {
        return $this->platformValue($platform, 'suffix');
    }

    public function hasUrlPattern(string $platform): bool
    {
        $config = $this->platformConfig($platform);

        return isset($config['prefix']) || isset($config['suffix']);
    }

    public function buildUrl(string $platform, string $handle): ?string
    {
        $prefix = $this->prefix($platform);
        $suffix = $this->suffix($platform);

        if ($prefix === null && $suffix === null) {
            return null;
        }

        return 'https://' . ($prefix ?? '') . $handle . ($suffix ?? '');
    }

    public function extractHandle(string $platform, string $url): ?string
    {
        $prefix = $this->prefix($platform);
        $suffix = $this->suffix($platform);

        if ($prefix !== null) {
            $handle = $this->extractFromPrefix($prefix, $url);

            if ($handle !== null) {
                return $handle;
            }
        }

        if ($suffix !== null) {
            $handle = $this->extractFromSuffix($suffix, $url);

            if ($handle !== null) {
                return $handle;
            }
        }

        return null;
    }

    private function extractFromPrefix(string $prefix, string $url): ?string
    {
        $schemeCleaned = preg_replace('/^https?:\/\//', '', $url);

        if (str_starts_with((string) $schemeCleaned, $prefix)) {
            $afterPrefix = mb_substr((string) $schemeCleaned, mb_strlen($prefix));
            $handle = explode('/', $afterPrefix)[0];

            if ($handle !== '') {
                return $handle;
            }
        }

        $prefixHost = (string) parse_url('https://' . mb_rtrim($prefix, '/'), PHP_URL_HOST);
        $urlHost = (string) parse_url($url, PHP_URL_HOST);

        if ($prefixHost === '' || $urlHost === '') {
            return null;
        }

        $urlHostClean = (string) preg_replace('/^www\./', '', $urlHost);
        $prefixHostClean = (string) preg_replace('/^www\./', '', $prefixHost);

        if ($urlHostClean !== $prefixHostClean) {
            return null;
        }

        $parsedUrl = parse_url($url);

        if (! is_array($parsedUrl) || ! isset($parsedUrl['path'])) {
            return null;
        }

        $path = mb_ltrim((string) $parsedUrl['path'], '/');

        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path);
        $first = mb_ltrim($segments[0], '@');

        return $first !== '' ? $first : null;
    }

    private function extractFromSuffix(string $suffix, string $url): ?string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        if ($host === '') {
            return null;
        }

        $suffixClean = mb_ltrim($suffix, '.');

        if (str_ends_with($host, '.' . $suffixClean)) {
            $subdomain = mb_substr($host, 0, -mb_strlen('.' . $suffixClean));

            if ($subdomain !== '') {
                return $subdomain;
            }
        }

        return null;
    }

    private function platformValue(string $platform, string $key): ?string
    {
        $config = $this->platformConfig($platform);

        if (is_array($config) && isset($config[$key]) && is_string($config[$key])) {
            return $config[$key];
        }

        return null;
    }

    private function platformConfig(string $platform): ?array
    {
        $config = config('contacting.social_profiles.platforms.' . $platform);

        return is_array($config) ? $config : null;
    }
}
