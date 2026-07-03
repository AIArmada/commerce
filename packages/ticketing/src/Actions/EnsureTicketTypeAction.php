<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Actions;

use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Model;

final class EnsureTicketTypeAction
{
    /** @param array<string, mixed> $attributes */
    public function handle(Model $ticketable, array $attributes = []): TicketType
    {
        $code = blank($attributes['code'] ?? null)
            ? $ticketable->getKey()
            : $attributes['code'];

        $ticketType = TicketType::query()->firstOrNew([
            'ticketable_id' => $ticketable->getKey(),
            'ticketable_type' => $ticketable->getMorphClass(),
            'code' => $code,
        ]);

        $ticketType->fill([
            'name' => $attributes['name'] ?? $code,
            'description' => $attributes['description'] ?? null,
            'access_type' => $attributes['access_type'] ?? 'general',
            'seating_mode' => $attributes['seating_mode'] ?? null,
            'price' => $attributes['price'] ?? null,
            'currency' => $attributes['currency'] ?? config('ticketing.defaults.currency', 'MYR'),
            'admits_quantity' => $attributes['admits_quantity'] ?? 1,
            'min_quantity' => $attributes['min_quantity'] ?? null,
            'max_quantity' => $attributes['max_quantity'] ?? null,
            'sales_starts_at' => $attributes['sales_starts_at'] ?? null,
            'sales_ends_at' => $attributes['sales_ends_at'] ?? null,
            'status' => $attributes['status'] ?? 'active',
            'visibility' => $attributes['visibility'] ?? 'public',
            'sort_order' => $attributes['sort_order'] ?? 0,
            'metadata' => $attributes['metadata'] ?? null,
        ]);

        if ($ticketType->isDirty() || ! $ticketType->exists) {
            $ticketType->save();
        }

        return $ticketType;
    }
}
