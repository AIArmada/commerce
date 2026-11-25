<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Changes\Queries;

use AIArmada\Events\Features\Changes\Actions\DispatchEventChangeChainAction;
use AIArmada\Events\Features\Changes\Api\ChangesApi;

final class EloquentChangesApi implements ChangesApi
{
    public function __construct(
        private readonly DispatchEventChangeChainAction $dispatchEventChangeChain,
    ) {}

    /**
     * @param  array<string, mixed>  $oldValue
     * @param  array<string, mixed>  $newValue
     */
    public function dispatchChange(
        string $eventId,
        string $changeType,
        string $changeCategory = 'administration',
        string $impactLevel = 'low',
        bool $requiresNotification = false,
        ?string $reason = null,
        ?string $occurrenceId = null,
        ?string $sessionId = null,
        array $oldValue = [],
        array $newValue = [],
    ): void {
        $this->dispatchEventChangeChain->handle(
            eventId: $eventId,
            changeType: $changeType,
            changeCategory: $changeCategory,
            impactLevel: $impactLevel,
            requiresNotification: $requiresNotification,
            reason: $reason,
            occurrenceId: $occurrenceId,
            sessionId: $sessionId,
            oldValue: $oldValue,
            newValue: $newValue,
        );
    }
}
