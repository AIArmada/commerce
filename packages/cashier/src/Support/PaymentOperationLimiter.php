<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Support;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Exceptions\PaymentOperationRateLimitedException;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;

final class PaymentOperationLimiter
{
    public static function run(string $gateway, string $operation, BillableContract | string | null $subject, Closure $callback): mixed
    {
        $config = (array) config('cashier.payment_operations.rate_limiting', []);

        if ((bool) ($config['enabled'] ?? true) === false) {
            return $callback();
        }

        $maxAttempts = (int) ($config['max_attempts'] ?? 60);

        if ($maxAttempts < 1) {
            return $callback();
        }

        $decaySeconds = max(1, (int) ($config['decay_seconds'] ?? 60));
        $key = self::key($gateway, $operation, $subject);

        $result = RateLimiter::attempt(
            key: $key,
            maxAttempts: $maxAttempts,
            callback: $callback,
            decaySeconds: $decaySeconds,
        );

        if ($result === false) {
            throw PaymentOperationRateLimitedException::create(
                $gateway,
                $operation,
                RateLimiter::availableIn($key),
            );
        }

        return $result;
    }

    public static function key(string $gateway, string $operation, BillableContract | string | null $subject): string
    {
        return sprintf(
            'cashier:payment-operations:%s:%s:%s',
            $gateway,
            $operation,
            sha1(self::subjectKey($subject)),
        );
    }

    private static function subjectKey(BillableContract | string | null $subject): string
    {
        if ($subject === null) {
            return 'global';
        }

        if (is_string($subject)) {
            return $subject;
        }

        if ($subject instanceof Model) {
            $key = $subject->getKey();

            return $subject->getMorphClass() . ':' . ($key === null ? 'unsaved:' . spl_object_id($subject) : (string) $key);
        }

        return $subject::class . ':' . spl_object_id($subject);
    }
}
