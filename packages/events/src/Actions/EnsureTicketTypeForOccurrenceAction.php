<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Events\Support\EventWriteGuard;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;

final class EnsureTicketTypeForOccurrenceAction
{
    /**
     * Create or update an EventTicketType for the given occurrence or session.
     *
     * When a ticket type with matching code already exists on the target,
     * its mutable fields are updated. Otherwise a new ticket type is created.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(EventOccurrence | EventSession $target, array $attributes = []): EventTicketType
    {
        EventWriteGuard::findOrFail($target->event_id);

        $code = blank($attributes['code'] ?? null)
            ? $target->getKey()
            : $attributes['code'];

        $targetKeyName = $target instanceof EventOccurrence ? 'event_occurrence_id' : 'event_session_id';

        $ticketType = EventTicketType::query()->firstOrNew([
            $targetKeyName => $target->getKey(),
            'code' => $code,
        ]);

        $ticketType->fill([
            'event_id' => $target->event_id,
            'name' => $attributes['name'] ?? $target->title,
            'description' => $attributes['description'] ?? null,
            'access_type' => $attributes['access_type'] ?? 'general',
            'price' => $attributes['price'] ?? 0,
            'currency' => $attributes['currency'] ?? 'MYR',
            'admits_quantity' => $attributes['admits_quantity'] ?? 1,
            'min_quantity' => $attributes['min_quantity'] ?? 1,
            'max_quantity' => $attributes['max_quantity'] ?? null,
            'sales_starts_at' => $attributes['sales_starts_at'] ?? null,
            'sales_ends_at' => $attributes['sales_ends_at'] ?? null,
            'status' => $attributes['status'] ?? 'active',
            'visibility' => $attributes['visibility'] ?? 'public',
            'sort_order' => $attributes['sort_order'] ?? 0,
        ]);

        if ($ticketType->isDirty() || ! $ticketType->exists) {
            $ticketType->save();
        }

        $quantity = (int) ($attributes['quota'] ?? $target->capacity ?? 0);

        if ($quantity > 0) {
            $defaultLocation = InventoryLocation::getOrCreateDefault();

            InventoryLevel::updateOrCreate(
                [
                    'inventoryable_type' => $ticketType->getMorphClass(),
                    'inventoryable_id' => $ticketType->getKey(),
                    'location_id' => $defaultLocation->getKey(),
                ],
                [
                    'quantity_on_hand' => $quantity,
                ],
            );
        }

        return $ticketType;
    }
}
