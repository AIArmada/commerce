<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Contracts\EventTicketingDriver;
use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Platform\EventWriteGuard;
use InvalidArgumentException;

final class EnsureTicketTypeForOccurrenceAction
{
    public function __construct(
        private readonly EventTicketingDriver $ticketing,
        private readonly CatalogApi $catalog,
    ) {}

    /**
     * Create or update a TicketType for the given occurrence or session.
     *
     * When a ticket type with matching code already exists on the target,
     * its mutable fields are updated. Otherwise a new ticket type is created.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(string $scopeType, string $scopeId, array $attributes = []): string
    {
        $scope = $this->catalog->describeScope($scopeType, $scopeId);

        if ($scope === null) {
            throw new InvalidArgumentException(sprintf('Unknown event scope [%s:%s].', $scopeType, $scopeId));
        }

        EventWriteGuard::findOrFail($scope->eventId);

        return $this->ticketing->ensureTicketType($scopeType, $scopeId, [
            'code' => $attributes['code'] ?? $scopeId,
            'name' => $attributes['name'] ?? $scope->title ?? $scopeId,
            'description' => $attributes['description'] ?? null,
            'access_type' => $attributes['access_type'] ?? 'general',
            'seating_mode' => $attributes['seating_mode'] ?? null,
            'price' => $attributes['price'] ?? 0,
            'currency' => $attributes['currency'] ?? config('events.defaults.currency', 'MYR'),
            'admits_quantity' => $attributes['admits_quantity'] ?? 1,
            'min_quantity' => $attributes['min_quantity'] ?? 1,
            'max_quantity' => $attributes['max_quantity'] ?? null,
            'sales_starts_at' => $attributes['sales_starts_at'] ?? null,
            'sales_ends_at' => $attributes['sales_ends_at'] ?? null,
            'status' => $attributes['status'] ?? 'active',
            'visibility' => $attributes['visibility'] ?? 'public',
            'sort_order' => $attributes['sort_order'] ?? 0,
            'metadata' => $attributes['metadata'] ?? null,
            'quota' => $attributes['quota'] ?? $scope->capacity ?? 0,
        ]);
    }
}
