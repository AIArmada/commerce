<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Gateways\AbstractGateway;
use AIArmada\Cashier\Gateways\ChipGateway;
use AIArmada\Cashier\Gateways\StripeGateway;
use AIArmada\Chip\Actions\DispatchChipWebhookAction;
use AIArmada\Chip\Contracts\ChipCustomerDirectoryInterface;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Commerce\Tests\Cashier\Fixtures\ChiplessBillableUser;
use AIArmada\Commerce\Tests\FilamentCashier\Fixtures\ChipBillableUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Laravel\Cashier\Http\Controllers\WebhookController;

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

        it('passes the Stripe signature to the Cashier webhook controller', function (): void {
            $controller = Mockery::mock(WebhookController::class);
            $controller->shouldReceive('handleWebhook')
                ->once()
                ->withArgs(function (Request $request): bool {
                    return $request->headers->get('Stripe-Signature') === 't=1,v1=test';
                })
                ->andReturn(['handled' => true]);

            $this->app->instance(WebhookController::class, $controller);

            $result = (new StripeGateway([]))->handleWebhook(
                ['id' => 'evt_test'],
                ['Stripe-Signature' => 't=1,v1=test'],
            );

            expect($result)->toBe(['handled' => true]);
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

        it('resolves its client through cashier-chip', function (): void {
            $client = Mockery::mock(ChipCollectService::class);
            $this->app->instance(ChipCollectService::class, $client);

            expect((new ChipGateway([]))->client())->toBe($client);
        });

        it('delegates webhook verification to the CHIP webhook service', function (): void {
            $payload = '{"event_type":"purchase.paid"}';
            $webhookService = Mockery::mock(WebhookService::class);
            $webhookService->shouldReceive('verifySignature')
                ->once()
                ->withArgs(function (Request $request) use ($payload): bool {
                    return $request->getContent() === $payload
                        && $request->headers->get('X-Signature') === 'chip-signature';
                })
                ->andReturnTrue();

            $this->app->instance(WebhookService::class, $webhookService);

            expect((new ChipGateway([]))->verifyWebhookSignature($payload, [
                'X-Signature' => 'chip-signature',
            ]))->toBeTrue();
        });

        it('delegates webhook handling to the CHIP dispatcher', function (): void {
            $payload = ['event_type' => 'purchase.paid', 'id' => 'purchase_test'];
            $dispatcher = Mockery::mock(DispatchChipWebhookAction::class);
            $dispatcher->shouldReceive('execute')
                ->once()
                ->with('purchase.paid', $payload)
                ->andReturn(WebhookResult::handled());

            $this->app->instance(DispatchChipWebhookAction::class, $dispatcher);

            expect((new ChipGateway([]))->handleWebhook($payload))->toBeInstanceOf(WebhookResult::class);
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
                ->with('cli_missing', null)
                ->andReturn(null);

            $this->app->instance(ChipCustomerDirectoryInterface::class, $directory);

            $gateway = $this->gatewayManager->gateway('chip');

            expect($gateway->findBillable('cli_missing'))->toBeNull();
        });

        it('forwards the explicit setup idempotency key to the CHIP billable hook', function (): void {
            $gateway = $this->gatewayManager->gateway('chip');
            $user = new class extends ChipBillableUser
            {
                /** @var array<string, mixed> */
                public array $setupOptions = [];

                /**
                 * @param  array<string, mixed>  $options
                 * @return array<string, mixed>
                 */
                public function createSetupPurchase(array $options = []): array
                {
                    $this->setupOptions = $options;

                    return $options;
                }
            };

            $options = [
                'idempotency_key' => 'setup-attempt-1',
                'success_url' => 'https://example.test/success',
            ];

            expect($gateway->createSetupIntent($user, $options))
                ->toBe($options)
                ->and($user->setupOptions)
                ->toBe($options);
        });

        it('rejects a missing CHIP setup key before invoking the billable hook', function (): void {
            $gateway = $this->gatewayManager->gateway('chip');
            $user = new class extends ChipBillableUser
            {
                public bool $setupPurchaseCalled = false;

                public function createSetupPurchase(array $options = []): mixed
                {
                    $this->setupPurchaseCalled = true;

                    return null;
                }
            };

            expect(fn () => $gateway->createSetupIntent($user))
                ->toThrow(InvalidArgumentException::class, 'An idempotency_key option is required');

            expect($user->setupPurchaseCalled)->toBeFalse();
        });
    });
});
