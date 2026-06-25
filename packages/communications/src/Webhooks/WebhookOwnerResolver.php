<?php

declare(strict_types=1);

namespace AIArmada\Communications\Webhooks;

use AIArmada\Communications\Contracts\WebhookOwnerResolver as WebhookOwnerResolverContract;
use Illuminate\Database\Eloquent\Model;

final class WebhookOwnerResolver implements WebhookOwnerResolverContract
{
    public function resolve(string $provider, array $payload): ?Model
    {
        return null;
    }
}
