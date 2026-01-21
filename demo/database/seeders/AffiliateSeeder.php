<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\Orders\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class AffiliateSeeder extends Seeder
{
    public function run(): void
    {
        // Skip affiliate seeding for now due to schema changes
        return;
        $this->createTopAffiliates();
        $this->createRegularAffiliates();
        $this->createInactiveAffiliates();
        $this->createTouchpointsAndConversions();
    }

    private function createTopAffiliates(): void
    {
        // Top affiliate - influencer
        $influencer = Affiliate::create([
            'code' => 'INFLUENCER-MAYA',
            'name' => 'Maya\'s Tech Reviews',
            'description' => 'Top tech influencer with 500K followers',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000, // 10%
            'currency' => 'MYR',
            'contact_email' => 'maya@techreviews.com',
            'website_url' => 'https://mayatechreviews.com',
            'payout_terms' => 'monthly',
            'tracking_domain' => 'ref.mayatech.com',
            'default_voucher_code' => 'MAYA10',
            'activated_at' => now()->subMonths(6),
            'metadata' => [
                'platform' => 'YouTube',
                'followers' => 500000,
                'niche' => 'Technology',
            ],
        ]);

        // Top affiliate - blogger
        Affiliate::create([
            'code' => 'LIFESTYLE-AMIR',
            'name' => 'Amir Lifestyle Blog',
            'description' => 'Popular lifestyle blogger',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 800, // 8%
            'currency' => 'MYR',
            'contact_email' => 'amir@lifestyleblog.com',
            'website_url' => 'https://amirlifestyle.com',
            'payout_terms' => 'monthly',
            'default_voucher_code' => 'AMIR5',
            'activated_at' => now()->subMonths(4),
            'metadata' => [
                'platform' => 'Blog',
                'monthly_visitors' => 100000,
                'niche' => 'Lifestyle',
            ],
        ]);

        // Top affiliate - business partner
        Affiliate::create([
            'code' => 'PARTNER-TECHMART',
            'name' => 'TechMart Partner',
            'description' => 'Strategic business partner',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Fixed,
            'commission_rate' => 5000, // RM 50 per sale
            'currency' => 'MYR',
            'contact_email' => 'partnerships@techmart.com',
            'website_url' => 'https://techmart.com.my',
            'payout_terms' => 'weekly',
            'activated_at' => now()->subMonths(8),
            'metadata' => [
                'partnership_type' => 'strategic',
                'tier' => 'gold',
            ],
        ]);
    }

    private function createRegularAffiliates(): void
    {
        $affiliateData = [
            [
                'code' => 'AFF-SARAH',
                'name' => 'Sarah\'s Fashion Corner',
                'commission_rate' => 500, // 5%
                'email' => 'sarah@fashioncorner.com',
                'platform' => 'Instagram',
            ],
            [
                'code' => 'AFF-AHMAD',
                'name' => 'Ahmad Tech Tips',
                'commission_rate' => 600, // 6%
                'email' => 'ahmad@techtips.my',
                'platform' => 'TikTok',
            ],
            [
                'code' => 'AFF-LISA',
                'name' => 'Lisa Beauty Hub',
                'commission_rate' => 700, // 7%
                'email' => 'lisa@beautyhub.com',
                'platform' => 'YouTube',
            ],
            [
                'code' => 'AFF-KUMAR',
                'name' => 'Kumar Deals',
                'commission_rate' => 400, // 4%
                'email' => 'kumar@deals.my',
                'platform' => 'Telegram',
            ],
            [
                'code' => 'AFF-NURUL',
                'name' => 'Nurul Home & Living',
                'commission_rate' => 550, // 5.5%
                'email' => 'nurul@homeliving.my',
                'platform' => 'Facebook',
            ],
        ];

        foreach ($affiliateData as $data) {
            Affiliate::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => 'Affiliate partner',
                'status' => AffiliateStatus::Active,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => $data['commission_rate'],
                'currency' => 'MYR',
                'contact_email' => $data['email'],
                'payout_terms' => 'monthly',
                'activated_at' => now()->subDays(rand(30, 180)),
                'metadata' => [
                    'platform' => $data['platform'],
                ],
            ]);
        }
    }

    private function createInactiveAffiliates(): void
    {
        // Pending affiliate
        Affiliate::create([
            'code' => 'PENDING-NEW',
            'name' => 'New Applicant',
            'description' => 'Pending approval',
            'status' => AffiliateStatus::Pending,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 500,
            'currency' => 'MYR',
            'contact_email' => 'newapplicant@example.com',
            'payout_terms' => 'monthly',
        ]);

        // Suspended affiliate
        Affiliate::create([
            'code' => 'SUSPENDED-OLD',
            'name' => 'Suspended Partner',
            'description' => 'Account suspended due to policy violation',
            'status' => AffiliateStatus::Suspended,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 600,
            'currency' => 'MYR',
            'contact_email' => 'suspended@example.com',
            'payout_terms' => 'monthly',
            'activated_at' => now()->subMonths(3),
            'metadata' => [
                'suspension_reason' => 'Terms violation',
                'suspended_at' => now()->subDays(14)->toDateTimeString(),
            ],
        ]);

        // Inactive affiliate
        Affiliate::create([
            'code' => 'INACTIVE-PARTNER',
            'name' => 'Inactive Partner',
            'description' => 'No activity for 90 days',
            'status' => AffiliateStatus::Inactive,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 500,
            'currency' => 'MYR',
            'contact_email' => 'inactive@example.com',
            'payout_terms' => 'monthly',
            'activated_at' => now()->subMonths(6),
        ]);
    }

    private function createTouchpointsAndConversions(): void
    {
        $affiliates = Affiliate::where('status', AffiliateStatus::Active)->get();
        $orders = Order::whereNotNull('affiliate_code')->get();

        if ($affiliates->isEmpty()) {
            return;
        }

        // Create touchpoints for each active affiliate
        foreach ($affiliates as $affiliate) {
            // Simulate clicks/visits
            for ($i = 0; $i < rand(10, 50); $i++) {
                AffiliateTouchpoint::create([
                    'affiliate_id' => $affiliate->id,
                    'visitor_id' => Str::uuid()->toString(),
                    'ip_address' => fake()->ipv4(),
                    'user_agent' => fake()->userAgent(),
                    'referrer_url' => 'https://'.fake()->domainName(),
                    'landing_page' => '/products/'.Str::random(8),
                    'utm_source' => $affiliate->code,
                    'utm_medium' => fake()->randomElement(['social', 'email', 'banner', 'video']),
                    'utm_campaign' => fake()->randomElement(['summer_sale', 'new_arrivals', 'flash_deal']),
                    'created_at' => now()->subDays(rand(1, 30)),
                ]);
            }

            // Create conversions
            $conversionCount = rand(2, 8);
            for ($i = 0; $i < $conversionCount; $i++) {
                $orderValue = rand(5000, 50000);
                $commissionAmount = $affiliate->commission_type === CommissionType::Percentage
                    ? (int) ($orderValue * $affiliate->commission_rate / 10000)
                    : $affiliate->commission_rate;

                AffiliateConversion::create([
                    'affiliate_id' => $affiliate->id,
                    'order_id' => $orders->isNotEmpty() ? $orders->random()->id : null,
                    'order_value' => $orderValue,
                    'commission_amount' => $commissionAmount,
                    'currency' => 'MYR',
                    'status' => fake()->randomElement(['pending', 'approved', 'paid']),
                    'converted_at' => now()->subDays(rand(1, 60)),
                    'metadata' => [
                        'order_items_count' => rand(1, 5),
                    ],
                ]);
            }
        }
    }
}
