<?php

declare(strict_types=1);

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Data\WebhookData;
use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use AIArmada\Chip\Events\PurchasePendingRefund;
use AIArmada\Chip\Events\PurchaseRecurringTokenDeleted;
use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Testing\WebhookFactory;
use AIArmada\Chip\Testing\WebhookSimulator;
use AIArmada\Chip\Webhooks\ChipWebhookProfile;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

describe('WebhookSimulator', function (): void {
    it('can be instantiated via make', function (): void {
        $simulator = WebhookSimulator::make();
        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('can create paid simulator', function (): void {
        $simulator = WebhookSimulator::paid();
        $payload = $simulator->getPayload();

        expect($payload['status'])->toBe('paid');
    });

    it('can create created simulator', function (): void {
        $simulator = WebhookSimulator::created();
        $payload = $simulator->getPayload();

        expect($payload['status'])->toBe('created');
    });

    it('can create refunded simulator', function (): void {
        $simulator = WebhookSimulator::refunded();
        $payload = $simulator->getPayload();

        expect($payload['status'])->toBe('refunded');
    });

    it('can create cancelled simulator', function (): void {
        $simulator = WebhookSimulator::cancelled();
        $payload = $simulator->getPayload();

        expect($payload['status'])->toBe('cancelled');
    });

    it('can create expired simulator', function (): void {
        $simulator = WebhookSimulator::expired();
        $payload = $simulator->getPayload();

        expect($payload['status'])->toBe('expired');
    });

    it('can create failed simulator', function (): void {
        $simulator = WebhookSimulator::failed();
        $payload = $simulator->getPayload();

        expect($payload['status'])->toBe('error')
            ->and($payload['event_type'])->toBe('purchase.payment_failure');
    });

    it('can create simulator for specific event type', function (): void {
        $simulator = WebhookSimulator::forEvent(WebhookEventType::PurchasePaid);
        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('creates realistic payloads for pending refund events', function (): void {
        $payload = WebhookSimulator::forEvent(WebhookEventType::PurchasePendingRefund)->getPayload();

        expect($payload['event_type'])->toBe('purchase.pending_refund')
            ->and($payload['status'])->toBe('pending_refund')
            ->and($payload['type'])->toBe('purchase');
    });

    it('creates payment-shaped payloads for refund completion events', function (): void {
        $payload = WebhookSimulator::forEvent(WebhookEventType::PaymentRefunded)->getPayload();

        expect($payload['event_type'])->toBe('payment.refunded')
            ->and($payload['type'])->toBe('payment')
            ->and(data_get($payload, 'related_to.type'))->toBe('purchase');
    });

    it('can set URL', function (): void {
        $simulator = WebhookSimulator::paid()
            ->to('https://example.com/webhook');

        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('can set URL via url method', function (): void {
        $simulator = WebhookSimulator::paid()
            ->url('https://example.com/webhook');

        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('can add headers', function (): void {
        $simulator = WebhookSimulator::paid()
            ->withHeader('X-Custom', 'value')
            ->withHeaders(['X-Another' => 'value2']);

        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('can set timeout', function (): void {
        $simulator = WebhookSimulator::paid()
            ->timeout(60);

        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('can configure amount', function (): void {
        $simulator = WebhookSimulator::paid()->amount(50000);
        $payload = $simulator->getPayload();

        expect($payload['purchase']['total'])->toBe(50000);
    });

    it('can set reference', function (): void {
        $simulator = WebhookSimulator::paid()->reference('MY-REF-123');
        $payload = $simulator->getPayload();

        expect($payload['reference'])->toBe('MY-REF-123');
    });

    it('can set purchase ID', function (): void {
        $simulator = WebhookSimulator::paid()->purchaseId('purch-custom');
        $payload = $simulator->getPayload();

        expect($payload['id'])->toBe('purch-custom');
    });

    it('can set client ID', function (): void {
        $simulator = WebhookSimulator::paid()->clientId('client-custom');
        $payload = $simulator->getPayload();

        expect($payload['client_id'])->toBe('client-custom');
    });

    it('can set customer details', function (): void {
        $simulator = WebhookSimulator::paid()
            ->customer('test@test.com', 'John Doe', '+60123456789');
        $payload = $simulator->getPayload();

        expect($payload['client']['email'])->toBe('test@test.com')
            ->and($payload['client']['full_name'])->toBe('John Doe');
    });

    it('can add products', function (): void {
        $simulator = WebhookSimulator::paid()
            ->addProduct('Product 1', 5000, '2.0000');
        $payload = $simulator->getPayload();

        expect($payload['purchase']['products'])->toHaveCount(1);
    });

    it('can set payment method', function (): void {
        $simulator = WebhookSimulator::paid()->paymentMethod('card');
        $payload = $simulator->getPayload();

        expect($payload['transaction_data']['payment_method'])->toBe('card');
    });

    it('can use fpx shorthand', function (): void {
        $simulator = WebhookSimulator::paid()->fpx();
        $payload = $simulator->getPayload();

        expect($payload['transaction_data']['payment_method'])->toBe('fpx');
    });

    it('can use card shorthand', function (): void {
        $simulator = WebhookSimulator::paid()->card();
        $payload = $simulator->getPayload();

        expect($payload['transaction_data']['payment_method'])->toBe('card');
    });

    it('can set test mode', function (): void {
        $simulator = WebhookSimulator::paid()->isTest(true);
        $payload = $simulator->getPayload();

        expect($payload['is_test'])->toBeTrue();
    });

    it('can set live mode', function (): void {
        $simulator = WebhookSimulator::paid()->live();
        $payload = $simulator->getPayload();

        expect($payload['is_test'])->toBeFalse();
    });

    it('can apply overrides', function (): void {
        $simulator = WebhookSimulator::paid()->with(['custom_key' => 'custom_value']);
        $payload = $simulator->getPayload();

        expect($payload['custom_key'])->toBe('custom_value');
    });

    it('returns payload as JSON', function (): void {
        $simulator = WebhookSimulator::paid();
        $json = $simulator->getPayloadJson();

        expect($json)->toBeString()
            ->and(json_decode($json, true))->toBeArray();
    });

    it('can create request object', function (): void {
        $simulator = WebhookSimulator::paid();
        $request = $simulator->toRequest('/webhook', ['X-Custom' => 'value']);

        expect($request)->toBeInstanceOf(Request::class)
            ->and($request->getMethod())->toBe('POST');
    });

    it('creates JSON requests that the CHIP webhook profile can process', function (): void {
        $request = WebhookSimulator::forEvent(WebhookEventType::PurchasePendingRefund)->toRequest('/chip/webhooks');

        expect($request->input('event_type'))->toBe('purchase.pending_refund')
            ->and($request->getContentTypeFormat())->toBe('json')
            ->and((new ChipWebhookProfile)->shouldProcess($request))->toBeTrue();
    });

    it('can create purchase data object', function (): void {
        $simulator = WebhookSimulator::paid();
        $purchase = $simulator->toPurchase();

        expect($purchase)->toBeInstanceOf(PurchaseData::class);
    });

    it('can create webhook data object', function (): void {
        $simulator = WebhookSimulator::paid();
        $webhook = $simulator->toWebhook();

        expect($webhook)->toBeInstanceOf(WebhookData::class);
    });

    it('creates webhook data objects for payment-shaped refund payloads', function (): void {
        $webhook = WebhookSimulator::refunded()->toWebhook();

        expect($webhook)->toBeInstanceOf(WebhookData::class)
            ->and($webhook->event_type)->toBe('payment.refunded')
            ->and($webhook->payload)->not->toBeNull()
            ->and(data_get($webhook->payload, 'type'))->toBe('payment');
    });

    it('dispatches generic refund webhooks with populated payment data', function (): void {
        Event::fake([WebhookReceived::class, PaymentRefunded::class]);

        WebhookSimulator::refunded()->dispatch();

        Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $event): bool => $event->payment !== null
            && $event->isRefunded());
        Event::assertDispatched(PaymentRefunded::class);
    });

    it('includes the active owner tuple when dispatching directly in owner mode', function (): void {
        config()->set('chip.owner.enabled', true);

        $owner = User::query()->create([
            'name' => 'Simulator Owner',
            'email' => 'simulator-owner@example.com',
            'password' => 'secret',
        ]);

        Event::fake([WebhookReceived::class, PaymentRefunded::class]);

        OwnerContext::withOwner($owner, function (): void {
            WebhookSimulator::refunded()->dispatch();
        });

        Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $event): bool => data_get($event->payload, '__owner_type') === $owner->getMorphClass()
            && data_get($event->payload, '__owner_id') === (string) $owner->getKey());

        Event::assertDispatched(PaymentRefunded::class, fn (PaymentRefunded $event): bool => data_get($event->payload, '__owner_type') === $owner->getMorphClass()
            && data_get($event->payload, '__owner_id') === (string) $owner->getKey());
    });

    it('dispatches purchase payment failure for the failed helper', function (): void {
        Event::fake([WebhookReceived::class, PurchasePaymentFailure::class]);

        WebhookSimulator::failed()->dispatch();

        Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $event): bool => $event->eventType === 'purchase.payment_failure');
        Event::assertDispatched(PurchasePaymentFailure::class);
    });

    it('fakes pending and recurring webhook events by default', function (): void {
        WebhookSimulator::fakeEvents();

        event(PurchasePendingRefund::fromPayload(WebhookFactory::purchasePendingRefund()));
        event(PurchaseRecurringTokenDeleted::fromPayload(WebhookFactory::purchaseRecurringTokenDeleted()));

        WebhookSimulator::assertDispatched(PurchasePendingRefund::class);
        WebhookSimulator::assertDispatched(PurchaseRecurringTokenDeleted::class);
    });

    it('dispatches refunded helper through the runtime dispatcher and syncs purchase state', function (): void {
        Event::fake([WebhookReceived::class, PaymentRefunded::class]);

        $purchase = Purchase::create([
            'id' => 'purchase-simulator-refund-123',
            'type' => 'purchase',
            'status' => 'paid',
            'brand_id' => 'brand-123',
            'company_id' => 'company-123',
            'client_id' => 'client-123',
            'created_on' => time(),
            'updated_on' => time(),
            'client' => ['email' => 'test@example.com'],
            'purchase' => ['total' => 10000, 'currency' => 'MYR'],
            'payment' => [
                'amount' => 10000,
                'currency' => 'MYR',
            ],
            'issuer_details' => [],
            'transaction_data' => [],
            'status_history' => [],
            'refund_availability' => 'all',
            'refundable_amount' => 10000,
            'refund_amount_minor' => 0,
            'platform' => 'test',
            'product' => 'chip',
            'send_receipt' => false,
            'is_test' => true,
            'is_recurring_token' => false,
            'skip_capture' => false,
            'force_recurring' => false,
            'marked_as_paid' => false,
        ]);

        WebhookSimulator::refunded()
            ->purchaseId($purchase->id)
            ->amount(2500)
            ->with(['id' => 'payment-refund-simulator-123'])
            ->dispatch();

        $purchase->refresh();

        expect($purchase->status)->toBe('partially_refunded')
            ->and($purchase->refund_amount_minor)->toBe(2500)
            ->and($purchase->refundable_amount)->toBe(7500);

        Event::assertDispatched(PaymentRefunded::class);
    });

    it('can set factory', function (): void {
        $factory = WebhookFactory::make()->cancelled();
        $simulator = WebhookSimulator::make()->factory($factory);
        $payload = $simulator->getPayload();

        expect($payload['status'])->toBe('cancelled');
    });

    it('disables signature verification', function (): void {
        config(['chip.webhooks.verify_signature' => true]);

        WebhookSimulator::withoutSignatureVerification();

        expect(config('chip.webhooks.verify_signature'))->toBeFalse();
    });

    it('throws when sending without URL', function (): void {
        $simulator = WebhookSimulator::paid();

        expect(fn () => $simulator->send())
            ->toThrow(RuntimeException::class, 'Webhook URL not set');
    });

    it('throws when sending using without URL', function (): void {
        $simulator = WebhookSimulator::paid();

        expect(fn () => $simulator->sendUsing(fn () => null))
            ->toThrow(RuntimeException::class, 'Webhook URL not set');
    });
});
