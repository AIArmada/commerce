<?php

declare(strict_types=1);

use AIArmada\Cashier\Actions\CancelSubscription;
use AIArmada\Cashier\Actions\CreatePayment;
use AIArmada\Cashier\Actions\CreateSubscription;
use AIArmada\Cashier\Actions\RefundPayment;
use AIArmada\Cashier\Concerns\Billable;
use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Exceptions\Gateway\GatewayNotFoundException;
use AIArmada\Cashier\Exceptions\Gateway\InvalidGatewayException;
use AIArmada\Cashier\Exceptions\Payment\PaymentFailedException;
use AIArmada\Cashier\Exceptions\PaymentOperationRateLimitedException;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Cashier\Gateways\ChipGateway;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Commerce\Tests\Cashier\Fixtures\OwnerScopedBillableUser;
use AIArmada\Commerce\Tests\Cashier\Fixtures\Tenant;
use AIArmada\Commerce\Tests\Cashier\Fixtures\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

uses(CashierTestCase::class);

describe('Action input validation', function (): void {
    beforeEach(function (): void {
        $this->billable = Mockery::mock(BillableContract::class);
    });

    it('rejects non-positive payment amounts', function (): void {
        expect(fn () => CreatePayment::run($this->billable, 0, 'pm_test'))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => CreatePayment::run($this->billable, -50, 'pm_test'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects empty payment methods', function (): void {
        expect(fn () => CreatePayment::run($this->billable, 5000, '  '))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects unknown gateways', function (): void {
        expect(fn () => CreatePayment::run($this->billable, 5000, 'pm_test', 'nope'))
            ->toThrow(InvalidGatewayException::class);
    });

    it('rethrows rate limiting without wrapping it', function (): void {
        $gatewayMock = Mockery::mock(GatewayContract::class);
        $gatewayMock->shouldReceive('charge')
            ->once()
            ->andThrow(PaymentOperationRateLimitedException::create('stripe', 'charge', 30));

        Cashier::shouldReceive('gateway')->once()->with('stripe')->andReturn($gatewayMock);

        Event::fake();

        try {
            CreatePayment::run($this->billable, 5000, 'pm_test');
            $this->fail('Expected a rate-limit exception.');
        } catch (PaymentOperationRateLimitedException $e) {
            expect($e->retryAfter())->toBe(30);
        }
    });

    it('logs unexpected failures and throws a generic payment error', function (): void {
        $gatewayMock = Mockery::mock(GatewayContract::class);
        $gatewayMock->shouldReceive('charge')
            ->once()
            ->andThrow(new RuntimeException('db connection exploded'));

        Cashier::shouldReceive('gateway')->once()->with('stripe')->andReturn($gatewayMock);

        Event::fake();

        try {
            CreatePayment::run($this->billable, 5000, 'pm_test');
            $this->fail('Expected a payment failure.');
        } catch (PaymentFailedException $e) {
            expect($e->getMessage())->toBe('payment_failed')
                ->and($e->getMessage())->not->toContain('exploded');
        }
    });

    it('rejects empty subscription types and prices', function (): void {
        expect(fn () => CreateSubscription::run($this->billable, '  ', ['price_x']))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => CreateSubscription::run($this->billable, 'default', []))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => CreateSubscription::run($this->billable, 'default', ''))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects non-positive and unknown-gateway refunds', function (): void {
        expect(fn () => RefundPayment::run('pi_test', -10))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => RefundPayment::run('pi_test', null, 'nope'))
            ->toThrow(InvalidGatewayException::class);
    });

});

describe('Action owner authorization', function (): void {
    beforeEach(function (): void {
        config()->set('commerce-support.owner.enabled', true);
        config()->set('cashier.tests.owner.enabled', true);

        $this->ownerA = Tenant::query()->create(['name' => 'Tenant A']);
        $this->ownerB = Tenant::query()->create(['name' => 'Tenant B']);
    });

    it('blocks payments for billables outside the current owner', function (): void {
        $user = OwnerContext::withOwner($this->ownerA, fn () => OwnerScopedBillableUser::query()->create([
            'name' => 'Tenant A User',
            'email' => 'a@example.com',
        ]));

        expect(fn () => OwnerContext::withOwner(
            $this->ownerB,
            fn () => CreatePayment::run($user, 5000, 'pm_test'),
        ))->toThrow(AuthorizationException::class);
    });

    it('blocks cancelling subscriptions outside the current owner', function (): void {
        $user = OwnerContext::withOwner($this->ownerA, fn () => OwnerScopedBillableUser::query()->create([
            'name' => 'Tenant A User',
            'email' => 'a@example.com',
        ]));

        $subscription = Mockery::mock(SubscriptionContract::class);
        $subscription->shouldReceive('owner')->andReturn($user);
        $subscription->shouldNotReceive('cancel');

        expect(fn () => OwnerContext::withOwner(
            $this->ownerB,
            fn () => CancelSubscription::run($subscription),
        ))->toThrow(AuthorizationException::class);
    });
});

describe('Billable and gateway guards', function (): void {
    it('rejects unknown preferred gateways before touching the database', function (): void {
        $user = $this->createUser();

        expect(fn () => $user->setPreferredGateway('nope'))
            ->toThrow(InvalidGatewayException::class);
    });

    it('reads uncast trial timestamps without fataling', function (): void {
        $user = $this->createUser(['trial_ends_at' => '2030-01-01 00:00:00']);

        $uncast = new class extends Model
        {
            use Billable;

            protected $table = 'users';

            public $timestamps = false;

            protected $guarded = [];
        };

        $fresh = $uncast->newQuery()->findOrFail($user->getKey());

        expect($fresh->getAttributes()['trial_ends_at'])->toBeString()
            ->and($fresh->onGenericTrial())->toBeTrue();

        $fresh->setRawAttributes(array_merge($fresh->getAttributes(), ['trial_ends_at' => 'not-a-date']));

        expect($fresh->onGenericTrial())->toBeFalse();
    });

    it('rethrows unexpected gateway failures instead of masking them', function (): void {
        $user = Mockery::mock(User::class, BillableContract::class)->makePartial();

        $failingGateway = Mockery::mock(GatewayContract::class);
        $failingGateway->shouldReceive('subscriptions')->andThrow(new RuntimeException('stripe api down'));

        Cashier::shouldReceive('supportedGateways')->andReturn(['stripe']);
        Cashier::shouldReceive('gateway')->with('stripe')->andReturn($failingGateway);

        expect(fn () => $user->findSubscription())->toThrow(RuntimeException::class, 'stripe api down');
    });

    it('still skips gateways that report themselves unavailable', function (): void {
        $user = Mockery::mock(User::class, BillableContract::class)->makePartial();

        $failingGateway = Mockery::mock(GatewayContract::class);
        $failingGateway->shouldReceive('subscriptions')->andThrow(new GatewayNotFoundException('down'));

        Cashier::shouldReceive('supportedGateways')->andReturn(['stripe']);
        Cashier::shouldReceive('gateway')->with('stripe')->andReturn($failingGateway);

        expect($user->findSubscription())->toBeNull()
            ->and($user->onTrialOnAny())->toBeFalse();
    });

    it('refuses CHIP checkouts that belong to another tenant', function (): void {
        Log::spy();

        $foreignPurchase = PurchaseData::from([
            'id' => 'purchase-checkout-foreign',
            'created_on' => 1704067200,
            'updated_on' => 1704070800,
            'client' => ['email' => 'other-tenant@example.com'],
            'client_id' => 'chip_cus_foreign_tenant',
            'purchase' => [
                'currency' => 'MYR',
                'total' => 7500,
                'products' => [[
                    'name' => 'Other tenant item',
                    'price' => 7500,
                    'quantity' => 1,
                    'discount' => 0,
                    'tax_percent' => 0.0,
                ]],
            ],
            'brand_id' => 'brand_123',
            'status' => 'sent',
        ]);

        $service = Mockery::mock(ChipCollectService::class);
        $service->shouldReceive('getPurchase')->once()->with('purchase-checkout-foreign')->andReturn($foreignPurchase);
        app()->instance(ChipCollectService::class, $service);

        expect((new ChipGateway([]))->retrieveCheckout('purchase-checkout-foreign'))->toBeNull();

        Log::shouldHaveReceived('warning')->once();
    });
});
