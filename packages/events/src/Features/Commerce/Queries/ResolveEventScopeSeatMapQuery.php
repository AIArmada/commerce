<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Queries;

use AIArmada\Events\Contracts\EventSeatingDriver;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use Illuminate\Database\Eloquent\Model;

final class ResolveEventScopeSeatMapQuery
{
    public function __construct(
        private readonly EventSeatingDriver $seating,
    ) {}

    public function forPass(string $passId): ?string
    {
        /** @var Pass|null $pass */
        $pass = Pass::query()->with('ticketable')->find($passId);

        if (! $pass instanceof Pass) {
            return null;
        }

        $scope = $this->passScope($pass);

        if ($scope === null) {
            return null;
        }

        return $this->seating->resolveScopeSeatMapId($scope['type'], $scope['id']);
    }

    public function forScope(string $scopeType, string $scopeId): ?string
    {
        return $this->seating->resolveScopeSeatMapId($scopeType, $scopeId);
    }

    /**
     * @return array{type: string, id: string}|null
     */
    private function passScope(Pass $pass): ?array
    {
        $sessionClass = ModelResolver::sessionClass();
        $occurrenceClass = ModelResolver::occurrenceClass();
        $ticketable = $pass->ticketable;

        $sessionId = $pass->session_id ?? ($ticketable instanceof $sessionClass ? $ticketable->getKey() : null);

        if ($sessionId !== null) {
            return ['type' => 'session', 'id' => (string) $sessionId];
        }

        $occurrenceId = $pass->occurrence_id ?? ($ticketable instanceof $occurrenceClass ? $ticketable->getKey() : null);

        if ($occurrenceId !== null) {
            return ['type' => 'occurrence', 'id' => (string) $occurrenceId];
        }

        if ($ticketable instanceof Model) {
            return ['type' => 'event', 'id' => (string) $ticketable->getKey()];
        }

        return null;
    }
}
