<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\Pages\Portal\PortalConversions;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalDashboard;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalLinks;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalPayouts;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalProfile;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalPrograms;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalSupport;

// PortalDashboard Tests
it('PortalDashboard returns view data with hasAffiliate', function (): void {
    $page = new PortalDashboard;
    $viewData = $page->getViewData();

    expect($viewData)
        ->toBeArray()
        ->toHaveKey('hasAffiliate')
        ->toHaveKey('totalEarnings')
        ->toHaveKey('availableEarnings')
        ->toHaveKey('pendingEarnings')
        ->toHaveKey('totalClicks')
        ->toHaveKey('totalConversions')
        ->toHaveKey('conversionRate');
});

// PortalLinks Tests
it('PortalLinks mount initializes targetUrl', function (): void {
    $page = new PortalLinks;
    $page->mount();

    expect($page->targetUrl)->toBe(mb_rtrim((string) config('app.url'), '/') . '/');
});

it('PortalLinks returns view data', function (): void {
    $page = new PortalLinks;
    $viewData = $page->getViewData();

    expect($viewData)
        ->toBeArray()
        ->toHaveKey('hasAffiliate')
        ->toHaveKey('defaultLink');
});

// PortalConversions Tests
it('PortalConversions returns view data', function (): void {
    $page = new PortalConversions;
    $viewData = $page->getViewData();

    expect($viewData)
        ->toBeArray()
        ->toHaveKey('hasAffiliate')
        ->toHaveKey('totalConversions')
        ->toHaveKey('totalEarnings')
        ->toHaveKey('pendingEarnings');
});

// PortalPayouts Tests
it('PortalPayouts returns view data', function (): void {
    $page = new PortalPayouts;
    $viewData = $page->getViewData();

    expect($viewData)
        ->toBeArray()
        ->toHaveKey('hasAffiliate')
        ->toHaveKey('totalPaid')
        ->toHaveKey('availableEarnings')
        ->toHaveKey('pendingEarnings');
});

// PortalProfile Tests
it('PortalProfile returns view data', function (): void {
    $page = new PortalProfile;
    $viewData = $page->getViewData();

    expect($viewData)
        ->toBeArray()
        ->toHaveKey('hasAffiliate')
        ->toHaveKey('payoutMethodOptions')
        ->and($viewData['payoutMethodOptions'])->toBeArray()->not->toBeEmpty();
});

// PortalPrograms Tests
it('PortalPrograms returns view data', function (): void {
    $page = new PortalPrograms;
    $viewData = $page->getViewData();

    expect($viewData)
        ->toBeArray()
        ->toHaveKey('hasAffiliate')
        ->toHaveKey('programs')
        ->toHaveKey('creativeCount');
});

// PortalSupport Tests
it('PortalSupport returns view data', function (): void {
    $page = new PortalSupport;
    $viewData = $page->getViewData();

    expect($viewData)
        ->toBeArray()
        ->toHaveKey('hasAffiliate')
        ->toHaveKey('tickets')
        ->toHaveKey('taxDocuments');
});
