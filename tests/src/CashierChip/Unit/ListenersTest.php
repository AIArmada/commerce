<?php

declare(strict_types=1);

use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CashierChip\Listeners\HandlePurchasePaid;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\Event;

uses(CashierChipTestCase::class);

describe('Listeners', function (): void {
    it('handle purchase paid dispatches event', function (): void {
        Event::fake([PaymentSucceeded::class]);

        $user = $this->createUser([
            'chip_id' => 'cli_123',
        ]);

        $purchaseData = [
            'id' => 'pur_123',
            'client_id' => 'cli_123',
            'status' => 'paid',
            'purchase' => ['total' => 1000, 'currency' => 'MYR'],
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchasePaid($purchase, $purchaseData);

        $listener = new HandlePurchasePaid;
        OwnerContext::withOwner($user, fn (): null => tap(null, fn () => $listener->handle($event)));

        Event::assertDispatched(PaymentSucceeded::class, function ($e) use ($user) {
            return $e->billable->is($user);
        });
    });

    it('handle purchase paid updates default pm', function (): void {
        $user = $this->createUser([
            'chip_id' => 'cli_123',
        ]);

        $purchaseData = [
            'id' => 'pur_123',
            'client_id' => 'cli_123',
            'status' => 'paid',
            'recurring_token' => 'tok_123',
            'transaction_data' => [
                'payment_method' => 'visa',
                'extra' => ['masked_pan' => '**** **** **** 4242'],
            ],
            'purchase' => ['total' => 1000, 'currency' => 'MYR'],
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchasePaid($purchase, $purchaseData);

        $listener = new HandlePurchasePaid;
        OwnerContext::withOwner($user, fn (): null => tap(null, fn () => $listener->handle($event)));

        $user->refresh();
        $this->assertEquals('tok_123', $user->default_pm_id);
    });
});
