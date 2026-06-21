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
        return $this->redactRecursive($payload);
    }

    public function redactRequest(array $request): array
    {
        return $this->redactRecursive($request);
    }

    public function redactResponse(array $response): array
    {
        return $this->redactRecursive($response);
    }

    private function redactRecursive(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->redactRecursive($value);
            } elseif ($this->isSensitive($key)) {
                $result[$key] = '**[REDACTED]**';
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function isSensitive(string $key): bool
    {
        $lower = mb_strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($lower, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
