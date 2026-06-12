<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventModerationAction;
use AIArmada\Events\Models\EventReport;

interface EventModerationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function report(mixed $target, array $data): EventReport;

    /**
     * @param  array<string, mixed>  $context
     */
    public function moderate(mixed $target, string $action, array $context = []): EventModerationAction;
}
