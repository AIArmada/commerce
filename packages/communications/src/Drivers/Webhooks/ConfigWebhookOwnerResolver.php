<?php

declare(strict_types=1);

namespace AIArmada\Communications\Drivers\Webhooks;

use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\Communications\Contracts\WebhookOwnerResolver as WebhookOwnerResolverContract;

final class ConfigWebhookOwnerResolver implements WebhookOwnerResolverContract
{
    public function resolve(string $provider, array $payload): ?RecordReference
    {
        return null;
    }
}
