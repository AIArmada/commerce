<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

interface WebhookOwnerResolver
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolve(string $provider, array $payload): ?string;
}
