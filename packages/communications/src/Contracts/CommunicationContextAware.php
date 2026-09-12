<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

interface CommunicationContextAware
{
    public function withCommunicationContext(
        string $communicationId,
        array $deliveryIdsByChannel,
        ?string $ownerType = null,
        ?string $ownerId = null,
    ): static;
}
