<?php

declare(strict_types=1);

use AIArmada\Chip\Commands\CleanWebhooksCommand;
use AIArmada\Chip\Commands\RetryWebhooksCommand;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Webhooks\WebhookRetryManager;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

describe('CleanWebhooksCommand', function (): void {
    it('shows message when no webhooks found', function (): void {
        $this->artisan(CleanWebhooksCommand::class)
            ->expectsOutput('No webhooks found matching the criteria.')
            ->assertSuccessful();
    });

    it('shows count and does not delete in dry-run mode', function (): void {
        // Create old webhook
        Webhook::create([
            'title' => 'Test Webhook',
            'event' => 'purchase.paid',
            'events' => ['purchase.paid'],
            'payload' => ['test' => 'data'],
            'status' => 'processed',
            'created_at' => now()->subDays(60),
            'created_on' => now()->subDays(60)->timestamp,
            'updated_on' => now()->subDays(60)->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        $this->artisan(CleanWebhooksCommand::class, ['--dry-run' => true])
            ->expectsOutputToContain('Found 1 webhook(s) older than 30 days')
            ->expectsOutput('Dry run mode - no webhooks will be deleted.')
            ->assertSuccessful();

        expect(Webhook::count())->toBe(1); // Still exists
    });

    it('deletes webhooks when confirmed', function (): void {
        // Create old webhook
        Webhook::create([
            'title' => 'Test Webhook',
            'event' => 'purchase.paid',
            'events' => ['purchase.paid'],
            'payload' => ['test' => 'data'],
            'status' => 'processed',
            'created_at' => now()->subDays(60),
            'created_on' => now()->subDays(60)->timestamp,
            'updated_on' => now()->subDays(60)->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        $this->artisan(CleanWebhooksCommand::class, ['--days' => 30, '--status' => 'processed'])
            ->expectsOutputToContain('Found 1 webhook(s) older than 30 days')
            ->expectsConfirmation('Are you sure you want to delete 1 webhook records?', 'yes')
            ->expectsOutput('Successfully deleted 1 webhook record(s).')
            ->assertSuccessful();

        expect(Webhook::count())->toBe(0);
    });

    it('cancels when user declines confirmation', function (): void {
        Webhook::create([
            'title' => 'Test Webhook',
            'event' => 'purchase.paid',
            'events' => ['purchase.paid'],
            'payload' => ['test' => 'data'],
            'status' => 'processed',
            'created_at' => now()->subDays(60),
            'created_on' => now()->subDays(60)->timestamp,
            'updated_on' => now()->subDays(60)->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        $this->artisan(CleanWebhooksCommand::class)
            ->expectsConfirmation('Are you sure you want to delete 1 webhook records?', 'no')
            ->expectsOutput('Operation cancelled.')
            ->assertSuccessful();

        expect(Webhook::count())->toBe(1); // Still exists
    });

    it('respects custom days option', function (): void {
        Webhook::create([
            'title' => 'Test Webhook',
            'event' => 'purchase.paid',
            'events' => ['purchase.paid'],
            'payload' => ['test' => 'data'],
            'status' => 'processed',
            'created_at' => now()->subDays(10), // Only 10 days old
            'created_on' => now()->subDays(10)->timestamp,
            'updated_on' => now()->subDays(10)->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        // Default 30 days should not find it
        $this->artisan(CleanWebhooksCommand::class, ['--days' => 30])
            ->expectsOutput('No webhooks found matching the criteria.')
            ->assertSuccessful();

        // 5 days should find it
        $this->artisan(CleanWebhooksCommand::class, ['--days' => 5, '--dry-run' => true])
            ->expectsOutputToContain('Found 1 webhook(s)')
            ->assertSuccessful();
    });

    it('respects status all option', function (): void {
        Webhook::create([
            'title' => 'Test Webhook',
            'event' => 'purchase.paid',
            'events' => ['purchase.paid'],
            'payload' => ['test' => 'data'],
            'status' => 'failed',
            'created_at' => now()->subDays(60),
            'created_on' => now()->subDays(60)->timestamp,
            'updated_on' => now()->subDays(60)->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        // Default status 'processed' should not find 'failed'
        $this->artisan(CleanWebhooksCommand::class, ['--status' => 'processed'])
            ->expectsOutput('No webhooks found matching the criteria.')
            ->assertSuccessful();

        // status 'all' should find it
        $this->artisan(CleanWebhooksCommand::class, ['--status' => 'all', '--dry-run' => true])
            ->expectsOutputToContain('Found 1 webhook(s)')
            ->assertSuccessful();
    });
});

describe('RetryWebhooksCommand', function (): void {
    it('shows message when no webhooks are eligible', function (): void {
        $retryManager = Mockery::mock(WebhookRetryManager::class);
        $retryManager->shouldReceive('getRetryableWebhooks')
            ->once()
            ->andReturn(new EloquentCollection);

        $this->app->instance(WebhookRetryManager::class, $retryManager);

        $this->artisan(RetryWebhooksCommand::class)
            ->expectsOutput('No webhooks are eligible for retry.')
            ->assertSuccessful();
    });

    it('shows table in dry-run mode', function (): void {
        $mockWebhook = new Webhook([
            'id' => 'webhook-123',
            'title' => 'Test Webhook',
            'event' => 'purchase.paid',
            'retry_count' => 2,
            'last_error' => 'Connection timeout',
            'payload' => [],
            'created_on' => time(),
            'updated_on' => time(),
            'callback' => 'http://example.com/webhook',
            'events' => ['purchase.paid'],
        ]);

        $retryManager = Mockery::mock(WebhookRetryManager::class);
        $retryManager->shouldReceive('getRetryableWebhooks')
            ->once()
            ->andReturn(new EloquentCollection([$mockWebhook]));

        $this->app->instance(WebhookRetryManager::class, $retryManager);

        $this->artisan(RetryWebhooksCommand::class, ['--dry-run' => true])
            ->expectsOutputToContain('Found 1 webhook(s) eligible for retry')
            ->expectsOutput('Dry run mode - no webhooks will be processed.')
            ->assertSuccessful();
    });

    it('respects limit option', function (): void {
        $webhooks = collect([
            new Webhook(['id' => 'w1', 'title' => 'Test Webhook', 'events' => ['purchase.paid'], 'event' => 'purchase.paid', 'retry_count' => 1, 'payload' => [], 'created_on' => time(), 'updated_on' => time(), 'callback' => 'http://example.com/webhook']),
            new Webhook(['id' => 'w2', 'title' => 'Test Webhook', 'events' => ['purchase.paid'], 'event' => 'purchase.paid', 'retry_count' => 1, 'payload' => [], 'created_on' => time(), 'updated_on' => time(), 'callback' => 'http://example.com/webhook']),
            new Webhook(['id' => 'w3', 'title' => 'Test Webhook', 'events' => ['purchase.paid'], 'event' => 'purchase.paid', 'retry_count' => 1, 'payload' => [], 'created_on' => time(), 'updated_on' => time(), 'callback' => 'http://example.com/webhook']),
        ]);

        $retryManager = Mockery::mock(WebhookRetryManager::class);
        $retryManager->shouldReceive('getRetryableWebhooks')
            ->once()
            ->andReturn(new EloquentCollection($webhooks->all()));

        $this->app->instance(WebhookRetryManager::class, $retryManager);

        // With limit 2, should only show 2
        $this->artisan(RetryWebhooksCommand::class, ['--limit' => 2, '--dry-run' => true])
            ->expectsOutputToContain('Found 2 webhook(s) eligible for retry')
            ->assertSuccessful();
    });
});
