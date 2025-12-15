<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\Pages\Portal\PortalConversions;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalDashboard;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalLinks;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalPayouts;

// PortalDashboard Tests
it('PortalDashboard can be instantiated', function (): void {
    $page = new PortalDashboard;

    expect($page)->toBeInstanceOf(PortalDashboard::class);
});

it('PortalDashboard has correct navigation label', function (): void {
    expect(PortalDashboard::getNavigationLabel())->toBe('Dashboard');
});

it('PortalDashboard has correct title', function (): void {
    $page = new PortalDashboard;

    expect($page->getTitle())->toBe('Affiliate Dashboard');
});

it('PortalDashboard returns view data with hasAffiliate', function (): void {
    $page = new PortalDashboard;
    $viewData = $page->getViewData();

    expect($viewData)
        ->toBeArray()
        ->toHaveKey('hasAffiliate')
        ->toHaveKey('totalEarnings')
        ->toHaveKey('pendingEarnings')
        ->toHaveKey('totalClicks')
        ->toHaveKey('totalConversions');
});

// PortalLinks Tests
it('PortalLinks can be instantiated', function (): void {
    $page = new PortalLinks;

    expect($page)->toBeInstanceOf(PortalLinks::class);
});

it('PortalLinks has correct navigation label', function (): void {
    expect(PortalLinks::getNavigationLabel())->toBe('Links');
});

it('PortalLinks has correct title', function (): void {
    $page = new PortalLinks;

    expect($page->getTitle())->toBe('Affiliate Links');
});

it('PortalLinks mount initializes targetUrl', function (): void {
    $page = new PortalLinks;
    $page->mount();

    expect($page->targetUrl)->toBe(url('/'));
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
it('PortalConversions can be instantiated', function (): void {
    $page = new PortalConversions;

    expect($page)->toBeInstanceOf(PortalConversions::class);
});

it('PortalConversions has correct navigation label', function (): void {
    expect(PortalConversions::getNavigationLabel())->toBe('Conversions');
});

it('PortalConversions has correct title', function (): void {
    $page = new PortalConversions;

    expect($page->getTitle())->toBe('Conversion History');
});

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
it('PortalPayouts can be instantiated', function (): void {
    $page = new PortalPayouts;

    expect($page)->toBeInstanceOf(PortalPayouts::class);
});

it('PortalPayouts has correct navigation label', function (): void {
    expect(PortalPayouts::getNavigationLabel())->toBe('Payouts');
});

it('PortalPayouts has correct title', function (): void {
    $page = new PortalPayouts;

    expect($page->getTitle())->toBe('Payout History');
});

it('PortalPayouts returns view data', function (): void {
    $page = new PortalPayouts;
    $viewData = $page->getViewData();

    expect($viewData)
        ->toBeArray()
        ->toHaveKey('hasAffiliate')
        ->toHaveKey('totalPaid')
        ->toHaveKey('pendingEarnings');
});
