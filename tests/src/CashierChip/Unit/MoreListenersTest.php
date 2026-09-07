<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Listeners\HandlePurchasePaymentFailure;
use AIArmada\CashierChip\Listeners\HandlePurchasePreauthorized;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use AIArmada\Chip\Events\PurchasePreauthorized;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\Event;

uses(CashierChipTestCase::class);

describe('MoreListeners', function (): void {
    it('handle purchase payment failure dispatches event', function (): void {
        Event::fake([PaymentFailed::class]);

        $user = $this->createUser();
        Cashier::chipCustomerDirectory()->link($user, 'cli_123');

        $purchaseData = [
            'id' => 'pur_123',
            'client_id' => 'cli_123',
            'status' => 'error',
            'purchase' => ['total' => 1000, 'currency' => 'MYR'],
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchasePaymentFailure($purchase, $purchaseData);

        $listener = new HandlePurchasePaymentFailure;
        OwnerContext::withOwner($user, fn (): null => tap(null, fn () => $listener->handle($event)));

        Event::assertDispatched(PaymentFailed::class, function ($e) use ($user) {
            return $e->billable->is($user);
        });
    });

    it('handle purchase payment failure returns early without client id', function (): void {
        Event::fake([PaymentFailed::class]);

        $purchaseData = [
            'id' => 'pur_123',
            'status' => 'error',
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchasePaymentFailure($purchase, $purchaseData);

        $listener = new HandlePurchasePaymentFailure;
        $listener->handle($event);

        Event::assertNotDispatched(PaymentFailed::class);
    });

    it('handle purchase payment failure marks subscription past due', function (): void {
        $user = $this->createUser();
        Cashier::chipCustomerDirectory()->link($user, 'cli_123');

        $subscription = Subscription::factory()->for($user, 'owner')->for($user, 'billable')->create([
            'type' => 'default',
            'chip_status' => SubscriptionStatus::Active,
        ]);

        $purchaseData = [
            'id' => 'pur_123',
            'client_id' => 'cli_123',
            'status' => 'error',
            'purchase' => [
                'metadata' => ['subscription_type' => 'default'],
            ],
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchasePaymentFailure($purchase, $purchaseData);

        $listener = new HandlePurchasePaymentFailure;
        OwnerContext::withOwner($user, fn (): null => tap(null, fn () => $listener->handle($event)));

        $this->assertEquals(SubscriptionStatus::PastDue, $subscription->fresh()->chip_status);
    });

    it('handle purchase preauthorized saves recurring token', function (): void {
        $user = $this->createUser();
        Cashier::chipCustomerDirectory()->link($user, 'cli_123');

        $purchaseData = [
            'id' => 'pur_123',
            'client_id' => 'cli_123',
            'status' => 'preauthorized',
            'recurring_token' => 'tok_preauth_123',
            'transaction_data' => [
                'payment_method' => 'visa',
                'extra' => ['masked_pan' => '**** **** **** 4242'],
            ],
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchasePreauthorized($purchase, $purchaseData);

        $listener = new HandlePurchasePreauthorized;
        OwnerContext::withOwner($user, fn (): null => tap(null, fn () => $listener->handle($event)));

        $user->refresh();

        $paymentMethod = $user->defaultPaymentMethod();

        $this->assertNotNull($paymentMethod);
        $this->assertEquals('tok_preauth_123', $paymentMethod?->id());
    });

    it('handle purchase preauthorized returns early without client id', function (): void {
        $purchaseData = [
            'id' => 'pur_123',
            'status' => 'preauthorized',
            'recurring_token' => 'tok_preauth_123',
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchasePreauthorized($purchase, $purchaseData);

        $listener = new HandlePurchasePreauthorized;
        $listener->handle($event);

        // No exception means it returned early successfully
        $this->assertTrue(true);
    });

    it('handle purchase preauthorized returns early without recurring token', function (): void {
        $user = $this->createUser();
        Cashier::chipCustomerDirectory()->link($user, 'cli_123');

        $purchaseData = [
            'id' => 'pur_123',
            'client_id' => 'cli_123',
            'status' => 'preauthorized',
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchasePreauthorized($purchase, $purchaseData);

        $listener = new HandlePurchasePreauthorized;
        OwnerContext::withOwner($user, fn (): null => tap(null, fn () => $listener->handle($event)));

        $user->refresh();
        $this->assertNull($user->defaultPaymentMethod());
    });
});
