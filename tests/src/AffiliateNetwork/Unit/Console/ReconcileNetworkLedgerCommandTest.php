<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\RecordNetworkConversion;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use Illuminate\Support\Str;

function reconcileFixtures(string $domain, bool $clean): AffiliateOffer
{
    $site = AffiliateSite::factory()->verified()->create(['domain' => $domain]);
    $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
        'landing_url' => "https://{$domain}/landing",
        'requires_approval' => false,
        'rate_base_bp' => 1000,
        'rate_fixed_minor' => null,
        'currency' => 'MYR',
    ]);
    $affiliate = createTestAffiliate();
    $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId((string) $affiliate->getKey())->create();

    if ($clean) {
        app(RecordNetworkConversion::class)->execute(
            offerId: (string) $offer->getKey(),
            affiliateId: (string) $affiliate->getKey(),
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'RECON-' . $domain,
            linkId: (string) $link->getKey(),
        );
    } else {
        NetworkConversionLeg::factory()->create([
            'link_id' => (string) $link->getKey(),
            'offer_id' => (string) $offer->getKey(),
            'site_id' => (string) $site->getKey(),
            'affiliate_id' => (string) $affiliate->getKey(),
            'status' => LegStatus::Posted,
            'revenue_minor' => 10000,
            'revenue_currency' => 'MYR',
            'commission_minor' => 1000,
            'commission_currency' => 'MYR',
            'fee_minor' => 0,
            'payout_minor' => 1000,
            'external_reference' => 'RECON-DIRTY-' . $domain,
        ]);
    }

    return $offer;
}

describe('reconcile command', function (): void {
    test('clean offers reconcile successfully', function (): void {
        reconcileFixtures('recon-clean.example', true);

        $this->artisan('affiliate-network:reconcile')
            ->expectsOutputToContain('Reconciled')
            ->assertSuccessful();
    });

    test('mismatches report without failing by default', function (): void {
        reconcileFixtures('recon-dirty.example', false);

        $this->artisan('affiliate-network:reconcile')
            ->expectsOutputToContain('mismatch')
            ->assertSuccessful();
    });

    test('mismatches fail with the flag for scheduling', function (): void {
        reconcileFixtures('recon-flag.example', false);

        $this->artisan('affiliate-network:reconcile', ['--fail-on-mismatch' => true])
            ->assertFailed();
    });

    test('single offers scope the report', function (): void {
        $offer = reconcileFixtures('recon-scope.example', true);

        $this->artisan('affiliate-network:reconcile', ['--offer' => (string) $offer->getKey()])
            ->expectsOutputToContain('1 offer(s)')
            ->assertSuccessful();

        $this->artisan('affiliate-network:reconcile', ['--offer' => (string) Str::uuid()])
            ->assertSuccessful();
    });
});
