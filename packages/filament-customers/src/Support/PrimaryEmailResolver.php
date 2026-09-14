<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Support;

use AIArmada\Customers\Models\Customer;
use Carbon\CarbonImmutable;

/**
 * Resolve a customer's primary email from the already-loaded
 * `contactMethods` relation instead of issuing a fresh query per row
 * (Customer::resolveEmail() always queries).
 *
 * Mirrors HasContactMethods::resolveContact('email') semantics: email type,
 * normalized value present, within the validity window, primary first then
 * lowest sort order.
 */
final class PrimaryEmailResolver
{
    public static function resolve(Customer $customer): ?string
    {
        $customer->loadMissing('contactMethods');

        $now = CarbonImmutable::now();

        /** @var mixed $contact */
        $contact = $customer->contactMethods
            ->where('type', 'email')
            ->whereNotNull('normalized_value')
            ->filter(static fn (mixed $method): bool => self::isCurrentlyValid($method, $now))
            ->sortBy([
                ['is_primary', 'desc'],
                ['sort_order', 'asc'],
            ])
            ->first();

        if ($contact === null) {
            return null;
        }

        $value = $contact->normalized_value ?? $contact->value;

        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }

    private static function isCurrentlyValid(mixed $method, CarbonImmutable $now): bool
    {
        $validFrom = $method->valid_from ?? null;
        $validUntil = $method->valid_until ?? null;

        if ($validFrom instanceof CarbonImmutable && $validFrom->greaterThan($now)) {
            return false;
        }

        if ($validUntil instanceof CarbonImmutable && $validUntil->lessThan($now)) {
            return false;
        }

        return true;
    }
}
