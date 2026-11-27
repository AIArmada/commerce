<?php

declare(strict_types=1);

namespace AIArmada\Communications\Webhooks;

use AIArmada\Communications\Contracts\WebhookOwnerResolver as WebhookOwnerResolverContract;
use AIArmada\CommerceSupport\Data\RecordReference;

final class ConfigWebhookOwnerResolver implements WebhookOwnerResolverContract
{
    public function resolve(string $provider, array $payload): ?RecordReference
    {
        return null;
    }
}
