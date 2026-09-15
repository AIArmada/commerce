<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Chip\Feature;

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Events\PurchaseCreated;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Testing\WebhookFactory;
use AIArmada\Chip\Testing\WebhookSimulator;
use AIArmada\Chip\Webhooks\ProcessChipWebhook;
use AIArmada\Chip\Webhooks\WebhookRouter;
use Illuminate\Support\Facades\Event;
use Spatie\WebhookClient\Models\WebhookCall;

beforeEach(function (): void {
    Event::fake();
});

describe('ProcessChipWebhook', function (): void {
    it('processes a paid purchase webhook', function (): void {
        $payload = WebhookFactory::make()->paid()->toArray();

        $webhookCall = WebhookCall::create([
            'name' => Webhook::WEBHOOK_NAME,
            'url' => 'https://example.test/chip/webhooks',
            'payload' => $payload,
        ]);

        $processor = new ProcessChipWebhook($webhookCall);
        $processor->handle();

        Event::assertDispatched(PurchasePaid::class);
        Event::assertDispatched(WebhookReceived::class);
    });

    it('processes a created purchase webhook', function (): void {
        $payload = WebhookFactory::make()->created()->toArray();

        $webhookCall = WebhookCall::create([
            'name' => Webhook::WEBHOOK_NAME,
            'url' => 'https://example.test/chip/webhooks',
            'payload' => $payload,
        ]);

        $processor = new ProcessChipWebhook($webhookCall);
        $processor->handle();

        Event::assertDispatched(PurchaseCreated::class);
    });

    it('processes a refunded payment webhook', function (): void {
        $payload = [
            'event_type' => 'payment.refunded',
            'type' => 'payment',
            'id' => 'payment-refund-123',
            'brand_id' => 'brand-123',
            'status' => 'refunded',
            'is_test' => true,
            'created_on' => time(),
            'updated_on' => time(),
            'client' => ['email' => 'test@example.com'],
            'related_to' => ['type' => 'purchase', 'id' => 'purchase-123'],
            'payment' => [
                'amount' => 10000,
                'currency' => 'MYR',
                'net_amount' => 10000,
                'fee_amount' => 0,
                'pending_amount' => 0,
                'payment_type' => 'refund',
                'is_outgoing' => true,
            ],
        ];

        $webhookCall = WebhookCall::create([
            'name' => Webhook::WEBHOOK_NAME,
            'url' => 'https://example.test/chip/webhooks',
            'payload' => $payload,
        ]);

        $processor = new ProcessChipWebhook($webhookCall);
        $processor->handle();

        Event::assertDispatched(PaymentRefunded::class);
    });

    it('stores webhook record on processing', function (): void {
        $payload = WebhookFactory::make()->paid()->toArray();

        $webhookCall = WebhookCall::create([
            'name' => Webhook::WEBHOOK_NAME,
            'url' => 'https://example.test/chip/webhooks',
            'payload' => $payload,
        ]);

        $processor = new ProcessChipWebhook($webhookCall);
        $processor->handle();

        $webhook = Webhook::query()
            ->withoutOwnerScope()
            ->whereKey($webhookCall->getKey())
            ->first();

        expect($webhook)->not->toBeNull();
        expect($webhook->event_type)->toBe($payload['event_type'] ?? '');
        expect($webhook->status)->toBe('processed');
    });
});

describe('WebhookSimulatorDispatch', function (): void {
    it('dispatches webhook events directly', function (): void {
        $simulator = WebhookSimulator::paid();

        $simulator->dispatch();

        Event::assertDispatched(PurchasePaid::class);
        Event::assertDispatched(WebhookReceived::class);
    });

    it('dispatches created webhook events', function (): void {
        WebhookSimulator::created()->dispatch();

        Event::assertDispatched(PurchaseCreated::class);
    });
});

describe('WebhookRouter', function (): void {
    it('routes payout.success through the typed dispatcher', function (): void {
        $payload = EnrichedWebhookPayload::fromPayload('payout.success', WebhookFactory::payoutSuccess());

        $router = new WebhookRouter;
        $result = $router->route('payout.success', $payload);

        expect($result->isHandled())->toBeTrue();
    });
});
