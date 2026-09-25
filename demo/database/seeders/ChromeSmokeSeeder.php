<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Links\Models\Link;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Deterministic fixtures for the real-Chrome affiliate surfaces test.
 *
 * Resets its own rows (CHROME-* markers) on every run so the browser
 * script can reverse the leg and the conversion repeatedly.
 */
final class ChromeSmokeSeeder extends Seeder
{
    public function run(): void
    {
        // Reversal companions keep the original reference, so one prefix
        // covers both the conversion and its compensating row.
        AffiliateConversion::query()->where('external_reference', 'like', 'CHROME-CONV%')->delete();

        NetworkConversionLeg::query()->where('external_reference', 'like', 'CHROME-LEG%')->delete();

        $trackedLinkIds = AffiliateOfferLink::query()
            ->whereHas('offer', fn ($query) => $query->where('slug', 'chrome-offer'))
            ->pluck('link_id')
            ->all();
        AffiliateOfferLink::query()->whereHas('offer', fn ($query) => $query->where('slug', 'chrome-offer'))->delete();

        if ($trackedLinkIds !== []) {
            Link::query()->whereKey($trackedLinkIds)->delete();
        }

        AffiliateOffer::query()->where('slug', 'chrome-offer')->delete();

        $site = AffiliateSite::firstOrCreate(
            ['domain' => 'chrome.example.com'],
            [
                'name' => 'CHROME-SITE',
                'status' => AffiliateSite::STATUS_VERIFIED,
                'verified_at' => now(),
            ],
        );

        $offer = AffiliateOffer::create([
            'site_id' => $site->getKey(),
            'name' => 'CHROME-OFFER',
            'slug' => 'chrome-offer',
            'status' => OfferStatus::Published,
            'visibility' => OfferVisibility::Public,
            'rate_base_bp' => 1500,
            'network_fee_bp' => 200,
            'currency' => 'MYR',
            'cookie_days' => 30,
            'requires_approval' => false,
            'landing_url' => 'https://chrome.example.com/landing',
        ]);

        $affiliate = Affiliate::firstOrCreate(
            ['code' => 'CHROME-AFF'],
            [
                'name' => 'Chrome Affiliate',
                'status' => Active::class,
                'commission_type' => 'percentage',
                'commission_rate' => 500,
                'currency' => 'MYR',
            ],
        );

        $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId((string) $affiliate->getKey())->create();

        NetworkConversionLeg::create([
            'link_id' => $link->getKey(),
            'offer_id' => $offer->getKey(),
            'site_id' => $site->getKey(),
            'affiliate_id' => (string) $affiliate->getKey(),
            'link_code' => $link->link->slug ?? Str::random(16),
            'revenue_minor' => 89900,
            'revenue_currency' => 'MYR',
            'commission_minor' => 13485,
            'commission_currency' => 'MYR',
            'fee_minor' => 269,
            'fee_bp' => 200,
            'payout_minor' => 13216,
            'external_reference' => 'CHROME-LEG-001',
            'status' => LegStatus::Posted,
            'occurred_at' => now(),
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliate->getKey(),
            'affiliate_code' => $affiliate->code,
            'external_reference' => 'CHROME-CONV-001',
            'subject_key' => 'checkout:chrome-001',
            'status' => ApprovedConversion::class,
            'occurred_at' => now(),
            'subtotal_minor' => 89900,
            'value_minor' => 89900,
            'commission_minor' => 4495,
            'commission_currency' => 'MYR',
            'origin' => 'network',
            'source_ref' => 'CHROME-LEG-001',
        ]);
    }
}
