<?php

declare(strict_types=1);

namespace AIArmada\Chip\Tests\Feature;

use AIArmada\Chip\Actions\DispatchChipWebhookAction;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Events\PurchaseCreated;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Models\SendInstruction;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Testing\WebhookFactory;
use AIArmada\Chip\Testing\WebhookSimulator;
use AIArmada\Chip\Tests\TestCase;
use AIArmada\Chip\Webhooks\ProcessChipWebhook;
use AIArmada\Chip\Webhooks\WebhookEnricher;
use AIArmada\Chip\Webhooks\WebhookRetryManager;
use AIArmada\Chip\Webhooks\WebhookRouter;
use Illuminate\Support\Facades\Event;
use Spatie\WebhookClient\Models\WebhookCall;

uses(TestCase::class);

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

describe('WebhookRetryManager', function (): void {
    it('retries a failed webhook', function (): void {
        $webhook = Webhook::create([
            'name' => Webhook::WEBHOOK_NAME,
            'url' => 'https://example.test/webhooks',
            'title' => 'Failed webhook',
            'event' => 'purchase.paid',
            'events' => ['purchase.paid'],
            'payload' => WebhookFactory::make()->paid()->toArray(),
            'status' => 'failed',
            'last_error' => 'Previous error',
            'retry_count' => 0,
            'created_on' => time(),
            'updated_on' => time(),
            'callback' => 'https://example.test/webhooks',
        ]);

        $manager = new WebhookRetryManager(app(DispatchChipWebhookAction::class));

        $result = $manager->retry($webhook);

        expect($result)->toBeInstanceOf(WebhookResult::class);
    });

    it('determines should retry for failed webhooks', function (): void {
        $webhook = Webhook::create([
            'name' => Webhook::WEBHOOK_NAME,
            'url' => 'https://example.test/webhooks',
            'title' => 'Retryable webhook',
            'event' => 'purchase.paid',
            'events' => ['purchase.paid'],
            'payload' => WebhookFactory::make()->paid()->toArray(),
            'status' => 'failed',
            'retry_count' => 0,
            'created_on' => time(),
            'updated_on' => time(),
            'callback' => 'https://example.test/webhooks',
        ]);

        $manager = new WebhookRetryManager(app(DispatchChipWebhookAction::class));

        expect($manager->shouldRetry($webhook))->toBeTrue();
    });

    it('should not retry processed webhooks', function (): void {
        $webhook = Webhook::create([
            'name' => Webhook::WEBHOOK_NAME,
            'url' => 'https://example.test/webhooks',
            'title' => 'Processed webhook',
            'event' => 'purchase.paid',
            'events' => ['purchase.paid'],
            'payload' => WebhookFactory::make()->paid()->toArray(),
            'status' => 'processed',
            'retry_count' => 0,
            'created_on' => time(),
            'updated_on' => time(),
            'callback' => 'https://example.test/webhooks',
        ]);

        $manager = new WebhookRetryManager(app(DispatchChipWebhookAction::class));

        expect($manager->shouldRetry($webhook))->toBeFalse();
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
    it('routes payout.success to send completed handler', function (): void {
        $instruction = SendInstruction::create([
            'id' => 42,
            'bank_account_id' => 1,
            'state' => 'received',
            'amount' => '500.00',
            'email' => 'test@example.com',
            'description' => 'Test payout',
            'reference' => 'REF-123',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enricher = new WebhookEnricher;
        $payload = $enricher->enrich('payout.success', [
            'id' => (string) $instruction->id,
            'type' => 'payout',
            'status' => 'success',
        ]);

        $router = new WebhookRouter;
        $result = $router->route('payout.success', $payload);

        expect($result->isHandled())->toBeTrue();
    });
});
