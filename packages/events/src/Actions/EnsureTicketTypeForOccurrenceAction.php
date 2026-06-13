<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventTicketType;

final class EnsureTicketTypeForOccurrenceAction
{
    /**
     * Create or update an EventTicketType for the given occurrence.
     *
     * When a ticket type with matching code already exists on the occurrence,
     * its mutable fields are updated. Otherwise a new ticket type is created.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(EventOccurrence $occurrence, array $attributes = []): EventTicketType
    {
        OwnerWriteGuard::findOrFailForOwner(Event::class, $occurrence->event_id);

        $code = blank($attributes['code'] ?? null)
            ? $occurrence->getKey()
            : $attributes['code'];

        $ticketType = EventTicketType::query()->firstOrNew([
            'event_occurrence_id' => $occurrence->getKey(),
            'code' => $code,
        ]);

        $ticketType->fill([
            'event_id' => $occurrence->event_id,
            'name' => $attributes['name'] ?? $occurrence->title,
            'description' => $attributes['description'] ?? null,
            'access_type' => $attributes['access_type'] ?? 'general',
            'price' => $attributes['price'] ?? 0,
            'currency' => $attributes['currency'] ?? 'MYR',
            'quota' => $attributes['quota'] ?? $occurrence->capacity,
            'admits_quantity' => $attributes['admits_quantity'] ?? 1,
            'min_quantity' => $attributes['min_quantity'] ?? 1,
            'max_quantity' => $attributes['max_quantity'] ?? null,
            'sales_starts_at' => $attributes['sales_starts_at'] ?? null,
            'sales_ends_at' => $attributes['sales_ends_at'] ?? null,
            'status' => $attributes['status'] ?? 'active',
            'visibility' => $attributes['visibility'] ?? 'public',
            'sort_order' => $attributes['sort_order'] ?? 0,
        ]);

        if ($ticketType->isDirty()) {
            $ticketType->save();
        }

        return $ticketType;
    }
}
