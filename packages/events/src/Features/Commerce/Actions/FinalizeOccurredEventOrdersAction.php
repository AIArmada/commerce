<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use AIArmada\Events\Platform\EventWriteGuard;
use InvalidArgumentException;

final class FinalizeOccurredEventOrdersAction
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly RegistrationsApi $registrations,
        private readonly FulfillEventOrderAction $fulfillOrder,
    ) {}

    public function handle(string $scopeType, string $scopeId): void
    {
        $scope = $this->catalog->describeScope($scopeType, $scopeId);

        if ($scope === null) {
            throw new InvalidArgumentException(sprintf('Unknown event scope [%s:%s].', $scopeType, $scopeId));
        }

        EventWriteGuard::findOrFail($scope->eventId);

        foreach ($this->registrations->registrationIdsWithOrderLink($scopeType, $scopeId) as $registrationId) {
            $this->fulfillOrder->handle($registrationId);
        }
    }
}
