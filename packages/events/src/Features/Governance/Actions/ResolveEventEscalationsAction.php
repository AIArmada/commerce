<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance\Actions;

use AIArmada\Events\Platform\ModelResolver;
use Carbon\CarbonImmutable;

final class ResolveEventEscalationsAction
{
    public function handle(string $eventId): void
    {
        $escalationClass = ModelResolver::escalationClass();

        $escalationClass::query()
            ->where('event_id', $eventId)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => CarbonImmutable::now()]);
    }
}
