<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Events\Contracts\EventSearchRelationProvider;
use AIArmada\Events\Contracts\EventTaxonomyHierarchy;
use AIArmada\Events\Contracts\EventTicketingDriver;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Commerce\Actions\AddEventTicketTypeToCartAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Search\Actions\UpsertEventSearchDocumentAction;
use AIArmada\Events\Features\Search\Models\EventSearchDocument;
use AIArmada\Events\Features\Taxonomy\Actions\SyncEventClassificationsAction;
use AIArmada\Events\Features\Taxonomy\Queries\EventTaxonomyHierarchyService;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Cart\InMemoryStorage;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('events.sync.build_search_documents', true);
    config()->set('events.search.queue_indexing', false);
    config()->set('inventory.owner.enabled', false);
    config()->set('ticketing.owner.enabled', false);
});

it('enforces the search document identity tuple with a unique index', function (): void {
    $attributes = [
        'searchable_type' => Event::class,
        'searchable_id' => (string) Str::uuid(),
        'document_type' => 'event',
        'status' => 'active',
    ];

    EventSearchDocument::query()->create($attributes);

    expect(fn () => EventSearchDocument::query()->create($attributes))
        ->toThrow(QueryException::class);
});

it('clears stale classification facets after the bulk-delete sync path', function (): void {
    $event = Event::factory()->create();

    app(SyncEventClassificationsAction::class)->handle(
        $event,
        ['topic' => ['Fiqh']],
        ['topic' => ['name' => 'Topic']],
    );

    $document = EventSearchDocument::query()
        ->where('event_id', $event->getKey())
        ->where('document_type', 'event')
        ->firstOrFail();

    expect($document->facets['_classifications'] ?? [])->not->toBeEmpty();

    app(SyncEventClassificationsAction::class)->handle($event, []);

    $document->refresh();

    expect($document->facets['_classifications'] ?? [])->toBe([]);
});

it('reuses the loaded search target instead of selecting the event again', function (): void {
    $event = Event::factory()->create();
    $event->load(app(EventSearchRelationProvider::class)->relations());

    DB::flushQueryLog();
    DB::enableQueryLog();

    app(UpsertEventSearchDocumentAction::class)->handleTarget($event, Event::class);

    $eventSelects = array_filter(
        DB::getQueryLog(),
        static fn (array $query): bool => str_contains($query['query'], 'from "' . $event->getTable() . '"'),
    );

    DB::disableQueryLog();

    expect($eventSelects)->toBe([])
        ->and(EventSearchDocument::query()->where('event_id', $event->getKey())->exists())->toBeTrue();
});

it('caches the taxonomy model lookup once per request and flushes it after a mutation', function (): void {
    $event = Event::factory()->create();

    app(SyncEventClassificationsAction::class)->handle(
        $event,
        ['topic' => ['Fiqh']],
        ['topic' => ['name' => 'Topic']],
    );

    $hierarchy = app(EventTaxonomyHierarchy::class);
    EventTaxonomyHierarchyService::flushRequestCache();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $hierarchy->taxonomy('topic');
    $hierarchy->taxonomy('topic');
    $hierarchy->terms('topic');

    $taxonomySelects = array_filter(
        DB::getQueryLog(),
        static fn (array $query): bool => str_contains($query['query'], 'from "' . config('events.database.tables.event_taxonomies') . '"'),
    );

    DB::disableQueryLog();

    expect($taxonomySelects)->toHaveCount(1);

    app(SyncEventClassificationsAction::class)->handle(
        $event,
        ['genre' => ['Lecture']],
        ['genre' => ['name' => 'Genre']],
    );

    expect($hierarchy->taxonomy('genre'))->not->toBeNull();
});

it('reports only passes actually revoked', function (): void {
    $event = Event::factory()->create();
    $ticketType = createEventTicketType($event);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
        'status' => 'confirmed',
    ]);

    $first = createEventPass($ticketType, $registration);
    createEventPass($ticketType, $registration);
    $first->markRevoked('Already revoked');

    $revoked = app(EventTicketingDriver::class)->revokePasses(
        $registration->getMorphClass(),
        (string) $registration->getKey(),
        'Test revoke',
    );

    expect($revoked)->toBe(1);
});

it('restores inventory per location without double-restoring a previously restored location', function (): void {
    $locationA = InventoryLocation::factory()->create();
    $locationB = InventoryLocation::factory()->create();
    $event = Event::factory()->create();
    $ticketType = createEventTicketType($event);
    $reason = 'event_registration_item_refunded:item-multi-location';

    foreach ([$locationA, $locationB] as $location) {
        InventoryMovement::query()->create([
            'inventoryable_type' => $ticketType->getMorphClass(),
            'inventoryable_id' => $ticketType->getKey(),
            'from_location_id' => $location->getKey(),
            'quantity' => 1,
            'type' => MovementType::Shipment->value,
            'reference' => 'order-multi-location',
            'occurred_at' => now(),
        ]);
    }

    app(InventoryService::class)->receive(
        model: $ticketType,
        locationId: (string) $locationA->getKey(),
        quantity: 1,
        reason: $reason,
    );

    app(EventTicketingDriver::class)->restoreRefundedRegistrationItemInventory(
        orderId: 'order-multi-location',
        orderNumber: null,
        registrationItemId: 'item-multi-location',
        ticketTypeId: (string) $ticketType->getKey(),
        quantity: 2,
    );

    $restored = InventoryMovement::query()
        ->where('type', MovementType::Receipt->value)
        ->where('reason', $reason)
        ->where('inventoryable_type', $ticketType->getMorphClass())
        ->where('inventoryable_id', $ticketType->getKey())
        ->selectRaw('to_location_id, SUM(quantity) as restored_quantity')
        ->groupBy('to_location_id')
        ->pluck('restored_quantity', 'to_location_id')
        ->all();

    expect((int) ($restored[$locationA->getKey()] ?? 0))->toBe(1)
        ->and((int) ($restored[$locationB->getKey()] ?? 0))->toBe(1);
});

it('protects server-derived cart identity attributes while keeping extension attributes', function (): void {
    $cart = new Cart(new InMemoryStorage, 'events-cart-attributes');
    $event = Event::factory()->create();
    $ticketType = createEventTicketType($event, ['status' => 'active', 'visibility' => 'public']);

    app(AddEventTicketTypeToCartAction::class)->handle(
        cart: $cart,
        ticketType: $ticketType,
        quantity: 1,
        extraAttributes: [
            'event_id' => 'forged-event',
            'ticket_type_id' => 'forged-ticket-type',
            'note' => 'keep me',
        ],
    );

    $item = $cart->get((string) $ticketType->getKey());

    expect($item)->not->toBeNull()
        ->and($item->getAttribute('event_id'))->toBe($event->getKey())
        ->and($item->getAttribute('ticket_type_id'))->toBe($ticketType->getKey())
        ->and($item->getAttribute('note'))->toBe('keep me');
});
