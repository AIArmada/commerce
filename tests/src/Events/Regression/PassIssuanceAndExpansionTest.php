<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Events\Exceptions\EventRegistrationNotAvailableException;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Commerce\Actions\IssueEventRegistrationPassesAction;
use AIArmada\Events\Features\Commerce\Actions\RecordAgentTicketSaleAction;
use AIArmada\Events\Features\Registrations\Actions\CreateEventComponentRegistrationsAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Inventory\Features\Stock\Actions\SaveInventoryLocation;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\TicketTypeComponent;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('contacting.features.owner.enabled', false);
    config()->set('inventory.owner.enabled', false);
    config()->set('ticketing.owner.enabled', false);
});

it('issues the total quantity across registration items that share a ticket type', function (): void {
    $event = Event::factory()->create();
    $ticketType = createEventTicketType($event, ['admits_quantity' => 1]);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
        'status' => 'confirmed',
    ]);

    $registration->items()->create(['ticket_type_id' => $ticketType->getKey(), 'quantity' => 1]);
    $registration->items()->create(['ticket_type_id' => $ticketType->getKey(), 'quantity' => 1]);

    $passes = app(IssueEventRegistrationPassesAction::class)->handle((string) $registration->getKey());

    expect($passes)->toHaveCount(2)
        ->and(Pass::query()->whereIn('id', $passes)->count())->toBe(2);
});

it('does not issue passes for terminal registrations', function (string $status): void {
    $event = Event::factory()->create();
    $ticketType = createEventTicketType($event);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
        'status' => $status,
        'cancelled_at' => $status === 'cancelled' ? now() : null,
        'refunded_at' => $status === 'refunded' ? now() : null,
    ]);
    $registration->items()->create(['ticket_type_id' => $ticketType->getKey(), 'quantity' => 1]);

    $passes = app(IssueEventRegistrationPassesAction::class)->handle((string) $registration->getKey());

    expect($passes)->toBe([])
        ->and(Pass::query()->count())->toBe(0);
})->with(['cancelled', 'refunded', 'rejected', 'expired']);

it('expands a bundle once, preserves participant contact data and answers, and retains children on retry', function (): void {
    [$parent, $childTicketType] = OwnerContext::withOwner(null, function (): array {
        $event = Event::factory()->paid()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->getKey()]);
        $parentTicketType = createEventTicketType($event, ['price' => 1500]);
        $childTicketType = createEventTicketType($occurrence, ['price' => 0]);

        TicketTypeComponent::query()->create([
            'parent_ticket_type_id' => $parentTicketType->getKey(),
            'component_ticket_type_id' => $childTicketType->getKey(),
            'quantity' => 1,
        ]);

        $parent = EventRegistration::factory()->create([
            'event_id' => $event->getKey(),
            'status' => 'confirmed',
            'total_participants' => 1,
        ]);
        $parent->items()->create(['ticket_type_id' => $parentTicketType->getKey(), 'quantity' => 1]);

        $participant = $parent->participants()->create([
            'event_id' => $event->getKey(),
            'name' => 'Bundle Holder',
            'is_primary' => true,
        ]);
        $participant->addContactMethod(ContactMethodData::email('bundle@example.com'));
        $participant->addContactMethod(ContactMethodData::phone('+60123456789'));
        $participant->answers()->create([
            'event_id' => $event->getKey(),
            'event_registration_id' => $parent->getKey(),
            'field_key' => 'meal',
            'question' => 'Meal',
            'answer' => 'Vegetarian',
        ]);

        return [$parent, $childTicketType];
    });

    $first = app(CreateEventComponentRegistrationsAction::class)->handle((string) $parent->getKey());
    $second = app(CreateEventComponentRegistrationsAction::class)->handle((string) $parent->getKey());

    expect($first)->toHaveCount(1)
        ->and($second)->toBe($first);

    $parent->refresh();
    $child = EventRegistration::query()->findOrFail($first[0]);
    $childParticipant = $child->participants()->firstOrFail();

    expect($parent->is_bundle_root)->toBeTrue()
        ->and($parent->getPassEntitlements())->toHaveCount(1)
        ->and($parent->getPassEntitlements()[0]['event_registration_id'])->toBe($child->getKey())
        ->and($child->parent_registration_id)->toBe($parent->getKey())
        ->and($child->event_occurrence_id)->toBe($childTicketType->ticketable_id)
        ->and($childParticipant->resolveEmail())->toBe('bundle@example.com')
        ->and($childParticipant->resolvePhone())->toBe('+60123456789')
        ->and($childParticipant->answers()->where('field_key', 'meal')->exists())->toBeTrue()
        ->and(EventRegistration::query()->where('parent_registration_id', $parent->getKey())->count())->toBe(1);
});

it('issues passes for both the agent bundle root and its child registrations', function (): void {
    config()->set('events.features.enforce_scope_capacity_on_paid_registrations', true);

    $location = app(SaveInventoryLocation::class)->getOrCreateDefault();
    $event = Event::factory()->paid()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->getKey()]);
    $parentTicketType = createEventTicketType($occurrence, ['price' => 1500, 'status' => 'active']);
    $childTicketType = createEventTicketType($occurrence, ['price' => 0, 'status' => 'active']);

    InventoryLevel::query()->create([
        'inventoryable_type' => $parentTicketType->getMorphClass(),
        'inventoryable_id' => $parentTicketType->getKey(),
        'location_id' => $location->getKey(),
        'quantity_on_hand' => 10,
    ]);

    TicketTypeComponent::query()->create([
        'parent_ticket_type_id' => $parentTicketType->getKey(),
        'component_ticket_type_id' => $childTicketType->getKey(),
        'quantity' => 1,
    ]);

    $ids = app(RecordAgentTicketSaleAction::class)->handle((string) $parentTicketType->getKey(), 1);
    $parent = EventRegistration::query()->findOrFail($ids[0]);
    $child = EventRegistration::query()->where('parent_registration_id', $parent->getKey())->firstOrFail();

    expect($ids)->toHaveCount(1)
        ->and($parent->is_bundle_root)->toBeTrue()
        ->and($parent->passes()->count())->toBe(1)
        ->and($child->passes()->count())->toBe(1);
});

it('rejects agent sales when the event lifecycle is closed', function (): void {
    $location = app(SaveInventoryLocation::class)->getOrCreateDefault();
    $event = Event::factory()->paid()->create(['status' => 'cancelled', 'cancelled_at' => now()]);
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->getKey()]);
    $ticketType = createEventTicketType($occurrence, ['price' => 1500, 'status' => 'active']);

    InventoryLevel::query()->create([
        'inventoryable_type' => $ticketType->getMorphClass(),
        'inventoryable_id' => $ticketType->getKey(),
        'location_id' => $location->getKey(),
        'quantity_on_hand' => 10,
    ]);

    app(RecordAgentTicketSaleAction::class)->handle((string) $ticketType->getKey(), 1);
})->throws(EventRegistrationNotAvailableException::class);
