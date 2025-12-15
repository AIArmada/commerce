<?php

declare(strict_types=1);

use AIArmada\Cashier\Events\WebhookHandled;
use AIArmada\Cashier\Events\WebhookReceived;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use Illuminate\Http\Request;

uses(CashierTestCase::class);

describe('Webhook Events', function (): void {
    describe('WebhookHandled', function (): void {
        it('can be instantiated with gateway and payload', function (): void {
            $event = new WebhookHandled('stripe', ['type' => 'payment_intent.succeeded']);

            expect($event)->toBeInstanceOf(WebhookHandled::class)
                ->and($event->gateway)->toBe('stripe')
                ->and($event->payload)->toBe(['type' => 'payment_intent.succeeded']);
        });

        it('returns gateway via method', function (): void {
            $event = new WebhookHandled('chip', ['event' => 'purchase.completed']);

            expect($event->gateway())->toBe('chip');
        });

        it('returns payload via method', function (): void {
            $payload = ['type' => 'invoice.paid', 'data' => ['id' => 'inv_xxx']];
            $event = new WebhookHandled('stripe', $payload);

            expect($event->payload())->toBe($payload);
        });
    });

    describe('WebhookReceived', function (): void {
        it('can be instantiated with gateway and payload', function (): void {
            $event = new WebhookReceived('stripe', ['type' => 'customer.created']);

            expect($event)->toBeInstanceOf(WebhookReceived::class)
                ->and($event->gateway)->toBe('stripe')
                ->and($event->payload)->toBe(['type' => 'customer.created']);
        });

        it('can be instantiated with optional request', function (): void {
            $request = Request::create('/webhook', 'POST');
            $event = new WebhookReceived('chip', ['event' => 'payment'], $request);

            expect($event->request)->toBe($request);
        });

        it('returns gateway via method', function (): void {
            $event = new WebhookReceived('chip', ['event' => 'purchase.completed']);

            expect($event->gateway())->toBe('chip');
        });

        it('returns payload via method', function (): void {
            $payload = ['type' => 'subscription.created', 'id' => 'sub_xxx'];
            $event = new WebhookReceived('stripe', $payload);

            expect($event->payload())->toBe($payload);
        });

        it('returns event type from payload with type key', function (): void {
            $event = new WebhookReceived('stripe', ['type' => 'customer.subscription.created']);

            expect($event->eventType())->toBe('customer.subscription.created');
        });

        it('returns event type from payload with event key', function (): void {
            $event = new WebhookReceived('chip', ['event' => 'purchase.paid']);

            expect($event->eventType())->toBe('purchase.paid');
        });

        it('returns null when no event type in payload', function (): void {
            $event = new WebhookReceived('stripe', ['id' => 'evt_xxx', 'data' => []]);

            expect($event->eventType())->toBeNull();
        });
    });
});
