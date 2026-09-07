<?php

declare(strict_types=1);

use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionBuilder;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;

uses(CashierChipTestCase::class);

describe('SubscriptionBuilder', function (): void {
    it('can create builder', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $this->assertInstanceOf(SubscriptionBuilder::class, $builder);
    });

    it('can add price', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default');

        $result = $builder->price('price_123');

        $this->assertSame($builder, $result);
    });

    it('can add price with quantity', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default');

        $result = $builder->price('price_123', 5);

        $this->assertSame($builder, $result);
    });

    it('quantity throws without price', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default');

        $builder->quantity(5);
    })->throws(InvalidArgumentException::class);

    it('quantity with single price', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->quantity(5);

        $this->assertSame($builder, $result);
    });

    it('trial days', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->trialDays(14);

        $this->assertSame($builder, $result);
    });

    it('trial until', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $trialEnd = Carbon::now()->addDays(30);
        $result = $builder->trialUntil($trialEnd);

        $this->assertSame($builder, $result);
    });

    it('skip trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->skipTrial();

        $this->assertSame($builder, $result);
    });

    it('billing interval', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->billingInterval('month', 1);

        $this->assertSame($builder, $result);
    });

    it('monthly', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->monthly();

        $this->assertSame($builder, $result);
    });

    it('yearly', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->yearly();

        $this->assertSame($builder, $result);
    });

    it('weekly', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->weekly();

        $this->assertSame($builder, $result);
    });

    it('daily', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->daily();

        $this->assertSame($builder, $result);
    });

    it('anchor billing cycle', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $anchor = Carbon::now()->addDay();
        $result = $builder->anchorBillingCycleOn($anchor);

        $this->assertSame($builder, $result);
    });

    it('with metadata', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->withMetadata(['key' => 'value']);

        $this->assertSame($builder, $result);
    });

    it('add throws without prices', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default');

        $builder->add();
    })->throws(Exception::class, 'At least one price is required');

    it('create throws without prices', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default');

        $builder->create();
    })->throws(Exception::class, 'At least one price is required');

    it('create requires owner context when owner scoping enabled', function (): void {
        config()->set('cashier-chip.features.owner.enabled', true);
        config()->set('cashier-chip.features.owner.include_global', false);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test-' . uniqid() . '@example.com',
        ]);

        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        try {
            $builder->create();
            $this->fail('Expected AuthorizationException was not thrown.');
        } catch (AuthorizationException) {
            $this->assertSame(0, Subscription::query()->withoutOwnerScope()->count());
        }
    });
});
