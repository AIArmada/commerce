<?php

declare(strict_types=1);

use AIArmada\Cashier\Actions\SyncWebhook;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Events\WebhookHandled;
use AIArmada\Cashier\Events\WebhookReceived;
use AIArmada\Cashier\Exceptions\Webhook\WebhookVerificationException;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Cashier\Gateways\ChipGateway;
use AIArmada\Cashier\Gateways\StripeGateway;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Http\Controllers\WebhookController;

uses(CashierTestCase::class);

describe('Webhook signature enforcement', function (): void {
    it('rejects forged payloads before dispatching WebhookReceived', function (): void {
        $payload = ['type' => 'payment_intent.succeeded', 'data' => []];
        $headers = ['Stripe-Signature' => 't=1,v1=forged'];
        $raw = '{"type":"payment_intent.succeeded"}';

        $gatewayMock = Mockery::mock(GatewayContract::class);
        $gatewayMock->shouldReceive('verifyWebhookSignature')
            ->once()
            ->with($raw, $headers)
            ->andReturnFalse();
        $gatewayMock->shouldNotReceive('handleWebhook');

        Cashier::shouldReceive('gateway')->once()->with('stripe')->andReturn($gatewayMock);

        Event::fake();

        expect(fn () => SyncWebhook::run('stripe', $payload, $headers, $raw))
            ->toThrow(WebhookVerificationException::class);

        Event::assertNotDispatched(WebhookReceived::class);
        Event::assertNotDispatched(WebhookHandled::class);
    });

    it('passes the raw body through once verified', function (): void {
        $payload = ['type' => 'payment_intent.succeeded', 'data' => []];
        $headers = ['Stripe-Signature' => 't=1,v1=valid'];
        $raw = '{"type":"payment_intent.succeeded"}';

        $gatewayMock = Mockery::mock(GatewayContract::class);
        $gatewayMock->shouldReceive('verifyWebhookSignature')->once()->with($raw, $headers)->andReturnTrue();
        $gatewayMock->shouldReceive('handleWebhook')->once()->with($payload, $headers, $raw)->andReturnNull();

        Cashier::shouldReceive('gateway')->once()->with('stripe')->andReturn($gatewayMock);

        Event::fake();

        SyncWebhook::run('stripe', $payload, $headers, $raw);

        Event::assertDispatched(WebhookReceived::class);
    });

    it('verifies the raw Stripe body before reaching the Cashier controller', function (): void {
        $gateway = new StripeGateway(['webhook_secret' => 'whsec_test']);

        expect(fn () => $gateway->handleWebhook(
            ['id' => 'evt_test'],
            ['Stripe-Signature' => 't=1,v1=forged'],
            '{"id":"evt_test"}',
        ))->toThrow(WebhookVerificationException::class);
    });

    it('forwards the verified raw Stripe body to the Cashier controller', function (): void {
        $raw = '{"id":"evt_test","type":"charge.succeeded"}';

        $controller = Mockery::mock(WebhookController::class);
        $controller->shouldReceive('handleWebhook')
            ->once()
            ->withArgs(fn (Request $request): bool => $request->getContent() === $raw)
            ->andReturn(['handled' => true]);

        $this->app->instance(WebhookController::class, $controller);

        $gateway = Mockery::mock(StripeGateway::class)->makePartial();
        $gateway->shouldReceive('verifyWebhookSignature')->once()->with($raw, [])->andReturnTrue();

        expect($gateway->handleWebhook(['id' => 'evt_test'], [], $raw))->toBe(['handled' => true]);
    });

    it('verifies the raw CHIP body before dispatching', function (): void {
        $payload = ['event_type' => 'purchase.paid', 'id' => 'purchase_test'];
        $raw = '{"event_type":"purchase.paid"}';

        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('verifySignature')->once()->andReturnFalse();
        $this->app->instance(WebhookService::class, $webhookService);

        expect(fn () => (new ChipGateway([]))->handleWebhook(
            $payload,
            ['X-Signature' => 'forged'],
            $raw,
        ))->toThrow(WebhookVerificationException::class);
    });

    it('never queues the HTTP request with WebhookReceived', function (): void {
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{"type":"x"}');
        $event = new WebhookReceived('stripe', ['type' => 'x'], $request);

        $restored = unserialize(serialize($event));

        expect($restored->gateway)->toBe('stripe')
            ->and($restored->payload)->toBe(['type' => 'x'])
            ->and($restored->request)->toBeNull();
    });
});

describe('Webhook replay command', function (): void {
    it('rejects unknown gateways', function (): void {
        $this->artisan('cashier:webhook:replay', [
            'event-id' => 'evt_test',
            '--gateway' => 'nope',
        ])->assertFailed();
    });

    it('requires an event id', function (): void {
        $this->artisan('cashier:webhook:replay', ['--gateway' => 'stripe'])
            ->expectsOutputToContain('event ID is required')
            ->assertFailed();
    });

    it('refuses CHIP replay without fabricating payloads', function (): void {
        $gatewayManager = Mockery::mock(GatewayManager::class);
        $gatewayManager->shouldReceive('supportsGateway')->with('chip')->andReturnTrue();
        $gatewayManager->shouldReceive('gateway')->with('chip')->andReturn(new ChipGateway([]));
        $this->app->instance(GatewayManager::class, $gatewayManager);

        $this->artisan('cashier:webhook:replay', [
            'event-id' => 'purchase_test',
            '--gateway' => 'chip',
        ])->assertFailed();
    });

    it('supports dry runs without touching gateways', function (): void {
        $this->artisan('cashier:webhook:replay', [
            'event-id' => 'evt_test',
            '--gateway' => 'stripe',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();
    });

    it('replays Stripe events re-fetched from the API', function (): void {
        $payload = ['id' => 'evt_test', 'type' => 'charge.succeeded'];

        $gateway = Mockery::mock(StripeGateway::class)->makePartial();
        $gateway->shouldReceive('fetchWebhookEvent')->once()->with('evt_test')->andReturn($payload);
        $gateway->shouldReceive('handleWebhook')->once()->with($payload, [])->andReturn(WebhookResult::handled());

        $gatewayManager = Mockery::mock(GatewayManager::class);
        $gatewayManager->shouldReceive('supportsGateway')->with('stripe')->andReturnTrue();
        $gatewayManager->shouldReceive('gateway')->twice()->with('stripe')->andReturn($gateway);
        Cashier::swap($gatewayManager);

        Event::fake();

        $this->artisan('cashier:webhook:replay', [
            'event-id' => 'evt_test',
            '--gateway' => 'stripe',
        ])
            ->expectsOutputToContain('replayed')
            ->assertSuccessful();

        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
    });
});
