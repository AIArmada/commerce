<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Api;

interface CommunicationDependentsCleaner
{
    public function cleanup(string $communicationId): void;
}
