<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Queries;

use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Data\EventRegistrationScopeData;
use AIArmada\Events\Data\RegistrationMode;
use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Ticketing\Enums\PricingMode;
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
