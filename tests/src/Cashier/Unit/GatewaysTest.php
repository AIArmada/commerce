<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Gateways\AbstractGateway;
use AIArmada\Cashier\Gateways\StripeGateway;
use AIArmada\Chip\Contracts\ChipCustomerDirectoryInterface;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Commerce\Tests\Cashier\Fixtures\ChiplessBillableUser;
use AIArmada\Commerce\Tests\FilamentCashier\Fixtures\ChipBillableUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(CashierTestCase::class);

describe('Gateways', function (): void {
    describe('AbstractGateway', function (): void {
        it('is an abstract class implementing GatewayContract', function (): void {
            $reflection = new ReflectionClass(AbstractGateway::class);

            expect($reflection->isAbstract())->toBeTrue()
                ->and($reflection->implementsInterface(GatewayContract::class))->toBeTrue();
        });

        it('defines name as abstract method', function (): void {
            $reflection = new ReflectionClass(AbstractGateway::class);
            $method = $reflection->getMethod('name');

            expect($method->isAbstract())->toBeTrue();
        });

        it('provides currency method', function (): void {
            $gateway = $this->gatewayManager->gateway('stripe');

            expect($gateway->currency())->toBe('USD');
        });
    });

    describe('StripeGateway', function (): void {
        it('returns correct name', function (): void {
            $gateway = $this->gatewayManager->gateway('stripe');

            expect($gateway->name())->toBe('stripe');
        });

        it('extends AbstractGateway', function (): void {
            $gateway = $this->gatewayManager->gateway('stripe');

            expect($gateway)->toBeInstanceOf(AbstractGateway::class);
        });

        it('implements GatewayContract', function (): void {
            $gateway = $this->gatewayManager->gateway('stripe');

            expect($gateway)->toBeInstanceOf(GatewayContract::class);
        });

        it('returns correct currency', function (): void {
            $gateway = $this->gatewayManager->gateway('stripe');

            expect($gateway->currency())->toBe('USD');
        });

        it('fails closed when webhook secret is missing', function (): void {
            $gateway = new StripeGateway(['webhook_secret' => null]);

            $result = $gateway->verifyWebhookSignature('{"id":"evt_test"}', [
                'Stripe-Signature' => 't=1,v1=abc',
            ]);

            expect($result)->toBeFalse();
        });

        it('fails closed when signature header is missing', function (): void {
            $gateway = new StripeGateway(['webhook_secret' => 'whsec_test']);

            $result = $gateway->verifyWebhookSignature('{"id":"evt_test"}', []);

            expect($result)->toBeFalse();
        });
    });

    describe('ChipGateway', function (): void {
        it('returns correct name', function (): void {
            $gateway = $this->gatewayManager->gateway('chip');

            expect($gateway->name())->toBe('chip');
        });

        it('extends AbstractGateway', function (): void {
            $gateway = $this->gatewayManager->gateway('chip');

            expect($gateway)->toBeInstanceOf(AbstractGateway::class);
        });

        it('implements GatewayContract', function (): void {
            $gateway = $this->gatewayManager->gateway('chip');

            expect($gateway)->toBeInstanceOf(GatewayContract::class);
        });

        it('returns correct currency', function (): void {
            $gateway = $this->gatewayManager->gateway('chip');

            expect($gateway->currency())->toBe('MYR');
        });

        it('does not forward stripe-style arguments into chip billable payment and invoice methods', function (): void {
            $gateway = $this->gatewayManager->gateway('chip');
            $user = new ChipBillableUser;

            $paymentMethods = $gateway->paymentMethods($user, 'card');
            $invoices = $gateway->invoices($user, ['include_pending' => true]);

            expect($paymentMethods)->toHaveCount(2)
                ->and($invoices)->toHaveCount(2);
        });

        it('does not assume a chip_id column when resolving billables', function (): void {
            Schema::create('chipless_billables', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamps();
            });

            config([
                'cashier.models.billable' => ChiplessBillableUser::class,
                'cashier-chip.features.owner.enabled' => false,
            ]);

            $directory = Mockery::mock(ChipCustomerDirectoryInterface::class);
            $directory->shouldReceive('findByChipCustomerId')
                ->once()
                ->with('cli_missing')
                ->andReturn(null);

            $this->app->instance(ChipCustomerDirectoryInterface::class, $directory);

            $gateway = $this->gatewayManager->gateway('chip');

            expect($gateway->findBillable('cli_missing'))->toBeNull();
        });
    });
});
