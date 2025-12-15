<?php

declare(strict_types=1);

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Testing\WebhookFactory;
use AIArmada\Chip\Webhooks\WebhookEnricher;

describe('WebhookEnricher', function (): void {
    it('can be instantiated', function (): void {
        $enricher = new WebhookEnricher;
        expect($enricher)->toBeInstanceOf(WebhookEnricher::class);
    });

    it('enriches payload', function (): void {
        $enricher = new WebhookEnricher;

        $payload = [
            'id' => 'purch_123',
            'client_id' => 'client_abc',
            'status' => 'paid',
        ];

        $result = $enricher->enrich('purchase.paid', $payload);

        expect($result)->toBeInstanceOf(EnrichedWebhookPayload::class)
            ->and($result->event)->toBe('purchase.paid')
            ->and($result->rawPayload)->toBe($payload);
    });
});

describe('WebhookFactory', function (): void {
    it('can be instantiated via make', function (): void {
        $factory = WebhookFactory::make();
        expect($factory)->toBeInstanceOf(WebhookFactory::class);
    });

    it('creates purchase.paid payload', function (): void {
        $payload = WebhookFactory::purchasePaid();

        expect($payload)->toBeArray()
            ->and($payload['event_type'])->toBe('purchase.paid')
            ->and($payload['status'])->toBe('paid')
            ->and($payload['type'])->toBe('purchase')
            ->and($payload['is_test'])->toBeTrue();
    });

    it('creates purchase.created payload', function (): void {
        $payload = WebhookFactory::purchaseCreated();

        expect($payload['event_type'])->toBe('purchase.created')
            ->and($payload['status'])->toBe('created');
    });

    it('creates purchase.payment_failure payload', function (): void {
        $payload = WebhookFactory::purchasePaymentFailure();

        expect($payload['event_type'])->toBe('purchase.payment_failure')
            ->and($payload['status'])->toBe('error');
    });

    it('creates purchase.cancelled payload', function (): void {
        $payload = WebhookFactory::purchaseCancelled();

        expect($payload['event_type'])->toBe('purchase.cancelled')
            ->and($payload['status'])->toBe('cancelled');
    });

    it('creates purchase.preauthorized payload', function (): void {
        $payload = WebhookFactory::purchasePreauthorized();

        expect($payload['event_type'])->toBe('purchase.preauthorized')
            ->and($payload['status'])->toBe('preauthorized')
            ->and($payload['recurring_token'])->not->toBeNull();
    });

    it('creates purchase.hold payload', function (): void {
        $payload = WebhookFactory::purchaseHold();

        expect($payload['event_type'])->toBe('purchase.hold')
            ->and($payload['status'])->toBe('hold');
    });

    it('creates purchase.captured payload', function (): void {
        $payload = WebhookFactory::purchaseCaptured();

        expect($payload['event_type'])->toBe('purchase.captured')
            ->and($payload['status'])->toBe('captured');
    });

    it('creates purchase.released payload', function (): void {
        $payload = WebhookFactory::purchaseReleased();

        expect($payload['event_type'])->toBe('purchase.released')
            ->and($payload['status'])->toBe('released');
    });

    it('creates purchase.subscription_charge_failure payload', function (): void {
        $payload = WebhookFactory::purchaseSubscriptionChargeFailure();

        expect($payload['event_type'])->toBe('purchase.subscription_charge_failure')
            ->and($payload['status'])->toBe('error');
    });

    it('creates payment.refunded payload', function (): void {
        $payload = WebhookFactory::paymentRefunded();

        expect($payload['event_type'])->toBe('payment.refunded')
            ->and($payload['status'])->toBe('refunded');
    });

    it('creates billing_template_client.subscription_billing_cancelled payload', function (): void {
        $payload = WebhookFactory::billingCancelled();

        expect($payload['event_type'])->toBe('billing_template_client.subscription_billing_cancelled')
            ->and($payload['type'])->toBe('billing_template_client');
    });

    it('creates payout.pending payload', function (): void {
        $payload = WebhookFactory::payoutPending();

        expect($payload['event_type'])->toBe('payout.pending')
            ->and($payload['type'])->toBe('payout')
            ->and($payload['status'])->toBe('pending');
    });

    it('creates payout.success payload', function (): void {
        $payload = WebhookFactory::payoutSuccess();

        expect($payload['event_type'])->toBe('payout.success')
            ->and($payload['status'])->toBe('success');
    });

    it('creates payout.failed payload', function (): void {
        $payload = WebhookFactory::payoutFailed();

        expect($payload['event_type'])->toBe('payout.failed')
            ->and($payload['status'])->toBe('failed')
            ->and($payload['error_code'])->toBe('insufficient_funds');
    });

    it('creates payload for event via forEvent', function (): void {
        $payload = WebhookFactory::forEvent(WebhookEventType::PurchasePaid);

        expect($payload['event_type'])->toBe('purchase.paid');
    });

    it('uses fluent builder pattern', function (): void {
        $factory = WebhookFactory::make()
            ->paid()
            ->amount(50000)
            ->currency('USD')
            ->reference('MY-REF-123')
            ->customer('test@test.com', 'John Doe', '+60123456789')
            ->isTest(false);

        $payload = $factory->toArray();

        expect($payload['status'])->toBe('paid')
            ->and($payload['purchase']['currency'])->toBe('USD')
            ->and($payload['reference'])->toBe('MY-REF-123')
            ->and($payload['client']['email'])->toBe('test@test.com')
            ->and($payload['client']['full_name'])->toBe('John Doe')
            ->and($payload['is_test'])->toBeFalse();
    });

    it('sets amount correctly', function (): void {
        $factory = WebhookFactory::make()->amount(25000);
        $payload = $factory->toArray();

        expect($payload['purchase']['total'])->toBe(25000);
    });

    it('sets payment method via fpx helper', function (): void {
        $factory = WebhookFactory::make()->paid()->fpx();
        $payload = $factory->toArray();

        expect($payload['transaction_data']['payment_method'])->toBe('fpx');
    });

    it('sets payment method via card helper', function (): void {
        $factory = WebhookFactory::make()->paid()->card();
        $payload = $factory->toArray();

        expect($payload['transaction_data']['payment_method'])->toBe('card');
    });

    it('sets payment method via ewallet helper', function (): void {
        $factory = WebhookFactory::make()->paid()->ewallet('touch_n_go');
        $payload = $factory->toArray();

        expect($payload['transaction_data']['payment_method'])->toBe('touch_n_go');
    });

    it('supports overrides via with method', function (): void {
        $payload = WebhookFactory::purchasePaid([
            'id' => 'custom-id-123',
            'reference' => 'CUSTOM-REF',
        ]);

        expect($payload['id'])->toBe('custom-id-123')
            ->and($payload['reference'])->toBe('CUSTOM-REF');
    });

    it('adds products', function (): void {
        $factory = WebhookFactory::make()
            ->addProduct('Product A', 10000)
            ->addProduct('Product B', 5000, '2.0000');

        $payload = $factory->toArray();

        expect($payload['purchase']['products'])->toHaveCount(2)
            ->and($payload['purchase']['products'][0]['name'])->toBe('Product A')
            ->and($payload['purchase']['products'][1]['name'])->toBe('Product B');
    });

    it('sets products array', function (): void {
        $factory = WebhookFactory::make()->products([
            ['name' => 'Item 1', 'price' => 5000],
            ['name' => 'Item 2', 'price' => 3000],
        ]);

        $payload = $factory->toArray();

        expect($payload['purchase']['products'])->toHaveCount(2);
    });

    it('can output as JSON', function (): void {
        $factory = WebhookFactory::make()->paid();
        $json = $factory->toJson();

        expect($json)->toBeString()
            ->and(json_decode($json, true))->toBeArray();
    });

    it('sets purchaseId', function (): void {
        $factory = WebhookFactory::make()->purchaseId('purch-custom-id');
        $payload = $factory->toArray();

        expect($payload['id'])->toBe('purch-custom-id');
    });

    it('sets clientId', function (): void {
        $factory = WebhookFactory::make()->clientId('client-custom-id');
        $payload = $factory->toArray();

        expect($payload['client_id'])->toBe('client-custom-id');
    });

    it('sets brandId', function (): void {
        $factory = WebhookFactory::make()->brandId('brand-custom-id');
        $payload = $factory->toArray();

        expect($payload['brand_id'])->toBe('brand-custom-id');
    });

    it('sets companyId', function (): void {
        $factory = WebhookFactory::make()->companyId('company-custom-id');
        $payload = $factory->toArray();

        expect($payload['company_id'])->toBe('company-custom-id');
    });

    it('sets live mode', function (): void {
        $factory = WebhookFactory::make()->live();
        $payload = $factory->toArray();

        expect($payload['is_test'])->toBeFalse();
    });

    it('creates failed status', function (): void {
        $factory = WebhookFactory::make()->failed();
        $payload = $factory->toArray();

        expect($payload['status'])->toBe('error');
    });

    it('creates expired status', function (): void {
        $factory = WebhookFactory::make()->expired();
        $payload = $factory->toArray();

        expect($payload['status'])->toBe('expired');
    });

    it('creates refunded status', function (): void {
        $factory = WebhookFactory::make()->refunded();
        $payload = $factory->toArray();

        expect($payload['status'])->toBe('refunded');
    });

    it('creates cancelled status', function (): void {
        $factory = WebhookFactory::make()->cancelled();
        $payload = $factory->toArray();

        expect($payload['status'])->toBe('cancelled');
    });

    it('includes payment data for paid status', function (): void {
        $payload = WebhookFactory::purchasePaid();

        expect($payload['payment'])->not->toBeNull()
            ->and($payload['payment']['amount'])->toBeInt()
            ->and($payload['payment']['currency'])->toBe('MYR');
    });

    it('excludes payment data for non-paid status', function (): void {
        $payload = WebhookFactory::purchaseCreated();

        expect($payload['payment'])->toBeNull();
    });

    it('builds status history', function (): void {
        $payload = WebhookFactory::purchasePaid();

        expect($payload['status_history'])->toBeArray()
            ->and(count($payload['status_history']))->toBeGreaterThan(0);
    });
});
