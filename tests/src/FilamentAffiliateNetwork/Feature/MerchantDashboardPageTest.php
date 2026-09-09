<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Pages\MerchantDashboardPage;

describe('MerchantDashboardPage', function (): void {
    test('owner-enabled site counts stay inside the current merchant owner', function (): void {
        config(['affiliate-network.owner.enabled' => true]);

        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        OwnerContext::withOwner($ownerA, fn () => AffiliateSite::factory()->verified()->forOwner($ownerA)->create());
        OwnerContext::withOwner($ownerB, fn () => AffiliateSite::factory()->verified()->forOwner($ownerB)->create());
        OwnerContext::withOwner(null, fn () => AffiliateSite::factory()->verified()->create());

        $page = app(MerchantDashboardPage::class);

        $counts = OwnerContext::withOwner($ownerA, fn (): array => [
            $page->getSitesCount(),
            $page->getVerifiedSitesCount(),
        ]);

        expect($counts)->toBe([1, 1]);
    });

    test('merchant dashboard does not embed network-wide widgets', function (): void {
        $page = app(MerchantDashboardPage::class);

        $method = new ReflectionMethod($page, 'getHeaderWidgets');

        expect($method->invoke($page))->toBe([]);
    });
});
