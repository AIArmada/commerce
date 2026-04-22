<?php

declare(strict_types=1);

use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Listeners\StoreWebhookData;
use AIArmada\Chip\Models\Client;
use AIArmada\Chip\Models\Payment;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Testing\WebhookFactory;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

describe('StoreWebhookData Listener', function (): void {
    beforeEach(function (): void {
        $this->listener = new StoreWebhookData;
        Config::set('chip.webhooks.store_webhooks', true);
    });

    describe('configuration checks', function (): void {
        it('does nothing when store_webhooks config is false', function (): void {
            Config::set('chip.webhooks.store_webhooks', false);

            $event = WebhookReceived::fromPayload([
                'id' => 'purchase-test-id-1',
                'type' => 'purchase',
                'status' => 'paid',
                'event_type' => 'purchase.paid',
                'brand_id' => 'brand-123',
                'created_on' => time(),
                'updated_on' => time(),
            ]);

            // Mock the Purchase model to verify it's NOT called
            Purchase::shouldReceive('updateOrCreate')->never();

            $this->listener->handle($event);
        })->skip('Mockery static mocking conflicts with Eloquent');

        it('only processes purchase type webhooks', function (): void {
            $initialPurchaseCount = Purchase::count();

            $event = WebhookReceived::fromPayload([
                'id' => 'payout-123',
                'type' => 'payout',  // Not a purchase
                'status' => 'success',
                'event_type' => 'payout.success',
                'created_on' => time(),
                'updated_on' => time(),
            ]);

            $this->listener->handle($event);

            // No purchase should be created for payout type
            expect(Purchase::count())->toBe($initialPurchaseCount);
            expect(Purchase::find('payout-123'))->toBeNull();
        });

        it('skips when no id in payload', function (): void {
            Log::shouldReceive('warning')
                ->once()
                ->with('CHIP: No purchase ID in webhook payload');

            // Use eventType directly in constructor to bypass PurchaseData validation
            $event = new WebhookReceived(
                eventType: 'purchase.paid',
                payload: [
                    'type' => 'purchase',
                    'status' => 'paid',
                    'created_on' => time(),
                    'updated_on' => time(),
                ],
            );

            $initialCount = Purchase::count();
            $this->listener->handle($event);

            expect(Purchase::count())->toBe($initialCount);
        });

        it('skips when type is not purchase', function (): void {
            $event = new WebhookReceived(
                eventType: 'billing_template.created',
                payload: [
                    'id' => 'billing-template-123',
                    'type' => 'billing_template',
                    'status' => 'active',
                    'created_on' => time(),
                    'updated_on' => time(),
                ],
            );

            $initialCount = Purchase::count();
            $this->listener->handle($event);

            expect(Purchase::count())->toBe($initialCount);
        });
    });

    describe('store_webhooks config toggle', function (): void {
        it('returns early when store_webhooks is false', function (): void {
            Config::set('chip.webhooks.store_webhooks', false);

            $event = new WebhookReceived(
                eventType: 'purchase.paid',
                payload: [
                    'id' => 'purchase-config-test',
                    'type' => 'purchase',
                    'status' => 'paid',
                    'brand_id' => 'brand-123',
                    'created_on' => time(),
                    'updated_on' => time(),
                ],
            );

            $initialCount = Purchase::count();
            $this->listener->handle($event);

            expect(Purchase::count())->toBe($initialCount);
            expect(Purchase::find('purchase-config-test'))->toBeNull();
        });
    });

    describe('persistence regressions', function (): void {
        it('stores purchases with default refund availability when the payload omits it', function (): void {
            $payload = WebhookFactory::purchasePaid([
                'id' => 'purchase-missing-refund-availability',
            ]);
            unset($payload['refund_availability']);

            $this->listener->handle(WebhookReceived::fromPayload($payload));

            $purchase = Purchase::find('purchase-missing-refund-availability');

            expect($purchase)->not->toBeNull()
                ->and($purchase?->refund_availability)->toBe('all');
        });

        it('does not create duplicate payment rows when the same webhook is stored twice', function (): void {
            $payload = WebhookFactory::purchasePaid([
                'id' => 'purchase-payment-repeat',
                'client_id' => 'client-payment-repeat',
            ]);

            $event = WebhookReceived::fromPayload($payload);

            $this->listener->handle($event);
            $this->listener->handle($event);

            $payments = Payment::query()
                ->where('purchase_id', 'purchase-payment-repeat')
                ->get();

            expect($payments)->toHaveCount(1)
                ->and($payments->sole()->amount)->toBe($payload['payment']['amount']);
        });

        it('allows the same client email to be stored for different owners', function (): void {
            config()->set('chip.owner.enabled', true);

            $ownerOne = User::query()->create([
                'name' => 'Owner One',
                'email' => 'owner-one@example.com',
                'password' => 'secret',
            ]);

            $ownerTwo = User::query()->create([
                'name' => 'Owner Two',
                'email' => 'owner-two@example.com',
                'password' => 'secret',
            ]);

            $sharedEmail = 'shared-client@example.com';

            $payloadOne = WebhookFactory::purchasePaid([
                'id' => 'purchase-owner-one',
                'client_id' => 'client-owner-one',
                'client' => [
                    'email' => $sharedEmail,
                    'full_name' => 'Shared Client One',
                ],
            ]);

            $payloadTwo = WebhookFactory::purchasePaid([
                'id' => 'purchase-owner-two',
                'client_id' => 'client-owner-two',
                'client' => [
                    'email' => $sharedEmail,
                    'full_name' => 'Shared Client Two',
                ],
            ]);

            OwnerContext::withOwner($ownerOne, function () use ($payloadOne): void {
                $this->listener->handle(WebhookReceived::fromPayload($payloadOne));
            });

            OwnerContext::withOwner($ownerTwo, function () use ($payloadTwo): void {
                $this->listener->handle(WebhookReceived::fromPayload($payloadTwo));
            });

            $clients = Client::query()
                ->withoutOwnerScope()
                ->where('email', $sharedEmail)
                ->get();

            expect($clients)->toHaveCount(2)
                ->and(collect($clients->pluck('owner_id')->all())->sort()->values()->all())
                ->toBe(collect([(string) $ownerOne->getKey(), (string) $ownerTwo->getKey()])->sort()->values()->all());
        });
    });

    describe('listener instantiation', function (): void {
        it('can be instantiated', function (): void {
            $listener = new StoreWebhookData;
            expect($listener)->toBeInstanceOf(StoreWebhookData::class);
        });

        it('has handle method', function (): void {
            expect(method_exists($this->listener, 'handle'))->toBeTrue();
        });

        it('handle method accepts WebhookReceived event', function (): void {
            $reflection = new ReflectionMethod($this->listener, 'handle');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(1);
            expect($params[0]->getType()?->getName())->toBe(WebhookReceived::class);
        });
    });
});
