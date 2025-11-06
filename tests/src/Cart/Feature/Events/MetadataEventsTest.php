<?php

declare(strict_types=1);

use AIArmada\Cart\Events\MetadataAdded;
use AIArmada\Cart\Events\MetadataRemoved;
use AIArmada\Cart\Facades\Cart;
use Illuminate\Support\Facades\Event;

describe('Metadata Events Dispatch', function (): void {
    beforeEach(function (): void {
        Event::fake(); // Fake events BEFORE any cart operations
        Cart::clear();
    });

    it('dispatches MetadataAdded event when adding metadata', function (): void {
        Cart::setMetadata('customer_note', 'Please gift wrap this order');

        Event::assertDispatched(MetadataAdded::class, function (MetadataAdded $event) {
            return $event->key === 'customer_note' &&
                   $event->value === 'Please gift wrap this order' &&
                   $event->cart instanceof AIArmada\Cart\Cart;
        });
    });

    it('dispatches MetadataRemoved event when removing metadata', function (): void {
        Cart::setMetadata('promo_code', 'SAVE20');

        Cart::removeMetadata('promo_code');

        Event::assertDispatched(MetadataRemoved::class, function (MetadataRemoved $event) {
            return $event->key === 'promo_code' &&
                   $event->cart instanceof AIArmada\Cart\Cart;
        });
    });

    it('includes comprehensive data in MetadataAdded event', function (): void {
        Cart::setMetadata('shipping_preference', 'express');

        Event::assertDispatched(MetadataAdded::class, function (MetadataAdded $event) {
            $data = $event->toArray();

            return isset($data['key']) &&
                   isset($data['value']) &&
                   isset($data['cart']) &&
                   isset($data['timestamp']) &&
                   $data['key'] === 'shipping_preference' &&
                   $data['value'] === 'express';
        });
    });

    it('includes comprehensive data in MetadataRemoved event', function (): void {
        Cart::setMetadata('gift_message', 'Happy Birthday!');

        Cart::removeMetadata('gift_message');

        Event::assertDispatched(MetadataRemoved::class, function (MetadataRemoved $event) {
            $data = $event->toArray();

            return isset($data['key']) &&
                   isset($data['cart']) &&
                   isset($data['timestamp']) &&
                   $data['key'] === 'gift_message';
        });
    });

    it('dispatches multiple MetadataAdded events when adding multiple metadata', function (): void {
        Cart::setMetadata('key1', 'value1');
        Cart::setMetadata('key2', 'value2');
        Cart::setMetadata('key3', 'value3');

        Event::assertDispatched(MetadataAdded::class, 3);
    });

    it('dispatches MetadataRemoved when removing non-existent metadata', function (): void {
        Cart::removeMetadata('non_existent_key');

        // Should still dispatch the event
        Event::assertDispatched(MetadataRemoved::class);
    });

    it('dispatches events when metadata is updated', function (): void {
        Cart::setMetadata('counter', 1);

        // Updating metadata is essentially adding with same key
        Cart::setMetadata('counter', 2);

        Event::assertDispatched(MetadataAdded::class, function (MetadataAdded $event) {
            return $event->key === 'counter' && $event->value === 2;
        });
    });
});
