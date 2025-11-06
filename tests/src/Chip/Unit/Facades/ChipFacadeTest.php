<?php

declare(strict_types=1);

use AIArmada\Chip\Facades\Chip;
use AIArmada\Chip\Services\SubscriptionService;

beforeEach(function (): void {
    config()->set('chip.collect.api_key', 'collect-key');
    config()->set('chip.collect.brand_id', 'brand-123');
    config()->set('chip.collect.environment', 'sandbox');
});

it('proxies collect service helpers through the Chip facade', function (): void {
    Chip::shouldReceive('getPublicKey')
        ->once()
        ->andReturn('test-public-key');

    expect(Chip::getPublicKey())->toBe('test-public-key');
});

it('resolves subscription helper through the Chip facade', function (): void {
    $subscriptionService = Mockery::mock(SubscriptionService::class);

    Chip::shouldReceive('subscriptions')
        ->once()
        ->andReturn($subscriptionService);

    expect(Chip::subscriptions())->toBe($subscriptionService);
});
