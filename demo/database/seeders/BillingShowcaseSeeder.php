<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Payment\StoredPaymentMethod;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\Chip\Models\ChipCustomerLink;
use AIArmada\CommerceSupport\Support\OwnerContext;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds billing demo data for the self-service billing portal showcase.
 *
 * Creates realistic subscription, payment method, and billing data
 * for demonstrating the Cashier CHIP billing portal.
 */
final class BillingShowcaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedBillingData();
    }

    /**
     * Seed billing portal demo data.
     */
    private function seedBillingData(): void
    {
        $owner = OwnerContext::resolve();

        if (! $owner instanceof User) {
            return;
        }

        $admin = $owner;

        // Setup admin with CHIP customer ID and payment method
        $this->setupBillableUser($admin, [
            'chip_id' => 'cli_demo_admin_'.Str::random(16),
            'default_pm_id' => 'tok_'.Str::random(32),
            'pm_type' => 'visa',
            'pm_last_four' => '4242',
        ]);

        // Create subscriptions for admin - demonstrating different states
        $this->createSubscription($admin, [
            'type' => 'Pro Monthly',
            'chip_status' => SubscriptionStatus::Active,
            'chip_price' => 'price_pro_monthly',
            'billing_interval' => 'month',
            'next_billing_at' => Carbon::now()->addDays(15),
        ]);

        $this->createSubscription($admin, [
            'type' => 'Team Storage',
            'chip_status' => SubscriptionStatus::Trialing,
            'chip_price' => 'price_storage_50gb',
            'billing_interval' => 'month',
            'trial_ends_at' => Carbon::now()->addDays(7),
            'next_billing_at' => Carbon::now()->addDays(7),
        ]);

        // Note: we intentionally only seed subscriptions for the current tenant owner.
        // Creating subscriptions for other (global) users would violate owner scoping.
    }

    /**
     * Setup a user as a billable customer.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function setupBillableUser(User $user, array $attributes): void
    {
        ChipCustomerLink::firstOrCreate(
            [
                'subject_type' => $user->getMorphClass(),
                'subject_id' => (string) $user->getKey(),
            ],
            [
                'chip_customer_id' => $attributes['chip_id'],
            ]
        );

        StoredPaymentMethod::firstOrCreate(
            [
                'billable_type' => $user->getMorphClass(),
                'billable_id' => (string) $user->getKey(),
            ],
            [
                'recurring_token' => $attributes['default_pm_id'],
                'type' => $attributes['pm_type'] ?? null,
                'last_four' => $attributes['pm_last_four'] ?? null,
                'is_default' => true,
            ]
        );
    }

    /**
     * Create a subscription for a user.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createSubscription(User $user, array $attributes): Subscription
    {
        return Subscription::create([
            'billable_type' => $user->getMorphClass(),
            'billable_id' => (string) $user->getKey(),
            'type' => $attributes['type'] ?? 'default',
            'chip_id' => 'sub_'.Str::random(40),
            'chip_status' => $attributes['chip_status'] ?? SubscriptionStatus::Active,
            'chip_price' => $attributes['chip_price'] ?? null,
            'quantity' => $attributes['quantity'] ?? 1,
            'billing_interval' => $attributes['billing_interval'] ?? 'month',
            'billing_interval_count' => $attributes['billing_interval_count'] ?? 1,
            'trial_ends_at' => $attributes['trial_ends_at'] ?? null,
            'ends_at' => $attributes['ends_at'] ?? null,
            'next_billing_at' => $attributes['next_billing_at'] ?? Carbon::now()->addMonth(),
        ]);
    }
}
