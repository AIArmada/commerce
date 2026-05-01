<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class SiteVerificationService
{
    /**
     * Generate a verification token for a site.
     */
    public function generateToken(AffiliateSite $site): string
    {
        $token = 'affiliatenetwork-verify-' . Str::random(32);

        $site->update([
            'verification_token' => $token,
        ]);

        return $token;
    }

    /**
     * Verify a site using the specified method.
     */
    public function verify(AffiliateSite $site, string $method): bool
    {
        if ($site->verification_token === null) {
            return false;
        }

        $verified = match ($method) {
            'dns' => $this->verifyDns($site),
            'meta_tag' => $this->verifyMetaTag($site),
            'file' => $this->verifyFile($site),
            default => false,
        };

        if ($verified) {
            $site->update([
                'status' => AffiliateSite::STATUS_VERIFIED,
                'verification_method' => $method,
                'verified_at' => now(),
            ]);
        }

        return $verified;
    }

    /**
     * Verify via DNS TXT record.
     */
    private function verifyDns(AffiliateSite $site): bool
    {
        $records = @dns_get_record($site->domain, DNS_TXT);

        if ($records === false) {
            return false;
        }

        foreach ($records as $record) {
            if (isset($record['txt']) && $record['txt'] === $site->verification_token) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify via meta tag on the homepage.
     */
    private function verifyMetaTag(AffiliateSite $site): bool
    {
        $html = $this->fetchRemoteContent($site, '/');

        if ($html === null) {
            return false;
        }

        return str_contains($html, $site->verification_token);
    }

    /**
     * Verify via file at well-known path.
     */
    private function verifyFile(AffiliateSite $site): bool
    {
        $content = $this->fetchRemoteContent($site, '/.well-known/affiliate-network-verify.txt');

        if ($content === null) {
            return false;
        }

        return mb_trim($content) === $site->verification_token;
    }

    private function fetchRemoteContent(AffiliateSite $site, string $path): ?string
    {
        if (! $this->isFetchableDomain($site->domain)) {
            return null;
        }

        $connectTimeout = (int) config('affiliate-network.http.connect_timeout_seconds', 3);
        $timeout = (int) config('affiliate-network.http.timeout_seconds', 5);
        $retries = (int) config('affiliate-network.http.retries', 1);
        $retrySleepMs = (int) config('affiliate-network.http.retry_sleep_ms', 150);

        foreach (['https', 'http'] as $scheme) {
            $url = sprintf('%s://%s%s', $scheme, $site->domain, $path);

            try {
                $response = Http::connectTimeout($connectTimeout)
                    ->timeout($timeout)
                    ->retry($retries, $retrySleepMs, throw: false)
                    ->get($url);

                if ($response->successful()) {
                    return $response->body();
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function isFetchableDomain(string $domain): bool
    {
        $normalizedDomain = Str::lower(mb_trim($domain));

        if ($normalizedDomain === '') {
            return false;
        }

        if (str_contains($normalizedDomain, '/') || str_contains($normalizedDomain, ':')) {
            return false;
        }

        if (in_array($normalizedDomain, ['localhost', 'localhost.localdomain'], true)) {
            return false;
        }

        if (str_ends_with($normalizedDomain, '.local') || str_ends_with($normalizedDomain, '.internal')) {
            return false;
        }

        if (filter_var($normalizedDomain, FILTER_VALIDATE_IP) !== false) {
            return filter_var($normalizedDomain, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $normalizedDomain) === 1;
    }

    /**
     * Get instructions for verification.
     *
     * @return array<string, string>
     */
    public function getInstructions(AffiliateSite $site, string $method): array
    {
        $token = $site->verification_token ?? $this->generateToken($site);

        return match ($method) {
            'dns' => [
                'title' => 'DNS TXT Record',
                'description' => "Add a TXT record to your domain's DNS settings.",
                'record_type' => 'TXT',
                'record_name' => '@',
                'record_value' => $token,
            ],
            'meta_tag' => [
                'title' => 'HTML Meta Tag',
                'description' => 'Add this meta tag to the <head> section of your homepage.',
                'html' => "<meta name=\"affiliate-network-verify\" content=\"{$token}\">",
            ],
            'file' => [
                'title' => 'Verification File',
                'description' => 'Create a file at the following path with the token as contents.',
                'path' => '/.well-known/affiliate-network-verify.txt',
                'content' => $token,
            ],
            default => [],
        };
    }
}
