<?php

declare(strict_types=1);

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePendingRefund;
use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Testing\WebhookFactory;
use AIArmada\Chip\Webhooks\Handlers\PurchasePaidHandler;
use AIArmada\Chip\Webhooks\WebhookRetryManager;
use AIArmada\Chip\Webhooks\WebhookRouter;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config()->set('chip.owner.enabled', false);
});

describe('WebhookRouter', function (): void {
    it('can be instantiated', function (): void {
        $router = new WebhookRouter;
        expect($router)->toBeInstanceOf(WebhookRouter::class);
    });

    it('replays purchase.paid through the dispatcher fallback', function (): void {
        $router = new WebhookRouter;
        $payload = EnrichedWebhookPayload::fromPayload('purchase.paid', WebhookFactory::purchasePaid());

        Event::fake([WebhookReceived::class, PurchasePaid::class]);

        $result = $router->route('purchase.paid', $payload);

        expect($result)->toBeInstanceOf(WebhookResult::class)
            ->and($result->isHandled())->toBeTrue();

        Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $event): bool => $event->eventType === 'purchase.paid');
        Event::assertDispatched(PurchasePaid::class);
    });

    it('returns skipped for unknown events', function (): void {
        $router = new WebhookRouter;
        $payload = new EnrichedWebhookPayload(
            event: 'unknown.event',
            rawPayload: [],
        );

        $result = $router->route('unknown.event', $payload);

        expect($result->isSkipped())->toBeTrue()
            ->and($result->message)->toContain('No handler');
    });

    it('can check if handler exists', function (): void {
        $router = new WebhookRouter;

        expect($router->hasHandler('purchase.paid'))->toBeTrue()
            ->and($router->hasHandler('purchase.pending_refund'))->toBeTrue()
            ->and($router->hasHandler('unknown.event'))->toBeFalse();
    });

    it('can register custom handler', function (): void {
        $router = new WebhookRouter;
        $router->registerHandler('custom.event', PurchasePaidHandler::class);

        expect($router->hasHandler('custom.event'))->toBeTrue();
    });

    it('returns all registered handlers', function (): void {
        $router = new WebhookRouter;
        $handlers = $router->getHandlers();

        expect($handlers)->toBeArray()
            ->and($handlers)->toHaveKey('payout.success')
            ->and($handlers)->toHaveKey('send_instruction.completed');
    });

    it('replays pending refund events through the dispatcher fallback', function (): void {
        $router = new WebhookRouter;
        $payload = EnrichedWebhookPayload::fromPayload('purchase.pending_refund', WebhookFactory::purchasePendingRefund());

        Event::fake([WebhookReceived::class, PurchasePendingRefund::class]);

        $result = $router->route('purchase.pending_refund', $payload);

        expect($result->isHandled())->toBeTrue();

        Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $event): bool => $event->eventType === 'purchase.pending_refund');
        Event::assertDispatched(PurchasePendingRefund::class);
    });

    it('replays payment.refunded through the dispatcher fallback', function (): void {
        $router = new WebhookRouter;
        $payload = EnrichedWebhookPayload::fromPayload('payment.refunded', WebhookFactory::paymentRefunded());

        Event::fake([WebhookReceived::class, PaymentRefunded::class]);

        $result = $router->route('payment.refunded', $payload);

        expect($result->isHandled())->toBeTrue();

        Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $event): bool => $event->eventType === 'payment.refunded');
        Event::assertDispatched(PaymentRefunded::class, fn (PaymentRefunded $event): bool => $event->getPurchaseId() !== null);
    });
});

describe('WebhookRetryManager', function (): void {
    it('can be instantiated', function (): void {
        $manager = app(WebhookRetryManager::class);

        expect($manager)->toBeInstanceOf(WebhookRetryManager::class);
    });

    it('determines if webhook should retry', function (): void {
        $manager = app(WebhookRetryManager::class);

        // Mock webhook with failed status
        $webhook = new Webhook;
        $webhook->forceFill([
            'status' => 'failed',
            'retry_count' => 0,
        ]);

        expect($manager->shouldRetry($webhook))->toBeTrue();

        // After max retries
        $webhook->forceFill(['retry_count' => 5]);
        expect($manager->shouldRetry($webhook))->toBeFalse();

        // Not failed status
        $webhook->forceFill(['status' => 'processed', 'retry_count' => 0]);
        expect($manager->shouldRetry($webhook))->toBeFalse();
    });

    it('calculates next retry delay', function (): void {
        $manager = app(WebhookRetryManager::class);

        $webhook = new Webhook;
        $webhook->forceFill(['retry_count' => 0]);

        // First retry = 60 seconds
        expect($manager->getNextRetryDelay($webhook))->toBe(60);

        // Second retry = 300 seconds
        $webhook->forceFill(['retry_count' => 1]);
        expect($manager->getNextRetryDelay($webhook))->toBe(300);

        // Third retry = 900 seconds
        $webhook->forceFill(['retry_count' => 2]);
        expect($manager->getNextRetryDelay($webhook))->toBe(900);
    });

    it('can set custom backoff schedule', function (): void {
        $manager = app(WebhookRetryManager::class);

        $result = $manager->setBackoffSchedule([1 => 30, 2 => 120]);

        expect($result)->toBe($manager);

        $webhook = new Webhook;
        $webhook->forceFill(['retry_count' => 0]);

        // Should now use custom schedule
        expect($manager->getNextRetryDelay($webhook))->toBe(30);
    });

    it('retries supported dispatcher events without explicit handlers', function (): void {
        Event::fake([WebhookReceived::class, PurchasePendingRefund::class]);

        $manager = app(WebhookRetryManager::class);

        $webhook = Webhook::forceCreate([
            'title' => 'Pending refund webhook',
            'event' => 'purchase.pending_refund',
            'events' => ['purchase.pending_refund'],
            'payload' => WebhookFactory::purchasePendingRefund([
                'id' => 'purchase-pending-refund-123',
            ]),
            'status' => 'failed',
            'retry_count' => 0,
            'created_on' => time(),
            'updated_on' => time(),
            'callback' => 'http://example.com/webhook',
        ]);

        $result = $manager->retry($webhook);

        expect($result->isHandled())->toBeTrue();

        Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $event): bool => $event->eventType === 'purchase.pending_refund');
        Event::assertDispatched(PurchasePendingRefund::class);

        $webhook->refresh();

        expect($webhook->status)->toBe('processed')
            ->and($webhook->processed)->toBeTrue();
    });

    it('restores owner context when replaying dispatcher-backed events', function (): void {
        config()->set('chip.owner.enabled', true);

        $owner = User::query()->create([
            'name' => 'Retry Owner',
            'email' => 'retry-owner@example.com',
            'password' => 'secret',
        ]);

        $purchaseId = 'purchase-retry-owner-123';

        $manager = app(WebhookRetryManager::class);

        $webhook = OwnerContext::withOwner($owner, function () use ($owner, $purchaseId): Webhook {
            return Webhook::forceCreate([
                'title' => 'Owned pending refund webhook',
                'event' => 'purchase.pending_refund',
                'events' => ['purchase.pending_refund'],
                'payload' => WebhookFactory::purchasePendingRefund([
                    'id' => $purchaseId,
                    '__owner_type' => $owner->getMorphClass(),
                    '__owner_id' => (string) $owner->getKey(),
                ]),
                'status' => 'failed',
                'retry_count' => 0,
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);
        });

        OwnerContext::withOwner(null, function () use ($manager, $webhook): void {
            $result = $manager->retry($webhook);

            expect($result->isHandled())->toBeTrue();
        });

        $storedPurchase = Purchase::query()
            ->withoutOwnerScope()
            ->find($purchaseId);

        expect($storedPurchase)->not->toBeNull()
            ->and($storedPurchase?->owner_type)->toBe($owner->getMorphClass())
            ->and($storedPurchase?->owner_id)->toBe((string) $owner->getKey());
    });
});
