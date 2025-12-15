<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Testing\WebhookFactory;
use AIArmada\Chip\Testing\WebhookSimulator;
use Illuminate\Http\Request;

describe('WebhookSimulator', function () {
    it('can be instantiated via make', function () {
        $simulator = WebhookSimulator::make();
        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('can create paid simulator', function () {
        $simulator = WebhookSimulator::paid();
        $payload = $simulator->getPayload();

        expect($payload['status'])->toBe('paid');
    });

    it('can create created simulator', function () {
        $simulator = WebhookSimulator::created();
        $payload = $simulator->getPayload();

        expect($payload['status'])->toBe('created');
    });

    it('can create refunded simulator', function () {
        $simulator = WebhookSimulator::refunded();
        $payload = $simulator->getPayload();

        expect($payload['status'])->toBe('refunded');
    });

    it('can create cancelled simulator', function () {
        $simulator = WebhookSimulator::cancelled();
        $payload = $simulator->getPayload();

        expect($payload['status'])->toBe('cancelled');
    });

    it('can create expired simulator', function () {
        $simulator = WebhookSimulator::expired();
        $payload = $simulator->getPayload();

        expect($payload['status'])->toBe('expired');
    });

    it('can create failed simulator', function () {
        $simulator = WebhookSimulator::failed();
        $payload = $simulator->getPayload();

        expect($payload['status'])->toBe('error');
    });

    it('can create simulator for specific event type', function () {
        $simulator = WebhookSimulator::forEvent(WebhookEventType::PurchasePaid);
        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('can set URL', function () {
        $simulator = WebhookSimulator::paid()
            ->to('https://example.com/webhook');

        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('can set URL via url method', function () {
        $simulator = WebhookSimulator::paid()
            ->url('https://example.com/webhook');

        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('can add headers', function () {
        $simulator = WebhookSimulator::paid()
            ->withHeader('X-Custom', 'value')
            ->withHeaders(['X-Another' => 'value2']);

        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('can set timeout', function () {
        $simulator = WebhookSimulator::paid()
            ->timeout(60);

        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('can configure amount', function () {
        $simulator = WebhookSimulator::paid()->amount(50000);
        $payload = $simulator->getPayload();

        expect($payload['purchase']['total'])->toBe(50000);
    });

    it('can set reference', function () {
        $simulator = WebhookSimulator::paid()->reference('MY-REF-123');
        $payload = $simulator->getPayload();

        expect($payload['reference'])->toBe('MY-REF-123');
    });

    it('can set purchase ID', function () {
        $simulator = WebhookSimulator::paid()->purchaseId('purch-custom');
        $payload = $simulator->getPayload();

        expect($payload['id'])->toBe('purch-custom');
    });

    it('can set client ID', function () {
        $simulator = WebhookSimulator::paid()->clientId('client-custom');
        $payload = $simulator->getPayload();

        expect($payload['client_id'])->toBe('client-custom');
    });

    it('can set customer details', function () {
        $simulator = WebhookSimulator::paid()
            ->customer('test@test.com', 'John Doe', '+60123456789');
        $payload = $simulator->getPayload();

        expect($payload['client']['email'])->toBe('test@test.com')
            ->and($payload['client']['full_name'])->toBe('John Doe');
    });

    it('can add products', function () {
        $simulator = WebhookSimulator::paid()
            ->addProduct('Product 1', 5000, '2.0000');
        $payload = $simulator->getPayload();

        expect($payload['purchase']['products'])->toHaveCount(1);
    });

    it('can set payment method', function () {
        $simulator = WebhookSimulator::paid()->paymentMethod('card');
        $payload = $simulator->getPayload();

        expect($payload['transaction_data']['payment_method'])->toBe('card');
    });

    it('can use fpx shorthand', function () {
        $simulator = WebhookSimulator::paid()->fpx();
        $payload = $simulator->getPayload();

        expect($payload['transaction_data']['payment_method'])->toBe('fpx');
    });

    it('can use card shorthand', function () {
        $simulator = WebhookSimulator::paid()->card();
        $payload = $simulator->getPayload();

        expect($payload['transaction_data']['payment_method'])->toBe('card');
    });

    it('can set test mode', function () {
        $simulator = WebhookSimulator::paid()->isTest(true);
        $payload = $simulator->getPayload();

        expect($payload['is_test'])->toBeTrue();
    });

    it('can set live mode', function () {
        $simulator = WebhookSimulator::paid()->live();
        $payload = $simulator->getPayload();

        expect($payload['is_test'])->toBeFalse();
    });

    it('can apply overrides', function () {
        $simulator = WebhookSimulator::paid()->with(['custom_key' => 'custom_value']);
        $payload = $simulator->getPayload();

        expect($payload['custom_key'])->toBe('custom_value');
    });

    it('returns payload as JSON', function () {
        $simulator = WebhookSimulator::paid();
        $json = $simulator->getPayloadJson();

        expect($json)->toBeString()
            ->and(json_decode($json, true))->toBeArray();
    });

    it('can create request object', function () {
        $simulator = WebhookSimulator::paid();
        $request = $simulator->toRequest('/webhook', ['X-Custom' => 'value']);

        expect($request)->toBeInstanceOf(Request::class)
            ->and($request->getMethod())->toBe('POST');
    });

    it('can create purchase data object', function () {
        $simulator = WebhookSimulator::paid();
        $purchase = $simulator->toPurchase();

        expect($purchase)->toBeInstanceOf(\AIArmada\Chip\Data\PurchaseData::class);
    });

    it('can create webhook data object', function () {
        $simulator = WebhookSimulator::paid();
        $webhook = $simulator->toWebhook();

        expect($webhook)->toBeInstanceOf(\AIArmada\Chip\Data\WebhookData::class);
    });

    it('can set factory', function () {
        $factory = WebhookFactory::make()->cancelled();
        $simulator = WebhookSimulator::make()->factory($factory);
        $payload = $simulator->getPayload();

        expect($payload['status'])->toBe('cancelled');
    });

    it('disables signature verification', function () {
        config(['chip.webhooks.verify_signature' => true]);

        WebhookSimulator::withoutSignatureVerification();

        expect(config('chip.webhooks.verify_signature'))->toBeFalse();
    });

    it('throws when sending without URL', function () {
        $simulator = WebhookSimulator::paid();

        expect(fn() => $simulator->send())
            ->toThrow(RuntimeException::class, 'Webhook URL not set');
    });

    it('throws when sending using without URL', function () {
        $simulator = WebhookSimulator::paid();

        expect(fn() => $simulator->sendUsing(fn() => null))
            ->toThrow(RuntimeException::class, 'Webhook URL not set');
    });
});
