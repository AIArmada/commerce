<?php

declare(strict_types=1);

namespace AIArmada\Chip\Actions;

use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Webhooks\WebhookEnricher;
use AIArmada\Chip\Webhooks\WebhookRouter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

class DispatchChipWebhookAction
{
    public function __construct(
        private readonly WebhookEnricher $enricher,
        private readonly WebhookRouter $router,
    ) {}

    public function execute(string $event, array $payload, ?Model $owner = null): WebhookResult
    {
        $enriched = $this->enricher->enrich($event, $payload);
        $resolvedOwner = $owner ?? $enriched->owner;

        $executor = fn (): WebhookResult => $this->router->route($event, $enriched);

        if ($resolvedOwner instanceof Model) {
            return OwnerContext::withOwner($resolvedOwner, $executor);
        }

        return $executor();
    }
}
