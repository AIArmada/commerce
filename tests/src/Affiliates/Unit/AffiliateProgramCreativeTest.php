<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

beforeEach(function (): void {
    app()->register(MediaLibraryServiceProvider::class);
    if (! Schema::hasTable('media')) {
        (require base_path('vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub'))->up();
    }
    Storage::fake('public');
});

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

    $creative = AffiliateProgramCreative::create([
        'tracking_code' => 'EMBED',
        'type' => 'banner',
        'name' => 'Test Banner',
        'destination_url' => 'https://example.com/landing',
        'width' => 300,
        'height' => 250,
    ]);

    $media = $creative->addMedia(UploadedFile::fake()->image('banner.png'))->toMediaCollection('creative_asset');

    $embedCode = $creative->getEmbedCode($affiliate);

    expect($embedCode)->toContain($media->getUrl());
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

test('creative media replaces its asset and is removed when the creative is deleted', function (): void {
    $creative = AffiliateProgramCreative::create([
        'type' => 'image',
        'name' => 'Media Creative',
        'tracking_code' => 'MEDIA',
    ]);

    expect($creative->getAssetUrl())->toBeNull();

    $first = $creative->addMedia(UploadedFile::fake()->image('first.png'))->toMediaCollection('creative_asset');
    expect($creative->fresh()->getAssetUrl())->toBe($first->getFullUrl());
    $firstPath = $first->getPathRelativeToRoot();
    Storage::disk('public')->assertExists($firstPath);

    $creative->refresh();
    $second = $creative->addMedia(UploadedFile::fake()->image('second.png'))->toMediaCollection('creative_asset');
    expect($creative->fresh()->getMedia('creative_asset'))->toHaveCount(1)
        ->and($creative->fresh()->getAssetUrl())->toBe($second->getFullUrl());
    Storage::disk('public')->assertMissing($firstPath);

    $secondPath = $second->getPathRelativeToRoot();
    $creative->delete();
    expect($second->fresh())->toBeNull();
    Storage::disk('public')->assertMissing($secondPath);
});

test('banner embeds require an attached asset', function (): void {
    $creative = new AffiliateProgramCreative([
        'type' => 'banner',
        'destination_url' => 'https://example.com',
    ]);

    expect($creative->getEmbedCode(new Affiliate(['code' => 'REF'])))->toBeNull();
});

test('creative media reads and program assignments respect the owner boundary', function (): void {
    $ownerA = User::create(['name' => 'Owner A', 'email' => 'media-a@example.com', 'password' => 'secret']);
    $ownerB = User::create(['name' => 'Owner B', 'email' => 'media-b@example.com', 'password' => 'secret']);
    config(['affiliates.owner.enabled' => true, 'affiliates.owner.include_global' => false]);

    $creativeA = OwnerContext::withOwner($ownerA, function (): AffiliateProgramCreative {
        $program = AffiliateProgram::create([
            'name' => 'Owner A Program', 'slug' => 'media-a', 'status' => ProgramStatus::Active,
            'commission_type' => 'percentage', 'default_commission_rate_basis_points' => 1000,
            'cookie_lifetime_days' => 30,
        ]);
        $creative = $program->creatives()->create([
            'type' => 'image', 'name' => 'Owner A Asset', 'tracking_code' => 'MEDIA-A',
        ]);
        $creative->addMedia(UploadedFile::fake()->image('owner-a.png'))->toMediaCollection('creative_asset');

        return $creative;
    });

    OwnerContext::withOwner($ownerB, function () use ($creativeA): void {
        expect(AffiliateProgramCreative::with('media')->find($creativeA->getKey()))->toBeNull();
        expect(fn () => AffiliateProgramCreative::create([
            'program_id' => $creativeA->program_id,
            'type' => 'image', 'name' => 'Cross Owner Asset', 'tracking_code' => 'CROSS',
        ]))->toThrow(AuthorizationException::class);
    });

    OwnerContext::withOwner($ownerA, function () use ($creativeA): void {
        expect(AffiliateProgramCreative::with('media')->findOrFail($creativeA->getKey())->getAssetUrl())
            ->toBe($creativeA->getAssetUrl());
    });
});

test('deleting a program removes its creative media and files', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Media Program', 'slug' => 'media-program', 'status' => ProgramStatus::Active,
        'commission_type' => 'percentage', 'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);
    $creative = $program->creatives()->create([
        'type' => 'image', 'name' => 'Program Asset', 'tracking_code' => 'PROGRAM-MEDIA',
    ]);
    $media = $creative->addMedia(UploadedFile::fake()->image('program.png'))->toMediaCollection('creative_asset');
    $path = $media->getPathRelativeToRoot();

    $program->delete();

    expect($creative->fresh())->toBeNull()
        ->and($media->fresh())->toBeNull();
    Storage::disk('public')->assertMissing($path);
});
