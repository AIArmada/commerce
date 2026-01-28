<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Transformers;

use AIArmada\Checkout\Contracts\SessionDataTransformerInterface;
use AIArmada\Checkout\Models\CheckoutSession;

final class OrderAddressTransformer implements SessionDataTransformerInterface
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transform(array $data, CheckoutSession $session): array
    {
        if ($this->isEmpty($data)) {
            return [];
        }

        [$firstName, $lastName] = $this->extractNames($data);

        $line1 = $data['line1'] ?? null;
        $line2 = $data['line2'] ?? null;

        $city = $data['city'] ?? $data['town'] ?? null;
        $postcode = $data['postcode'] ?? $data['zip'] ?? null;
        $country = $this->normalizeCountry($data);

        return array_filter([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company' => $data['company'] ?? null,
            'line1' => $line1 ?? 'Unknown',
            'line2' => $line2,
            'city' => $city ?? 'Unknown',
            'state' => $data['state'] ?? $data['province'] ?? null,
            'postcode' => $postcode ?? '00000',
            'country' => $country,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'metadata' => is_array($data['metadata'] ?? null) ? $data['metadata'] : null,
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isEmpty(array $data): bool
    {
        foreach (['name', 'full_name', 'first_name', 'last_name', 'email', 'phone', 'line1'] as $key) {
            if (! empty($data[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0:string,1:string}
     */
    private function extractNames(array $data): array
    {
        $firstName = $data['first_name'] ?? null;
        $lastName = $data['last_name'] ?? null;

        if ($firstName !== null && $lastName !== null) {
            return [$firstName, $lastName];
        }

        $fullName = $data['name'] ?? $data['full_name'] ?? null;

        if ($fullName !== null && is_string($fullName)) {
            $parts = array_values(array_filter(explode(' ', mb_trim($fullName))));

            if ($parts !== []) {
                $firstName = $firstName ?? array_shift($parts);
                $lastName = $lastName ?? (implode(' ', $parts) ?: 'Customer');
            }
        }

        return [
            $firstName ?? 'Guest',
            $lastName ?? 'Customer',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function normalizeCountry(array $data): string
    {
        $country = $data['country_code'] ?? $data['country'] ?? 'MY';

        if (! is_string($country)) {
            return 'MY';
        }

        $country = mb_strtoupper(mb_trim($country));

        if (mb_strlen($country) === 2) {
            return $country;
        }

        return 'MY';
    }
}
