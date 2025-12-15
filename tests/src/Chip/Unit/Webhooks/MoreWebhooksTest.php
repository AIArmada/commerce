<?php

declare(strict_types=1);

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Webhooks\Handlers\PurchasePaidHandler;
use AIArmada\Chip\Webhooks\WebhookEnricher;
use AIArmada\Chip\Webhooks\WebhookRetryManager;
use AIArmada\Chip\Webhooks\WebhookRouter;

describe('WebhookRouter', function (): void {
    it('can be instantiated', function (): void {
        $router = new WebhookRouter;
        expect($router)->toBeInstanceOf(WebhookRouter::class);
    });

    it('routes purchase.paid to correct handler', function (): void {
        $router = new WebhookRouter;
        $payload = new EnrichedWebhookPayload(
            event: 'purchase.paid',
            rawPayload: ['id' => 'purch_123'],
            localPurchase: null,
        );

        $result = $router->route('purchase.paid', $payload);

        // Handler skips because localPurchase is null
        expect($result)->toBeInstanceOf(WebhookResult::class)
            ->and($result->isSkipped())->toBeTrue();
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
            ->and($router->hasHandler('purchase.cancelled'))->toBeTrue()
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
            ->and($handlers)->toHaveKey('purchase.paid')
            ->and($handlers)->toHaveKey('purchase.cancelled');
    });
});

describe('WebhookRetryManager', function (): void {
    it('can be instantiated', function (): void {
        $enricher = new WebhookEnricher;
        $router = new WebhookRouter;
        $manager = new WebhookRetryManager($enricher, $router);

        expect($manager)->toBeInstanceOf(WebhookRetryManager::class);
    });

    it('determines if webhook should retry', function (): void {
        $enricher = new WebhookEnricher;
        $router = new WebhookRouter;
        $manager = new WebhookRetryManager($enricher, $router);

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
        $enricher = new WebhookEnricher;
        $router = new WebhookRouter;
        $manager = new WebhookRetryManager($enricher, $router);

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
        $enricher = new WebhookEnricher;
        $router = new WebhookRouter;
        $manager = new WebhookRetryManager($enricher, $router);

        $result = $manager->setBackoffSchedule([1 => 30, 2 => 120]);

        expect($result)->toBe($manager);

        $webhook = new Webhook;
        $webhook->forceFill(['retry_count' => 0]);

        // Should now use custom schedule
        expect($manager->getNextRetryDelay($webhook))->toBe(30);
    });
});
