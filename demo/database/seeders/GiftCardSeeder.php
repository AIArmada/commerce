<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardTransactionType;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardType;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use AIArmada\Vouchers\GiftCards\Models\GiftCardTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 🎁 GIFT CARD SHOWCASE SEEDER
 *
 * Creates a comprehensive gift card system demonstrating:
 * - Multiple gift card types (Standard, Open Value, Promotional, Reward, Corporate)
 * - Various statuses (Active, Exhausted, Expired, Suspended)
 * - Transaction history (Top-ups, Redemptions, Transfers)
 * - Balance scenarios for demo purposes
 */
final class GiftCardSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎁 Creating Gift Card System...');

        $users = User::all();
        $admin = User::where('email', 'admin@commerce.demo')->first();

        // ============================================
        // STANDARD GIFT CARDS (Customer Purchases)
        // ============================================
        $standardCards = [
            [
                'code' => 'GC-MALL-2024-ABCD',
                'type' => GiftCardType::Standard,
                'initial_balance' => 50000, // RM500
                'current_balance' => 50000,
                'status' => GiftCardStatus::Active,
                'metadata' => [
                    'design' => 'holiday_winter',
                    'message' => 'Happy Holidays! Enjoy your shopping!',
                    'purchase_order' => 'ORD-GC001',
                ],
            ],
            [
                'code' => 'GC-BDAY-2024-EFGH',
                'type' => GiftCardType::Standard,
                'initial_balance' => 20000, // RM200
                'current_balance' => 12500, // RM125 remaining
                'status' => GiftCardStatus::Active,
                'activated_at' => now()->subDays(15),
                'last_used_at' => now()->subDays(3),
                'metadata' => [
                    'design' => 'birthday_celebration',
                    'message' => 'Happy Birthday! 🎂',
                    'purchase_order' => 'ORD-GC002',
                ],
            ],
            [
                'code' => 'GC-GIFT-2024-IJKL',
                'type' => GiftCardType::Standard,
                'initial_balance' => 10000, // RM100
                'current_balance' => 0, // Fully used
                'status' => GiftCardStatus::Exhausted,
                'activated_at' => now()->subMonth(),
                'last_used_at' => now()->subWeek(),
                'metadata' => [
                    'design' => 'thank_you',
                    'message' => 'Thank you for everything!',
                ],
            ],
        ];

        // ============================================
        // OPEN VALUE GIFT CARDS (Flexible Amounts)
        // ============================================
        $openValueCards = [
            [
                'code' => 'GC-OPEN-PREMIUM-1',
                'type' => GiftCardType::OpenValue,
                'initial_balance' => 100000, // RM1000
                'current_balance' => 78500, // RM785 remaining
                'status' => GiftCardStatus::Active,
                'activated_at' => now()->subDays(30),
                'last_used_at' => now()->subDays(5),
                'metadata' => [
                    'design' => 'premium_gold',
                    'tier' => 'premium',
                ],
            ],
            [
                'code' => 'GC-OPEN-CUSTOM-2',
                'type' => GiftCardType::OpenValue,
                'initial_balance' => 25000, // RM250
                'current_balance' => 25000,
                'status' => GiftCardStatus::Active,
                'activated_at' => now()->subDays(2),
                'metadata' => [
                    'design' => 'custom_amount',
                ],
            ],
        ];

        // ============================================
        // PROMOTIONAL GIFT CARDS (Marketing Campaigns)
        // ============================================
        $promoCards = [
            [
                'code' => 'PROMO-LAUNCH2024',
                'type' => GiftCardType::Promotional,
                'initial_balance' => 5000, // RM50
                'current_balance' => 5000,
                'status' => GiftCardStatus::Active,
                'activated_at' => now(),
                'expires_at' => now()->addMonths(3),
                'metadata' => [
                    'campaign' => 'new_year_launch',
                    'source' => 'email_marketing',
                ],
            ],
            [
                'code' => 'PROMO-WELCOME50',
                'type' => GiftCardType::Promotional,
                'initial_balance' => 5000, // RM50
                'current_balance' => 0,
                'status' => GiftCardStatus::Exhausted,
                'activated_at' => now()->subMonth(),
                'last_used_at' => now()->subWeeks(2),
                'metadata' => [
                    'campaign' => 'new_customer_welcome',
                    'source' => 'registration_bonus',
                ],
            ],
            [
                'code' => 'PROMO-FLASH100',
                'type' => GiftCardType::Promotional,
                'initial_balance' => 10000, // RM100
                'current_balance' => 10000,
                'status' => GiftCardStatus::Inactive,
                'expires_at' => now()->addDays(7),
                'metadata' => [
                    'campaign' => 'flash_sale_bonus',
                    'source' => 'promotion',
                ],
            ],
        ];

        // ============================================
        // REWARD GIFT CARDS (Loyalty Points Conversion)
        // ============================================
        $rewardCards = [
            [
                'code' => 'REWARD-GOLD-001',
                'type' => GiftCardType::Reward,
                'initial_balance' => 30000, // RM300
                'current_balance' => 15000, // RM150 remaining
                'status' => GiftCardStatus::Active,
                'activated_at' => now()->subMonths(2),
                'last_used_at' => now()->subDays(10),
                'metadata' => [
                    'points_converted' => 15000,
                    'tier' => 'gold',
                    'member_id' => 'MEM-00123',
                ],
            ],
            [
                'code' => 'REWARD-PLATINUM-001',
                'type' => GiftCardType::Reward,
                'initial_balance' => 50000, // RM500
                'current_balance' => 50000,
                'status' => GiftCardStatus::Active,
                'activated_at' => now(),
                'metadata' => [
                    'points_converted' => 25000,
                    'tier' => 'platinum',
                    'member_id' => 'MEM-00001',
                ],
            ],
        ];

        // ============================================
        // CORPORATE GIFT CARDS (B2B Bulk Purchase)
        // ============================================
        $corporateCards = [
            [
                'code' => 'CORP-TECHCORP-001',
                'type' => GiftCardType::Corporate,
                'initial_balance' => 100000, // RM1000
                'current_balance' => 85000, // RM850 remaining
                'status' => GiftCardStatus::Active,
                'activated_at' => now()->subMonths(3),
                'last_used_at' => now()->subDays(7),
                'expires_at' => now()->addMonths(9),
                'metadata' => [
                    'company' => 'TechCorp Sdn Bhd',
                    'department' => 'Human Resources',
                    'purpose' => 'Employee Recognition',
                    'batch_id' => 'BATCH-TC-2024-001',
                ],
            ],
            [
                'code' => 'CORP-BANKPLUS-001',
                'type' => GiftCardType::Corporate,
                'initial_balance' => 50000, // RM500
                'current_balance' => 50000,
                'status' => GiftCardStatus::Active,
                'activated_at' => now(),
                'expires_at' => now()->addYear(),
                'metadata' => [
                    'company' => 'BankPlus Malaysia',
                    'department' => 'Marketing',
                    'purpose' => 'Customer Rewards',
                    'batch_id' => 'BATCH-BP-2024-001',
                ],
            ],
            [
                'code' => 'CORP-EXPIRED-001',
                'type' => GiftCardType::Corporate,
                'initial_balance' => 25000, // RM250
                'current_balance' => 18000, // RM180 - forfeited
                'status' => GiftCardStatus::Expired,
                'activated_at' => now()->subYear(),
                'last_used_at' => now()->subMonths(8),
                'expires_at' => now()->subMonth(),
                'metadata' => [
                    'company' => 'OldCorp Ltd',
                    'purpose' => 'Event Prize',
                ],
            ],
        ];

        // ============================================
        // EDGE CASE GIFT CARDS (Demo Scenarios)
        // ============================================
        $edgeCaseCards = [
            [
                'code' => 'GC-SUSPENDED-001',
                'type' => GiftCardType::Standard,
                'initial_balance' => 20000,
                'current_balance' => 15000,
                'status' => GiftCardStatus::Suspended,
                'activated_at' => now()->subMonth(),
                'metadata' => [
                    'suspension_reason' => 'Fraud investigation',
                    'suspended_at' => now()->subDays(3)->toIso8601String(),
                ],
            ],
            [
                'code' => 'GC-CANCELLED-001',
                'type' => GiftCardType::Standard,
                'initial_balance' => 10000,
                'current_balance' => 10000,
                'status' => GiftCardStatus::Cancelled,
                'metadata' => [
                    'cancellation_reason' => 'Customer refund request',
                    'refunded_amount' => 10000,
                ],
            ],
            [
                'code' => 'GC-EXPIRING-SOON',
                'type' => GiftCardType::Standard,
                'initial_balance' => 15000,
                'current_balance' => 8500,
                'status' => GiftCardStatus::Active,
                'activated_at' => now()->subMonths(11),
                'expires_at' => now()->addDays(5),
                'metadata' => [
                    'design' => 'standard',
                    'reminder_sent' => true,
                ],
            ],
        ];

        // Create all gift cards and assign to random users
        $allCards = array_merge(
            $standardCards,
            $openValueCards,
            $promoCards,
            $rewardCards,
            $corporateCards,
            $edgeCaseCards
        );

        $createdCount = 0;
        foreach ($allCards as $cardData) {
            $randomUser = $users->random();

            $giftCard = GiftCard::create(array_merge([
                'currency' => 'MYR',
                'purchaser_type' => $randomUser ? User::class : null,
                'purchaser_id' => $randomUser?->id,
                'recipient_type' => $users->random()?->id ? User::class : null,
                'recipient_id' => $users->random()?->id,
            ], $cardData));

            // Create transaction history for cards with usage
            if ($giftCard->initial_balance !== $giftCard->current_balance) {
                $this->createTransactionHistory($giftCard, $users);
            }

            $createdCount++;
        }

        $this->command->info("   ✓ Created {$createdCount} gift cards across " . count(['Standard', 'Open Value', 'Promotional', 'Reward', 'Corporate']) . ' types');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, User>  $users
     */
    private function createTransactionHistory(GiftCard $giftCard, $users): void
    {
        $usedAmount = $giftCard->initial_balance - $giftCard->current_balance;

        // Create activation transaction
        GiftCardTransaction::create([
            'gift_card_id' => $giftCard->id,
            'type' => GiftCardTransactionType::Activate,
            'amount' => 0,
            'balance_before' => $giftCard->initial_balance,
            'balance_after' => $giftCard->initial_balance,
            'description' => 'Gift card activated',
            'actor_type' => User::class,
            'actor_id' => $users->first()?->id,
            'created_at' => $giftCard->activated_at ?? now()->subMonth(),
        ]);

        // Create redemption transactions
        if ($usedAmount > 0) {
            $remainingToUse = $usedAmount;
            $currentBalance = $giftCard->initial_balance;
            $transactionDate = $giftCard->activated_at ?? now()->subMonth();

            while ($remainingToUse > 0) {
                $amount = min($remainingToUse, rand(2000, 15000));
                $transactionDate = $transactionDate->copy()->addDays(rand(1, 7));

                GiftCardTransaction::create([
                    'gift_card_id' => $giftCard->id,
                    'type' => GiftCardTransactionType::Redeem,
                    'amount' => -$amount,
                    'balance_before' => $currentBalance,
                    'balance_after' => $currentBalance - $amount,
                    'description' => 'Redeemed for order ORD-' . Str::upper(Str::random(8)),
                    'reference_type' => 'App\\Models\\Order',
                    'reference_id' => Str::uuid()->toString(),
                    'actor_type' => User::class,
                    'actor_id' => $users->random()?->id,
                    'created_at' => $transactionDate,
                ]);

                $currentBalance -= $amount;
                $remainingToUse -= $amount;
            }
        }
    }
}
