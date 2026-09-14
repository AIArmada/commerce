<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services\Collect;

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Exceptions\ChipValidationException;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class CollectApi
{
    public function __construct(
        protected ChipCollectClient $client
    ) {}

    /**
     * Reject path segments that could escape the intended endpoint.
     *
     * CHIP identifiers are UUIDs; the accepted alphabet stays wider so test
     * doubles and future id shapes keep working, but slashes, dots, and
     * whitespace never reach the outbound URL.
     */
    protected function assertSafePathSegment(string $value, string $field): void
    {
        if ($value !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $value) === 1) {
            return;
        }

        throw new ChipValidationException("{$field} contains characters that are not allowed in a CHIP resource identifier.");
    }

    /**
     * Execute the given operation while logging any thrown exception.
     *
     * @param  array<string, mixed>  $context
     */
    protected function attempt(callable $operation, string $message, array $context = []): mixed
    {
        try {
            return $operation();
        } catch (Throwable $exception) {
            Log::error($message, array_merge($context, [
                'error' => $exception->getMessage(),
            ]));

            throw $exception;
        }
    }
}
