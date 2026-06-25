<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

use Illuminate\Database\Eloquent\Model;

interface WebhookOwnerResolver
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolve(string $provider, array $payload): ?Model;
}
