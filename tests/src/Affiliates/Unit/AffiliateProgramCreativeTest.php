<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\States\Active;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// AffiliateProgramCreative Tests
test('AffiliateProgramCreative can be created with required fields', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Creative Test Program',
        'slug' => 'creative-test-program',
        'status' => ProgramStatus::Active,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $creative = AffiliateProgramCreative::create([
        'program_id' => $program->id,
        'type' => 'banner',
        'name' => 'Test Banner',
        'asset_url' => 'https://cdn.example.com/banner.png',
        'destination_url' => 'https://example.com/landing',
        'tracking_code' => 'BANNER_001',
        'width' => 300,
        'height' => 250,
    ]);

    expect($creative)->toBeInstanceOf(AffiliateProgramCreative::class);
    expect($creative->name)->toBe('Test Banner');
});

test('AffiliateProgramCreative has program relationship', function (): void {
    $creative = new AffiliateProgramCreative;

    expect($creative->program())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateProgramCreative getTrackingUrl appends affiliate code', function (): void {
    config(['affiliates.links.parameter' => 'ref']);

    $affiliate = Affiliate::create([
        'code' => 'CREAT001',
        'name' => 'Creative Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $creative = new AffiliateProgramCreative([
        'destination_url' => 'https://example.com/landing',
    ]);

    $url = $creative->getTrackingUrl($affiliate);

    expect($url)->toBe('https://example.com/landing?ref=CREAT001');
});

test('AffiliateProgramCreative getTrackingUrl uses ampersand for existing query string', function (): void {
    config(['affiliates.links.parameter' => 'ref']);

    $affiliate = Affiliate::create([
        'code' => 'CREAT002',
        'name' => 'Query Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $creative = new AffiliateProgramCreative([
        'destination_url' => 'https://example.com/landing?source=email',
    ]);

    $url = $creative->getTrackingUrl($affiliate);

    expect($url)->toBe('https://example.com/landing?source=email&ref=CREAT002');
});

test('AffiliateProgramCreative getEmbedCode for banner type', function (): void {
    config(['affiliates.links.parameter' => 'ref']);

    $affiliate = Affiliate::create([
        'code' => 'EMBED001',
        'name' => 'Embed Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $creative = new AffiliateProgramCreative([
        'type' => 'banner',
        'name' => 'Test Banner',
        'asset_url' => 'https://cdn.example.com/banner.png',
        'destination_url' => 'https://example.com/landing',
        'width' => 300,
        'height' => 250,
    ]);

    $embedCode = $creative->getEmbedCode($affiliate);

    expect($embedCode)->toContain('<a href="');
    expect($embedCode)->toContain('<img src="');
    expect($embedCode)->toContain('width="300"');
    expect($embedCode)->toContain('height="250"');
});

test('AffiliateProgramCreative getEmbedCode for text link type', function (): void {
    config(['affiliates.links.parameter' => 'ref']);

    $affiliate = Affiliate::create([
        'code' => 'EMBED002',
        'name' => 'Text Link Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $creative = new AffiliateProgramCreative([
        'type' => 'text_link',
        'name' => 'Click Here!',
        'destination_url' => 'https://example.com/landing',
    ]);

    $embedCode = $creative->getEmbedCode($affiliate);

    expect($embedCode)->toContain('<a href="');
    expect($embedCode)->toContain('Click Here!</a>');
});

test('AffiliateProgramCreative getDimensions returns formatted string', function (): void {
    $creative = new AffiliateProgramCreative([
        'width' => 300,
        'height' => 250,
    ]);

    expect($creative->getDimensions())->toBe('300x250');
});

test('AffiliateProgramCreative getDimensions returns null when dimensions missing', function (): void {
    $creative = new AffiliateProgramCreative([
        'width' => null,
        'height' => null,
    ]);

    expect($creative->getDimensions())->toBeNull();
});
