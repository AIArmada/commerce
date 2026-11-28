<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Ticketing\Features\TicketTypes\Actions\AddTicketTypeToCartAction;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;
use Illuminate\Database\Eloquent\Relations\Relation;
use Tests\Support\Cart\InMemoryStorage;

beforeEach(function (): void {
    $this->cart = new Cart(new InMemoryStorage, 'ticket-attributes-test');
    $this->ticketType = TicketType::factory()->create([
        'status' => 'active',
        'visibility' => 'public',
        'price' => 5000,
    ]);
});

it('rejects extra attributes that collide with reserved keys', function (): void {
    expect(fn () => app(AddTicketTypeToCartAction::class)->handle(
        $this->cart,
        $this->ticketType,
        extraAttributes: ['code' => 'FORGED'],
    ))->toThrow(InvalidArgumentException::class, 'reserved keys');
});

it('rejects participant lists above the configured maximum', function (): void {
    config()->set('ticketing.cart.max_participants', 2);

    $participants = [
        ['name' => 'One'],
        ['name' => 'Two'],
        ['name' => 'Three'],
    ];

    expect(fn () => app(AddTicketTypeToCartAction::class)->handle(
        $this->cart,
        $this->ticketType,
        participants: $participants,
    ))->toThrow(InvalidArgumentException::class, 'Too many participants');
});

it('rejects participants with invalid emails', function (): void {
    expect(fn () => app(AddTicketTypeToCartAction::class)->handle(
        $this->cart,
        $this->ticketType,
        participants: [['name' => 'Bad Email', 'email' => 'not-an-email']],
    ))->toThrow(InvalidArgumentException::class, 'email');
});

it('stores validated participants and preserves system attributes', function (): void {
    $item = app(AddTicketTypeToCartAction::class)->handle(
        $this->cart,
        $this->ticketType,
        quantity: 2,
        participants: [
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
        ],
        extraAttributes: ['note' => 'front row'],
    );

    expect($item->getAttribute('purchasable_type'))->toBe(TicketType::class)
        ->and($item->getAttribute('purchasable_id'))->toBe($this->ticketType->getKey())
        ->and($item->getAttribute('code'))->toBe($this->ticketType->code)
        ->and($item->getAttribute('participants'))->toHaveCount(2)
        ->and($item->getAttribute('note'))->toBe('front row');
});

it('refreshes ticket price on repeated additions', function (): void {
    $action = app(AddTicketTypeToCartAction::class);
    $action->handle($this->cart, $this->ticketType);
    $this->ticketType->fresh()->update(['price' => 7500]);

    $item = $action->handle($this->cart, $this->ticketType);

    expect($item->quantity)->toBe(2)
        ->and($item->price)->toBe(7500);
});

it('rejects a stale ticket type after it becomes unavailable', function (): void {
    $this->ticketType->fresh()->update(['visibility' => 'hidden']);

    expect(fn () => app(AddTicketTypeToCartAction::class)->handle($this->cart, $this->ticketType))
        ->toThrow(InvalidArgumentException::class, 'not available');
});

it('rejects forged bundle source attributes', function (): void {
    expect(fn () => app(AddTicketTypeToCartAction::class)->handle(
        $this->cart,
        $this->ticketType,
        extraAttributes: ['bundle_parent_line_id' => 'forged'],
    ))->toThrow(InvalidArgumentException::class, 'reserved keys');
});

it('stores the configured ticket type morph alias on the cart line', function (): void {
    Relation::morphMap(['ticket_type_alias' => TicketType::class]);

    $item = app(AddTicketTypeToCartAction::class)->handle($this->cart, $this->ticketType);

    expect($item->getAttribute('purchasable_type'))->toBe('ticket_type_alias');

    Relation::morphMap([], false);
});
