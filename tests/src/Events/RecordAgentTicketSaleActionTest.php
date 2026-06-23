<?php

declare(strict_types=1);

use AIArmada\Events\Actions\RecordAgentTicketSaleAction;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('events.features.enforce_scope_capacity_on_paid_registrations', true);
    config()->set('inventory.owner.enabled', false);
});

it('blocks agent ticket sales when the scope does not have enough capacity', function (): void {
    $defaultLocation = InventoryLocation::getOrCreateDefault();

    $event = Event::factory()->paid()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'capacity' => 1,
    ]);
    $ticketType = EventTicketType::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'status' => 'active',
    ]);

    InventoryLevel::factory()->create([
        'inventoryable_type' => $ticketType->getMorphClass(),
        'inventoryable_id' => $ticketType->getKey(),
        'location_id' => $defaultLocation->id,
        'quantity_on_hand' => 10,
    ]);

    app(RecordAgentTicketSaleAction::class)->handle($ticketType, 2);
})->throws(EventCapacityExceededException::class);
