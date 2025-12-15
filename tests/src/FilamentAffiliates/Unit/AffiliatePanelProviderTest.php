<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\AffiliatePanelProvider;
use Filament\Panel;

it('AffiliatePanelProvider can be instantiated', function (): void {
    $provider = new AffiliatePanelProvider(app());

    expect($provider)->toBeInstanceOf(AffiliatePanelProvider::class);
});

it('AffiliatePanelProvider creates panel with correct id', function (): void {
    config(['filament-affiliates.portal.panel_id' => 'affiliate']);
    config(['filament-affiliates.portal.path' => 'affiliate']);
    config(['filament-affiliates.portal.brand_name' => 'Affiliate Portal']);
    config(['filament-affiliates.portal.primary_color' => '#6366f1']);
    config(['filament-affiliates.portal.login_enabled' => true]);
    config(['filament-affiliates.portal.registration_enabled' => false]);
    config(['filament-affiliates.portal.auth_guard' => 'web']);
    config(['filament-affiliates.portal.features' => [
        'dashboard' => true,
        'links' => true,
        'conversions' => true,
        'payouts' => true,
    ]]);

    $provider = new AffiliatePanelProvider(app());
    $panel = $provider->panel(Panel::make());

    expect($panel)->toBeInstanceOf(Panel::class)
        ->and($panel->getId())->toBe('affiliate');
});
