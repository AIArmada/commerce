<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventChangeLog;

interface RecordsEventChanges
{
    public function recordEventChange(string $changeType, array $oldValue = [], array $newValue = [], array $context = []): EventChangeLog;
}
