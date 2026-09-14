<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Ticketing\Actions\AddTicketTypeToCartAction;
use AIArmada\Ticketing\Models\TicketType;
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
