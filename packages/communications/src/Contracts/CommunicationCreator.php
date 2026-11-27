<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

use AIArmada\Communications\Data\CommunicationContextData;

interface CommunicationCreator
{
    public function handle(CommunicationContextData $context, bool $queued = false): string;
}
