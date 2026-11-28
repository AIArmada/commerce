<?php

declare(strict_types=1);

namespace AIArmada\Communications\Support;

use AIArmada\Communications\Contracts\PayloadRedactor;

class PayloadRedactorService implements PayloadRedactor
{
    private const SENSITIVE_KEYS = [
        'password', 'secret', 'token', 'authorization', 'api_key', 'api_secret',
        'access_key', 'private_key', 'credit_card', 'cvv', 'ssn', 'sin',
        'bank_account', 'routing_number', 'pin', 'passcode',
    ];

    public function redact(array $payload): array
    {
        return $this->redactRecursive($payload, array_fill_keys($this->configuredExactKeys(), true), $this->configuredSensitiveSubstrings());
    }

    public function redactRequest(array $request): array
    {
        return $this->redact($request);
    }

    public function redactResponse(array $response): array
    {
        return $this->redact($response);
    }

    public function redactText(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $redacted = preg_replace(
            '/-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----.*?-----END [A-Z0-9 ]*PRIVATE KEY-----/s',
            '**[REDACTED-PRIVATE-KEY]**',
            $text,
        );

        $redacted = preg_replace(
            '/\bBearer\s+[A-Za-z0-9\-._~+\/=]{8,}/',
            'Bearer **[REDACTED]**',
            $redacted ?? $text,
        );

        $redacted = preg_replace(
            '/\bBasic\s+[A-Za-z0-9+\/=]{8,}/',
            'Basic **[REDACTED]**',
            $redacted ?? $text,
        );

        return $redacted ?? $text;
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @param  array<string, true>  $exactKeys
     * @param  list<string>  $sensitiveSubstrings
     * @return array<int|string, mixed>
     */
    private function redactRecursive(array $data, array $exactKeys, array $sensitiveSubstrings): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitive($key, $exactKeys, $sensitiveSubstrings)) {
                $result[$key] = '**[REDACTED]**';

                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->redactRecursive($value, $exactKeys, $sensitiveSubstrings);

                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param  array<string, true>  $exactKeys
     * @param  list<string>  $sensitiveSubstrings
     */
    private function isSensitive(string $key, array $exactKeys, array $sensitiveSubstrings): bool
    {
        $lower = mb_strtolower($key);

        if (isset($exactKeys[$lower])) {
            return true;
        }

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($lower, $sensitive)) {
                return true;
            }
        }

        foreach ($sensitiveSubstrings as $sensitive) {
            if (str_contains($lower, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function configuredExactKeys(): array
    {
        $configured = config('communications.redaction.exact_keys', []);

        return $this->normalizeKeyList(is_array($configured) ? $configured : []);
    }

    /**
     * @return list<string>
     */
    private function configuredSensitiveSubstrings(): array
    {
        $configured = config('communications.redaction.sensitive_substrings', []);

        return $this->normalizeKeyList(is_array($configured) ? $configured : []);
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return list<string>
     */
    private function normalizeKeyList(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (! is_string($item)) {
                continue;
            }

            $candidate = mb_strtolower(mb_trim($item));

            if ($candidate !== '') {
                $normalized[] = $candidate;
            }
        }

        return array_values(array_unique($normalized));
    }
}
