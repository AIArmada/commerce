<?php

declare(strict_types=1);

use AIArmada\Chip\Commands\AggregateMetricsCommand;
use AIArmada\Chip\Commands\CleanWebhooksCommand;
use AIArmada\Chip\Commands\ProcessRecurringCommand;
use AIArmada\Chip\Commands\RetryWebhooksCommand;
use AIArmada\Chip\Services\MetricsAggregator;
use AIArmada\Chip\Services\RecurringService;
use AIArmada\Chip\Webhooks\WebhookRetryManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

describe('AggregateMetricsCommand', function () {
    it('has correct signature', function () {
        $command = new AggregateMetricsCommand;

        expect($command->getName())->toBe('chip:aggregate-metrics');
    });

    it('has description', function () {
        $command = new AggregateMetricsCommand;

        expect($command->getDescription())->not->toBeEmpty();
    });
});

describe('CleanWebhooksCommand', function () {
    it('has correct signature', function () {
        $command = new CleanWebhooksCommand;

        expect($command->getName())->toBe('chip:clean-webhooks');
    });

    it('has description', function () {
        $command = new CleanWebhooksCommand;

        expect($command->getDescription())->not->toBeEmpty();
    });
});

describe('ProcessRecurringCommand', function () {
    it('has correct signature', function () {
        $command = new ProcessRecurringCommand;

        expect($command->getName())->toBe('chip:process-recurring');
    });

    it('has description', function () {
        $command = new ProcessRecurringCommand;

        expect($command->getDescription())->not->toBeEmpty();
    });
});

describe('RetryWebhooksCommand', function () {
    it('has correct signature', function () {
        $command = new RetryWebhooksCommand;

        expect($command->getName())->toBe('chip:retry-webhooks');
    });

    it('has description', function () {
        $command = new RetryWebhooksCommand;

        expect($command->getDescription())->not->toBeEmpty();
    });
});

describe('AggregateMetricsCommand execution', function () {
    it('aggregates metrics for yesterday by default', function () {
        $aggregator = Mockery::mock(MetricsAggregator::class);
        $aggregator->shouldReceive('aggregateForDate')
            ->once()
            ->withArgs(fn($date) => $date->isYesterday());

        $this->artisan('chip:aggregate-metrics')
            ->assertSuccessful();
    })->skip('Requires service binding');

    it('aggregates metrics for specific date', function () {
        $aggregator = Mockery::mock(MetricsAggregator::class);
        $aggregator->shouldReceive('aggregateForDate')
            ->once()
            ->withArgs(fn($date) => $date->toDateString() === '2024-01-15');

        $this->artisan('chip:aggregate-metrics', ['--date' => '2024-01-15'])
            ->assertSuccessful();
    })->skip('Requires service binding');
});

describe('CleanWebhooksCommand execution', function () {
    it('shows message when no webhooks to clean', function () {
        $this->artisan('chip:clean-webhooks', ['--dry-run' => true])
            ->assertSuccessful();
    })->skip('Requires database');
});

describe('ProcessRecurringCommand execution', function () {
    it('shows message when no schedules due', function () {
        $service = Mockery::mock(RecurringService::class);
        $service->shouldReceive('getDueSchedules')
            ->once()
            ->andReturn(collect([]));

        $this->app->instance(RecurringService::class, $service);

        $this->artisan('chip:process-recurring')
            ->assertSuccessful();
    })->skip('Requires service binding');
});

describe('RetryWebhooksCommand execution', function () {
    it('shows message when no webhooks to retry', function () {
        $manager = Mockery::mock(WebhookRetryManager::class);
        $manager->shouldReceive('getRetryableWebhooks')
            ->once()
            ->andReturn(collect([]));

        $this->app->instance(WebhookRetryManager::class, $manager);

        $this->artisan('chip:retry-webhooks')
            ->assertSuccessful();
    })->skip('Requires service binding');
});
