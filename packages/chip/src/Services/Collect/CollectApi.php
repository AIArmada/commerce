<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services\Collect;

use AIArmada\Chip\Clients\ChipCollectClient;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class CollectApi
{
    public function __construct(
        protected ChipCollectClient $client
    ) {}

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
