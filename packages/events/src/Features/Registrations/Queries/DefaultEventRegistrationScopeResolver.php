<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Queries;

use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Data\EventRegistrationScopeData;
use AIArmada\Events\Data\RegistrationMode;
use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Ticketing\Data\PricingMode;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

final class DefaultEventRegistrationScopeResolver implements EventRegistrationScopeResolver
{
    public function __construct(
        private readonly CatalogApi $catalog,
    ) {}

    public function resolve(string $targetType, string $targetId): EventRegistrationScopeData
    {
        $scope = $this->catalog->describeScope($targetType, $targetId);

        if ($scope === null) {
            if (in_array($targetType, ['event', 'occurrence', 'session'], true)) {
                throw new AuthorizationException(
                    'The referenced event scope is not accessible in the current owner scope.',
                );
            }

            throw new InvalidArgumentException(
                sprintf('Target must be an event, event occurrence, or event session; got [%s].', $targetType),
            );
        }

        return new EventRegistrationScopeData(
            eventId: $scope->eventId,
            occurrenceId: $scope->occurrenceId,
            sessionId: $scope->sessionId,
            pricingMode: PricingMode::from($scope->pricingMode),
            registrationMode: RegistrationMode::from($scope->registrationMode),
            shouldIssuePasses: $scope->shouldIssuePassesForFree,
            capacity: $scope->capacity,
        );
    }
}
