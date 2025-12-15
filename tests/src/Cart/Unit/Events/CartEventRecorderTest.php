<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Events\Store\CartEventRecorder;
use AIArmada\Cart\Events\Store\CartEventRepositoryInterface;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\CommerceSupport\Contracts\Events\CartEventInterface;

describe('CartEventRecorder', function (): void {
    beforeEach(function (): void {
        $this->repository = Mockery::mock(CartEventRepositoryInterface::class);
        $this->recorder = new CartEventRecorder($this->repository);
    });

    it('can be instantiated', function (): void {
        expect($this->recorder)->toBeInstanceOf(CartEventRecorder::class);
    });

    it('is enabled by default', function (): void {
        expect($this->recorder->isEnabled())->toBeTrue();
    });

    it('can be disabled', function (): void {
        $this->recorder->disable();

        expect($this->recorder->isEnabled())->toBeFalse();
    });

    it('can be enabled after disabled', function (): void {
        $this->recorder->disable();
        $this->recorder->enable();

        expect($this->recorder->isEnabled())->toBeTrue();
    });

    it('returns null when recording disabled', function (): void {
        $event = Mockery::mock(CartEventInterface::class);
        $storage = new InMemoryStorage;
        $cart = new Cart($storage, 'user-123');

        $this->recorder->disable();
        $result = $this->recorder->record($event, $cart);

        expect($result)->toBeNull();
    });

    it('returns empty array when batch recording disabled', function (): void {
        $event = Mockery::mock(CartEventInterface::class);
        $storage = new InMemoryStorage;
        $cart = new Cart($storage, 'user-123');

        $this->recorder->disable();
        $result = $this->recorder->recordBatch([$event], $cart);

        expect($result)->toBeEmpty();
    });

    it('executes callback with recording disabled', function (): void {
        $executed = false;

        $result = $this->recorder->withoutRecording(function () use (&$executed) {
            $executed = true;

            return 'result';
        });

        expect($executed)->toBeTrue()
            ->and($result)->toBe('result')
            ->and($this->recorder->isEnabled())->toBeTrue(); // Restored after callback
    });

    it('restores state after withoutRecording even on exception', function (): void {
        $this->recorder->enable();

        try {
            $this->recorder->withoutRecording(function () {
                throw new Exception('Test exception');
            });
        } catch (Exception) {
            // Expected
        }

        expect($this->recorder->isEnabled())->toBeTrue();
    });

    it('returns zero event count for cart without id', function (): void {
        $this->repository->shouldReceive('getEventCount')->andReturn(0);

        $storage = new InMemoryStorage;
        $cart = new Cart($storage, 'user-no-items');
        $cart->add('item-1', 'Product', 100, 1); // Ensures cart has ID

        $count = $this->recorder->getEventCount($cart);

        expect($count)->toBe(0);
    });

    it('returns empty history for cart with no events', function (): void {
        $this->repository->shouldReceive('getEventsForCart')->andReturn([]);

        $storage = new InMemoryStorage;
        $cart = new Cart($storage, 'user-history');
        $cart->add('item-1', 'Product', 100, 1);

        $history = $this->recorder->getHistory($cart);

        expect($history)->toBeEmpty();
    });

    it('returns empty events by type', function (): void {
        $this->repository->shouldReceive('getEventsByType')->andReturn([]);

        $storage = new InMemoryStorage;
        $cart = new Cart($storage, 'user-events');
        $cart->add('item-1', 'Product', 100, 1);

        $events = $this->recorder->getEventsByType($cart, 'item_added');

        expect($events)->toBeEmpty();
    });
});
