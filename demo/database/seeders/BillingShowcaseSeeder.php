<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\CashierChip\Subscription;
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
        // Get the admin user and a few regular users
        $admin = User::where('email', 'admin@commerce.demo')->first();
        $users = User::where('email', '!=', 'admin@commerce.demo')->take(5)->get();

        if (! $admin) {
            return;
        }

        // Setup admin with CHIP customer ID and payment method
        $this->setupBillableUser($admin, [
            'chip_id' => 'cli_demo_admin_'.Str::random(16),
            'chip_default_payment_method' => 'tok_'.Str::random(32),
            'pm_type' => 'visa',
            'pm_last_four' => '4242',
        ]);

        // Create subscriptions for admin - demonstrating different states
        $this->createSubscription($admin, [
            'type' => 'Pro Monthly',
            'chip_status' => Subscription::STATUS_ACTIVE,
            'chip_price' => 'price_pro_monthly',
            'billing_interval' => 'month',
            'next_billing_at' => Carbon::now()->addDays(15),
        ]);

        $this->createSubscription($admin, [
            'type' => 'Team Storage',
            'chip_status' => Subscription::STATUS_TRIALING,
            'chip_price' => 'price_storage_50gb',
            'billing_interval' => 'month',
            'trial_ends_at' => Carbon::now()->addDays(7),
            'next_billing_at' => Carbon::now()->addDays(7),
        ]);

        // Add Stripe data alongside Chip
        $admin->update([
            'stripe_id' => 'cus_demo_admin_' . Str::random(16),
            'stripe_default_payment_method' => 'pm_' . Str::random(24),
        ]);

        // Create Stripe subscriptions (mock for demo)
        \Laravel\Cashier\Database\Factories\SubscriptionFactory::new()->create([
            'user_id' => $admin->id,
            'stripe_status' => 'active',
            'stripe_id' => 'sub_stripe_' . Str::random(20),
            'stripe_price' => 'price_stripe_pro',
        ]);

        // Setup other users with varying subscription states
        foreach ($users as $index => $user) {
            $this->setupBillableUser($user, [
                'chip_id' => 'cli_demo_user_'.Str::random(16),
                'chip_default_payment_method' => 'tok_'.Str::random(32),
                'pm_type' => $this->randomCardBrand(),
                'pm_last_four' => (string) rand(1000, 9999),
            ]);

            // Create different subscription scenarios
            match ($index) {
                0 => $this->createSubscription($user, [
                    'type' => 'Starter Plan',
                    'chip_status' => Subscription::STATUS_ACTIVE,
                    'chip_price' => 'price_starter',
                    'billing_interval' => 'month',
                    'next_billing_at' => Carbon::now()->addDays(rand(5, 25)),
                ]),
                1 => $this->createSubscription($user, [
                    'type' => 'Business Annual',
                    'chip_status' => Subscription::STATUS_ACTIVE,
                    'chip_price' => 'price_business_yearly',
                    'billing_interval' => 'year',
                    'next_billing_at' => Carbon::now()->addMonths(8),
                ]),
                2 => $this->createSubscription($user, [
                    'type' => 'Premium Trial',
                    'chip_status' => Subscription::STATUS_TRIALING,
                    'chip_price' => 'price_premium',
                    'billing_interval' => 'month',
                    'trial_ends_at' => Carbon::now()->addDays(10),
                    'next_billing_at' => Carbon::now()->addDays(10),
                ]),
                3 => $this->createSubscription($user, [
                    'type' => 'Enterprise',
                    'chip_status' => Subscription::STATUS_ACTIVE,
                    'chip_price' => 'price_enterprise',
                    'billing_interval' => 'month',
                    'ends_at' => Carbon::now()->addDays(5), // On grace period
                    'next_billing_at' => Carbon::now()->addDays(5),
                ]),
                default => null,
            };

            $user->update([
                'stripe_id' => 'cus_demo_user_' . Str::random(16),
            ]);
        }
    }

    /**
     * Setup a user as a billable customer.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function setupBillableUser(User $user, array $attributes): void
    {
        $user->forceFill($attributes)->save();
    }

    /**
     * Create a subscription for a user.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createSubscription(User $user, array $attributes): Subscription
    {
        return Subscription::create([
            'user_id' => $user->id,
            'type' => $attributes['type'] ?? 'default',
            'chip_id' => 'sub_'.Str::random(40),
            'chip_status' => $attributes['chip_status'] ?? Subscription::STATUS_ACTIVE,
            'chip_price' => $attributes['chip_price'] ?? null,
            'quantity' => $attributes['quantity'] ?? 1,
            'billing_interval' => $attributes['billing_interval'] ?? 'month',
            'billing_interval_count' => $attributes['billing_interval_count'] ?? 1,
            'recurring_token' => 'tok_'.Str::random(32),
            'trial_ends_at' => $attributes['trial_ends_at'] ?? null,
            'ends_at' => $attributes['ends_at'] ?? null,
            'next_billing_at' => $attributes['next_billing_at'] ?? Carbon::now()->addMonth(),
        ]);
    }

    /**
     * Get a random card brand.
     */
    private function randomCardBrand(): string
    {
        return collect(['visa', 'mastercard', 'amex'])->random();
    }
}
