<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliateNetwork\Pages\MerchantDashboardPage;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;
use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use AIArmada\FilamentAffiliateNetwork\Widgets\NetworkStatsWidget;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::define('affiliate-network.admin', fn (User $user): bool => $user->email === 'admin@example.com');
});

it('denies network-wide surfaces without the configured admin ability', function (): void {
    expect(NetworkAdminAccess::allows())->toBeFalse()
        ->and(AffiliateSiteResource::canViewAny())->toBeFalse()
        ->and(MerchantDashboardPage::canAccess())->toBeFalse()
        ->and(NetworkStatsWidget::canView())->toBeFalse();
});

it('allows network-wide surfaces only for an admin user', function (): void {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $regularUser = User::factory()->create(['email' => 'regular@example.com']);

    expect(NetworkAdminAccess::allows($admin))->toBeTrue()
        ->and(NetworkAdminAccess::allows($regularUser))->toBeFalse();

    $this->actingAs($admin);

    expect(AffiliateOfferResource::canViewAny())->toBeTrue()
        ->and(MerchantDashboardPage::canAccess())->toBeTrue()
        ->and(NetworkStatsWidget::canView())->toBeTrue();
});
