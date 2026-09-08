<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Discount;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Exceptions\SubscriptionUpdateFailure;
use AIArmada\CashierChip\Invoice\Invoice;
use AIArmada\CashierChip\Subscription\RenewalAttempt;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionItem;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

uses(CashierChipTestCase::class);

describe('SubscriptionIntegration', function (): void {
    it('can swap single price', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_swap_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_old',
            'chip_status' => SubscriptionStatus::Active,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_old',
        ]);

        $subscription->swap('price_new');

        $this->assertEquals('price_new', $subscription->fresh()->chip_price);
    });

    it('can swap multiple prices', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_swap_multi_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_old',
            'chip_status' => SubscriptionStatus::Active,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_old',
        ]);

        $subscription->swap(['price_new_1', 'price_new_2']);

        $this->assertNull($subscription->fresh()->chip_price);
        $this->assertEquals(2, $subscription->fresh()->items->count());
    });

    it('swap throws on incomplete', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_swap_incomplete_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Incomplete,
        ]);

        $subscription->swap('price_new');
    })->throws(Exception::class);

    it('swap throws with empty prices', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_swap_empty_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
        ]);

        $subscription->swap([]);
    })->throws(InvalidArgumentException::class);

    it('swap clears ends at', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_swap_ends_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_old',
            'chip_status' => SubscriptionStatus::Active,
            'ends_at' => Carbon::now()->addDays(5),
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_old',
        ]);

        $subscription->swap('price_new');

        $this->assertNull($subscription->fresh()->ends_at);
    });

    it('update quantity', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_qty_update_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 1,
            'chip_status' => SubscriptionStatus::Active,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 1,
        ]);

        $subscription->updateQuantity(5);

        $this->assertEquals(5, $subscription->fresh()->quantity);
    });

    it('update quantity clamps to minimum one', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_qty_min_clamp_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 2,
            'chip_status' => SubscriptionStatus::Active,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 2,
        ]);

        $subscription->updateQuantity(0);

        $this->assertEquals(1, $subscription->fresh()->quantity);
    });

    it('increment quantity', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_qty_inc_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 5,
            'chip_status' => SubscriptionStatus::Active,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 5,
        ]);

        $subscription->incrementQuantity(2);

        $this->assertEquals(7, $subscription->fresh()->quantity);
    });

    it('decrement quantity', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_qty_dec_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 5,
            'chip_status' => SubscriptionStatus::Active,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 5,
        ]);

        $subscription->decrementQuantity(2);

        $this->assertEquals(3, $subscription->fresh()->quantity);
    });

    it('decrement quantity minimum one', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_qty_min_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 2,
            'chip_status' => SubscriptionStatus::Active,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 2,
        ]);

        $subscription->decrementQuantity(5);

        $this->assertEquals(1, $subscription->fresh()->quantity);
    });

    it('quantity throws on incomplete', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_qty_incomplete_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_per_seat',
            'chip_status' => SubscriptionStatus::Incomplete,
        ]);

        $subscription->updateQuantity(5);
    })->throws(Exception::class);

    it('current period start', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_period_123']);
        $nextBilling = Carbon::now()->addMonth();
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'billing_interval' => 'month',
            'next_billing_at' => $nextBilling,
        ]);

        $periodStart = $subscription->currentPeriodStart();

        $this->assertNotNull($periodStart);
    });

    it('current period start null without billing date', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_period_null_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'next_billing_at' => null,
        ]);

        $this->assertNull($subscription->currentPeriodStart());
    });

    it('current period end', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_period_end_123']);
        $nextBilling = Carbon::now()->addMonth();
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'next_billing_at' => $nextBilling,
        ]);

        $periodEnd = $subscription->currentPeriodEnd();

        $this->assertNotNull($periodEnd);
    });

    it('current period end with timezone', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_period_tz_123']);
        $nextBilling = Carbon::now()->addMonth();
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'next_billing_at' => $nextBilling,
        ]);

        $periodEnd = $subscription->currentPeriodEnd('Asia/Kuala_Lumpur');

        $this->assertNotNull($periodEnd);
        $this->assertEquals('Asia/Kuala_Lumpur', $periodEnd->timezoneName);
    });

    it('recurring token from subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_token_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'recurring_token' => 'tok_sub_123',
        ]);

        $this->assertEquals('tok_sub_123', $subscription->recurringToken());
    });

    it('has discount', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_discount_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'coupon_id' => 'COUPON123',
            'coupon_discount' => 1000,
        ]);

        $this->assertTrue($subscription->hasDiscount());
    });

    it('no discount', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_no_discount_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'coupon_id' => null,
        ]);

        $this->assertFalse($subscription->hasDiscount());
    });

    it('has product', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_prod_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_product' => 'prod_123',
        ]);

        $this->assertTrue($subscription->hasProduct('prod_123'));
        $this->assertFalse($subscription->hasProduct('prod_456'));
    });

    it('has price with multiple prices', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_multi_price_check_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => null,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_123',
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_456',
        ]);

        $this->assertTrue($subscription->hasPrice('price_123'));
        $this->assertTrue($subscription->hasPrice('price_456'));
        $this->assertFalse($subscription->hasPrice('price_789'));
    });

    it('find item or fail', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_find_item_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_123',
        ]);

        $item = $subscription->findItemOrFail('price_123');

        $this->assertInstanceOf(SubscriptionItem::class, $item);
    });

    it('find item or fail throws', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_find_item_fail_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $subscription->findItemOrFail('non_existent_price');
    })->throws(ModelNotFoundException::class);

    it('discount returns discount instance', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_discount_inst_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'coupon_id' => 'COUPON123',
            'coupon_discount' => 1000,
            'coupon_applied_at' => now(),
        ]);

        $discount = $subscription->discount();

        $this->assertInstanceOf(Discount::class, $discount);
    });

    it('discount returns null without coupon', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_discount_null_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'coupon_id' => null,
        ]);

        $this->assertNull($subscription->discount());
    });

    it('discounts returns collection', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_discounts_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'coupon_id' => 'COUPON123',
            'coupon_discount' => 1000,
        ]);

        $discounts = $subscription->discounts();

        $this->assertCount(1, $discounts);
    });

    it('discounts returns empty without coupon', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_discounts_empty_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'coupon_id' => null,
        ]);

        $this->assertCount(0, $subscription->discounts());
    });

    it('remove discount', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_remove_discount_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'coupon_id' => 'COUPON123',
            'coupon_discount' => 1000,
        ]);

        $subscription->removeDiscount();

        $this->assertNull($subscription->fresh()->coupon_id);
        $this->assertNull($subscription->fresh()->coupon_discount);
    });

    it('paused', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_paused_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Paused,
        ]);

        $this->assertTrue($subscription->paused());
    });

    it('pause', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_pause_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
        ]);

        $subscription->pause();

        $this->assertEquals(SubscriptionStatus::Paused, $subscription->chip_status);
    });

    it('unpause', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_unpause_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Paused,
        ]);

        $subscription->unpause();

        $this->assertEquals(SubscriptionStatus::Active, $subscription->chip_status);
    });

    it('scope paused', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_scope_paused_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Paused,
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
        ]);

        $this->assertEquals(1, Subscription::query()->paused()->count());
    });

    it('invoices returns empty collection', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_invoices_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $this->assertCount(0, $subscription->invoices());
    });

    it('upcoming invoice returns null when canceled', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_upcoming_invoice_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => now()->subDay(),
        ]);

        $this->assertNull($subscription->upcomingInvoice());
    });

    it('latest invoice returns null', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_latest_invoice_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $this->assertNull($subscription->latestInvoice());
    });

    it('latest payment returns null', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_latest_payment_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $this->assertNull($subscription->latestPayment());
    });

    it('builds payment and invoice history from completed renewal attempts', function (): void {
        $user = $this->createUser([
            'chip_id' => 'cli_payment_history_123',
            'email' => 'history@example.com',
        ]);
        $subscription = Subscription::factory()->for($user, 'owner')->create();
        $purchase = $this->fakeChip->createPurchase([
            'client_id' => $user->chip_id,
            'client' => [
                'email' => $user->email,
                'full_name' => $user->name,
            ],
            'purchase' => [
                'currency' => 'MYR',
                'products' => [[
                    'name' => 'Monthly plan',
                    'price' => 1000,
                    'quantity' => 1,
                    'discount' => 0,
                ]],
                'total' => 1000,
            ],
        ]);
        $this->fakeChip->markPurchaseAsPaid($purchase->id);

        RenewalAttempt::create([
            'subscription_id' => $subscription->id,
            'status' => 'completed',
            'amount_minor' => 1000,
            'period_key' => '2026-09',
            'purchase_id' => $purchase->id,
            'completed_at' => now(),
        ]);

        $payment = $subscription->latestPayment();
        $invoice = $subscription->latestInvoice();

        expect($payment)->not->toBeNull()
            ->and($payment?->id())->toBe($purchase->id)
            ->and($payment?->isSucceeded())->toBeTrue()
            ->and($invoice)->toBeInstanceOf(Invoice::class)
            ->and($invoice?->id())->toBe($purchase->id)
            ->and($subscription->invoices())->toHaveCount(1);
    });

    it('builds an upcoming invoice from the current subscription items', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_upcoming_invoice_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_monthly',
            'next_billing_at' => now()->addMonth(),
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_monthly',
            'chip_product' => 'Monthly plan',
            'unit_amount' => 1200,
            'quantity' => 2,
        ]);

        $invoice = $subscription->upcomingInvoice();

        expect($invoice)->toBeInstanceOf(Invoice::class)
            ->and($invoice?->rawTotal())->toBe(2400)
            ->and($invoice?->status())->toBe('created')
            ->and($invoice?->invoiceItems())->toHaveCount(1);
    });

    it('add price', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_add_price_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_base',
            'chip_status' => SubscriptionStatus::Active,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_base',
        ]);

        $subscription->addPrice('price_addon');

        $this->assertEquals(2, $subscription->fresh()->items->count());
        $this->assertNull($subscription->fresh()->chip_price);
    });

    it('add price throws on duplicate', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_add_dup_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_existing',
        ]);

        $subscription->addPrice('price_existing');
    })->throws(SubscriptionUpdateFailure::class);

    it('remove price throws on single price', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_remove_single_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_only',
            'chip_status' => SubscriptionStatus::Active,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_only',
        ]);

        $subscription->removePrice('price_only');
    })->throws(SubscriptionUpdateFailure::class);

    it('sync chip status', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_sync_status_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
            'ends_at' => null,
            'trial_ends_at' => null,
        ]);

        $subscription->syncChipStatus();

        // Status should remain active since not ended
        $this->assertEquals(SubscriptionStatus::Active, $subscription->chip_status);
    });
});
