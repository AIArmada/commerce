<?php

declare(strict_types=1);

it('conversion resource schemas use neutral reference fields', function (): void {
    $repositoryRoot = dirname(__DIR__, 4);

    $infolistSource = file_get_contents($repositoryRoot . '/packages/filament-affiliates/src/Resources/AffiliateConversionResource/Schemas/AffiliateConversionInfolist.php');
    $tableSource = file_get_contents($repositoryRoot . '/packages/filament-affiliates/src/Resources/AffiliateConversionResource/Tables/AffiliateConversionsTable.php');

    expect($infolistSource)
        ->toContain("TextEntry::make('external_reference')")
        ->toContain("TextEntry::make('subject_key')")
        ->toContain("Section::make('Cart Integration')")
        ->and($tableSource)
        ->toContain("TextColumn::make('external_reference')")
        ->toContain("FilamentPermission::hasAnyAbility(['affiliate_conversion.update', 'affiliate.approve'])")
        ->not->toContain("->label('Order / Ref')");
});

it('fraud review surfaces share dual-permission authorization semantics', function (): void {
    $repositoryRoot = dirname(__DIR__, 4);

    $fraudResourceSource = file_get_contents($repositoryRoot . '/packages/filament-affiliates/src/Resources/AffiliateFraudSignalResource.php');
    $fraudPageSource = file_get_contents($repositoryRoot . '/packages/filament-affiliates/src/Pages/FraudReviewPage.php');
    $fraudWidgetSource = file_get_contents($repositoryRoot . '/packages/filament-affiliates/src/Widgets/FraudAlertWidget.php');

    expect($fraudResourceSource)
        ->toContain("FilamentPermission::hasAnyAbility(['affiliate.approve', 'affiliates.fraud.update'])")
        ->and($fraudPageSource)
        ->toContain("FilamentPermission::hasAnyAbility(['affiliate.approve', 'affiliates.fraud.update'])")
        ->and($fraudWidgetSource)
        ->toContain("FilamentPermission::hasAnyAbility(['affiliate.approve', 'affiliates.fraud.update'])");
});

it('affiliate portal views and schemas use state helpers instead of enum value properties', function (): void {
    $repositoryRoot = dirname(__DIR__, 4);

    $dashboardSource = file_get_contents($repositoryRoot . '/packages/filament-affiliates/resources/views/pages/portal/dashboard.blade.php');
    $infolistSource = file_get_contents($repositoryRoot . '/packages/filament-affiliates/src/Resources/AffiliateConversionResource/Schemas/AffiliateConversionInfolist.php');
    $tableSource = file_get_contents($repositoryRoot . '/packages/filament-affiliates/src/Resources/AffiliateResource/Tables/AffiliatesTable.php');

    expect($dashboardSource)
        ->toContain(':color="$affiliate->status->color()"')
        ->toContain('{{ $affiliate->status->label() }}')
        ->toContain(':color="$conversion->status->color()"')
        ->toContain('{{ $conversion->status->label() }}')
        ->not->toContain('status->value')
        ->and($infolistSource)
        ->toContain('ConversionStatus::fromString($state)->color()')
        ->toContain('ConversionStatus::fromString($state)->label()')
        ->not->toContain('$state?->value ?? $state')
        ->and($tableSource)
        ->toContain('AffiliateStatus::fromString($state)->color()');
});

it('affiliate portal ships package-owned styling hooks instead of relying on app themes', function (): void {
    $repositoryRoot = dirname(__DIR__, 4);

    $dashboardSource = file_get_contents($repositoryRoot . '/packages/filament-affiliates/resources/views/pages/portal/dashboard.blade.php');
    $linksSource = file_get_contents($repositoryRoot . '/packages/filament-affiliates/resources/views/pages/portal/links.blade.php');
    $conversionsSource = file_get_contents($repositoryRoot . '/packages/filament-affiliates/resources/views/pages/portal/conversions.blade.php');
    $payoutsSource = file_get_contents($repositoryRoot . '/packages/filament-affiliates/resources/views/pages/portal/payouts.blade.php');
    $stylesheetSource = file_get_contents($repositoryRoot . '/packages/filament-affiliates/resources/css/affiliate-portal.css');

    expect($dashboardSource)
        ->toContain('fia-portal-hero')
        ->toContain('fia-portal-stats')
        ->toContain('fia-portal-status')
        ->not->toContain('bg-gradient-to-r')
        ->not->toContain('!p-4')
        ->and($linksSource)
        ->toContain('fia-portal-inline-code')
        ->and($conversionsSource)
        ->toContain('fia-portal-summary-grid')
        ->and($payoutsSource)
        ->toContain('fia-portal-summary-grid')
        ->and($stylesheetSource)
        ->toContain('.fia-portal-hero')
        ->toContain('.fia-portal-status .fi-badge')
        ->toContain('.fia-portal-stats');
});

it('AffiliatePayoutsTable uses canonical payout status values', function (): void {
    $repositoryRoot = dirname(__DIR__, 4);
    $source = file_get_contents($repositoryRoot . '/packages/filament-affiliates/src/Resources/AffiliatePayoutResource/Tables/AffiliatePayoutsTable.php');

    expect($source)
        ->toContain('PayoutStatus::options()')
        ->toContain('CompletedPayout::value()')
        ->toContain('ProcessingPayout::value()')
        ->toContain('FailedPayout::value()')
        ->not->toContain("updateStatus(\$payout, 'paid')")
        ->not->toContain("updateStatus(\$payout, 'queued')");
});
