<?php

declare(strict_types=1);

use AIArmada\Chip\Actions\DispatchChipWebhookAction;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Webhooks\WebhookEnricher;
use AIArmada\Chip\Webhooks\WebhookRetryManager;
use AIArmada\Chip\Webhooks\WebhookRouter;
use Carbon\CarbonImmutable;

final class FakeDispatchAction extends DispatchChipWebhookAction
{
    public ?WebhookResult $result = null;

    public function __construct()
    {
        $enricher = app(WebhookEnricher::class);
        $router = app(WebhookRouter::class);
        parent::__construct($enricher, $router);
    }

    public function execute(string $event, array $payload, mixed $owner = null): WebhookResult
    {
        return $this->result ?? WebhookResult::handled('OK');
    }
}

describe('WebhookRetryManager', function (): void {
    beforeEach(function (): void {
        $this->dispatchAction = new FakeDispatchAction;
        $this->manager = new WebhookRetryManager($this->dispatchAction);
    });

    describe('shouldRetry', function (): void {
        it('returns false for non-failed webhooks', function (): void {
            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['test' => 'data'],
                'status' => 'processed',
                'retry_count' => 0,
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            expect($this->manager->shouldRetry($webhook))->toBeFalse();
        });

        it('returns true for failed webhooks with retries remaining', function (): void {
            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['test' => 'data'],
                'status' => 'failed',
                'retry_count' => 2,
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            expect($this->manager->shouldRetry($webhook))->toBeTrue();
        });

        it('returns false for failed webhooks with max retries reached', function (): void {
            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['test' => 'data'],
                'status' => 'failed',
                'retry_count' => 5,
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            expect($this->manager->shouldRetry($webhook))->toBeFalse();
        });
    });

    describe('getNextRetryDelay', function (): void {
        it('returns correct delay for first retry', function (): void {
            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['test' => 'data'],
                'status' => 'failed',
                'retry_count' => 0,
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            expect($this->manager->getNextRetryDelay($webhook))->toBe(60);
        });

        it('returns correct delay for second retry', function (): void {
            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['test' => 'data'],
                'status' => 'failed',
                'retry_count' => 1,
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            expect($this->manager->getNextRetryDelay($webhook))->toBe(300);
        });

        it('returns last delay for attempts beyond schedule', function (): void {
            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['test' => 'data'],
                'status' => 'failed',
                'retry_count' => 10,
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            expect($this->manager->getNextRetryDelay($webhook))->toBe(14400);
        });
    });

    describe('retry', function (): void {
        it('processes retry successfully and marks webhook as processed', function (): void {
            $this->dispatchAction->result = WebhookResult::handled('Success');

            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['id' => 'purchase-123', 'type' => 'purchase'],
                'status' => 'failed',
                'retry_count' => 1,
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            $result = $this->manager->retry($webhook);

            expect($result->isHandled())->toBeTrue();

            $webhook->refresh();
            expect($webhook->retry_count)->toBe(2);
            expect($webhook->status)->toBe('processed');
            expect($webhook->last_error)->toBeNull();
        });

        it('handles retry failure and updates last_error', function (): void {
            $this->dispatchAction->result = WebhookResult::failed('Handler error');

            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['id' => 'purchase-123', 'type' => 'purchase'],
                'status' => 'failed',
                'retry_count' => 0,
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            $result = $this->manager->retry($webhook);

            expect($result->isFailed())->toBeTrue();

            $webhook->refresh();
            expect($webhook->last_error)->toBe('Handler error');
            expect($webhook->status)->toBe('failed');
        });

        it('handles exception during retry', function (): void {
            $this->dispatchAction->result = WebhookResult::failed('Enrichment failed');

            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['id' => 'purchase-123', 'type' => 'purchase'],
                'status' => 'failed',
                'retry_count' => 0,
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            $result = $this->manager->retry($webhook);

            expect($result->isFailed())->toBeTrue();
        });
    });

    describe('setBackoffSchedule', function (): void {
        it('allows setting custom backoff schedule', function (): void {
            $customSchedule = [
                1 => 30,
                2 => 120,
                3 => 600,
            ];

            $result = $this->manager->setBackoffSchedule($customSchedule);

            expect($result)->toBe($this->manager);

            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['test' => 'data'],
                'status' => 'failed',
                'retry_count' => 0,
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            expect($this->manager->getNextRetryDelay($webhook))->toBe(30);
        });
    });

    describe('getRetryableWebhooks', function (): void {
        it('returns only failed webhooks whose retry delay has elapsed', function (): void {
            $now = CarbonImmutable::parse('2026-04-22 12:00:00');
            CarbonImmutable::setTestNow($now);

            $this->manager->setBackoffSchedule([
                1 => 60,
            ]);

            $eligibleWebhook = Webhook::create([
                'title' => 'Eligible webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['id' => 'purchase-eligible', 'type' => 'purchase'],
                'status' => 'failed',
                'retry_count' => 0,
                'last_retry_at' => $now->subSeconds(61),
                'created_on' => $now->subMinutes(5)->timestamp,
                'updated_on' => $now->subSeconds(61)->timestamp,
                'callback' => 'http://example.com/webhook',
            ]);

            Webhook::create([
                'title' => 'Too recent webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['id' => 'purchase-recent', 'type' => 'purchase'],
                'status' => 'failed',
                'retry_count' => 0,
                'last_retry_at' => $now->subSeconds(30),
                'created_on' => $now->subMinutes(5)->timestamp,
                'updated_on' => $now->subSeconds(30)->timestamp,
                'callback' => 'http://example.com/webhook',
            ]);

            Webhook::create([
                'title' => 'Processed webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['id' => 'purchase-processed', 'type' => 'purchase'],
                'status' => 'processed',
                'retry_count' => 0,
                'last_retry_at' => $now->subSeconds(120),
                'created_on' => $now->subMinutes(5)->timestamp,
                'updated_on' => $now->subSeconds(120)->timestamp,
                'callback' => 'http://example.com/webhook',
            ]);

            $retryable = $this->manager->getRetryableWebhooks();

            expect($retryable->pluck('id')->all())->toBe([$eligibleWebhook->id]);
        });
    });
});
