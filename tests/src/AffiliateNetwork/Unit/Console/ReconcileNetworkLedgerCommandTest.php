<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;

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
        app(OfferLinkService::class)->recordConversion($link, 10000, 'MYR', 'RECON-' . $domain);
    } else {
        app(OfferLinkService::class)->recordConversion($link, 10000, 'MYR');
    }

    return $offer;
}

describe('reconcile command', function (): void {
    test('clean offers reconcile successfully', function (): void {
        reconcileFixtures('recon-clean.example', true);

        $this->artisan('affiliate-network:reconcile')
            ->expectsOutputToContain('reconcile.')
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

        $this->artisan('affiliate-network:reconcile', ['--offer' => (string) \Illuminate\Support\Str::uuid()])
            ->assertFailed();
    });
});
