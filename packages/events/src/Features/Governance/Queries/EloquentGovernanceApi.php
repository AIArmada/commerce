<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance\Queries;

use AIArmada\Events\Features\Governance\Actions\ResolveEventEscalationsAction;
use AIArmada\Events\Features\Governance\Api\GovernanceApi;

final class EloquentGovernanceApi implements GovernanceApi
{
    public function __construct(
        private readonly ResolveEventEscalationsAction $resolveEventEscalations,
    ) {}

    public function resolveEscalations(string $eventId): void
    {
        $this->resolveEventEscalations->handle($eventId);
    }
}
