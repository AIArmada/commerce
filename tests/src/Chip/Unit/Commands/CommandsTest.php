<?php

declare(strict_types=1);

use AIArmada\Chip\Commands\CleanWebhooksCommand;
use AIArmada\Chip\Commands\RetryWebhooksCommand;
use AIArmada\Chip\Webhooks\WebhookRetryManager;

describe('CleanWebhooksCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new CleanWebhooksCommand;

        expect($command->getName())->toBe('chip:clean-webhooks');
    });

    it('has description', function (): void {
        $command = new CleanWebhooksCommand;

        expect($command->getDescription())->not->toBeEmpty();
    });
});

describe('RetryWebhooksCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new RetryWebhooksCommand;

        expect($command->getName())->toBe('chip:retry-webhooks');
    });

    it('has description', function (): void {
        $command = new RetryWebhooksCommand;

        expect($command->getDescription())->not->toBeEmpty();
    });
});

describe('CleanWebhooksCommand execution', function (): void {
    it('shows message when no webhooks to clean', function (): void {
        $this->artisan('chip:clean-webhooks', ['--dry-run' => true])
            ->assertSuccessful();
    })->skip('Requires database');
});

describe('RetryWebhooksCommand execution', function (): void {
    it('shows message when no webhooks to retry', function (): void {
        $manager = Mockery::mock(WebhookRetryManager::class);
        $manager->shouldReceive('getRetryableWebhooks')
            ->once()
            ->andReturn(collect([]));

        $this->app->instance(WebhookRetryManager::class, $manager);

        $this->artisan('chip:retry-webhooks')
            ->assertSuccessful();
    })->skip('Requires service binding');
});
