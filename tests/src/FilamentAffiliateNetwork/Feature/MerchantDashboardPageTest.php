<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Pages\MerchantDashboardPage;

describe('MerchantDashboardPage', function (): void {
    test('owner-enabled site counts include tenant-owned sites in network-wide admin view', function (): void {
        config(['affiliate-network.owner.enabled' => true]);

        $owner = User::factory()->create();

        OwnerContext::withOwner($owner, fn () => AffiliateSite::factory()->verified()->forOwner($owner)->create());
        OwnerContext::withOwner(null, fn () => AffiliateSite::factory()->verified()->create());

        $page = app(MerchantDashboardPage::class);

        expect($page->getSitesCount())->toBe(2)
            ->and($page->getVerifiedSitesCount())->toBe(2);
    });
});