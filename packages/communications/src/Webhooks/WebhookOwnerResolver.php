<?php

declare(strict_types=1);

namespace AIArmada\Communications\Webhooks;

use AIArmada\Communications\Contracts\WebhookOwnerResolver as WebhookOwnerResolverContract;

final class WebhookOwnerResolver implements WebhookOwnerResolverContract
{
    public function resolve(string $provider, array $payload): ?string
    {
        return null;
    }
}
