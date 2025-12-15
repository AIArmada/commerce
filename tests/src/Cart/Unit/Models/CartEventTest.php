<?php

declare(strict_types=1);

use AIArmada\Cart\Models\CartEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CartEvent Model', function (): void {
    it('can be instantiated', function (): void {
        $event = new CartEvent;

        expect($event)->toBeInstanceOf(CartEvent::class);
    });

    it('has correct table name', function (): void {
        $event = new CartEvent;

        expect($event->getTable())->toBe('cart_events');
    });

    it('gets table name from config', function (): void {
        config(['cart.database.events_table' => 'custom_cart_events']);

        $event = new CartEvent;

        expect($event->getTable())->toBe('custom_cart_events');

        // Reset config
        config(['cart.database.events_table' => null]);
    });

    it('has correct fillable attributes', function (): void {
        $event = new CartEvent;

        expect($event->getFillable())->toBe([
            'cart_id',
            'event_type',
            'event_id',
            'payload',
            'metadata',
            'aggregate_version',
            'stream_position',
            'occurred_at',
        ]);
    });

    it('gets payload data as empty array when null', function (): void {
        $event = new CartEvent;
        $event->payload = null;

        expect($event->getPayloadData())->toBe([]);
    });

    it('gets payload data when set', function (): void {
        $event = new CartEvent;
        $event->payload = ['item_id' => 'item-123', 'quantity' => 2];

        expect($event->getPayloadData())->toBe(['item_id' => 'item-123', 'quantity' => 2]);
    });

    it('gets metadata data as empty array when null', function (): void {
        $event = new CartEvent;
        $event->metadata = null;

        expect($event->getMetadataData())->toBe([]);
    });

    it('gets metadata data when set', function (): void {
        $event = new CartEvent;
        $event->metadata = ['user_id' => 'user-456', 'ip' => '127.0.0.1'];

        expect($event->getMetadataData())->toBe(['user_id' => 'user-456', 'ip' => '127.0.0.1']);
    });

    it('checks event type correctly', function (): void {
        $event = new CartEvent;
        $event->event_type = 'item_added';

        expect($event->isType('item_added'))->toBeTrue()
            ->and($event->isType('item_removed'))->toBeFalse();
    });

    it('gets specific payload value', function (): void {
        $event = new CartEvent;
        $event->payload = ['item_id' => 'item-123', 'quantity' => 5];

        expect($event->getPayloadValue('item_id'))->toBe('item-123')
            ->and($event->getPayloadValue('quantity'))->toBe(5)
            ->and($event->getPayloadValue('missing', 'default'))->toBe('default');
    });

    it('gets specific metadata value', function (): void {
        $event = new CartEvent;
        $event->metadata = ['source' => 'web', 'version' => '1.0'];

        expect($event->getMetadataValue('source'))->toBe('web')
            ->and($event->getMetadataValue('version'))->toBe('1.0')
            ->and($event->getMetadataValue('missing', 'N/A'))->toBe('N/A');
    });

    it('returns correct casts', function (): void {
        $event = new CartEvent;
        $casts = $event->getCasts();

        expect($casts['payload'])->toBe('array')
            ->and($casts['metadata'])->toBe('array')
            ->and($casts['aggregate_version'])->toBe('integer')
            ->and($casts['stream_position'])->toBe('integer')
            ->and($casts['occurred_at'])->toBe('datetime');
    });
});
