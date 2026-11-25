<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance\Api;

interface GovernanceApi
{
    public function resolveEscalations(string $eventId): void;
}
