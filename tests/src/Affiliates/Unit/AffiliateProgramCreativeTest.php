<?php

declare(strict_types=1);

use AIArmada\Affiliates\Contracts\CreativeAssetFilesystem;
use AIArmada\Affiliates\Drivers\Media\SpatieCreativeAssetFilesystem;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Features\Programs\Actions\ProcessCreativeAssetCleanup;
use AIArmada\Affiliates\Features\Programs\Api\CreativeAdministration;
use AIArmada\Affiliates\Features\Programs\Api\ProgramAdministration;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateCreativeAssetCleanup;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramCreative;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileNameNotAllowed;
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

    $affiliateCode = 'CREAT001';

    $creative = new AffiliateProgramCreative([
        'destination_url' => 'https://example.com/landing',
    ]);

    $url = $creative->getTrackingUrl($affiliateCode);

    expect($url)->toBe('https://example.com/landing?ref=CREAT001');
});

test('AffiliateProgramCreative getTrackingUrl uses ampersand for existing query string', function (): void {
    config(['affiliates.links.parameter' => 'ref']);

    $affiliateCode = 'CREAT002';

    $creative = new AffiliateProgramCreative([
        'destination_url' => 'https://example.com/landing?source=email',
    ]);

    $url = $creative->getTrackingUrl($affiliateCode);

    expect($url)->toBe('https://example.com/landing?source=email&ref=CREAT002');
});

test('AffiliateProgramCreative getEmbedCode for banner type', function (): void {
    config(['affiliates.links.parameter' => 'ref']);

    $affiliateCode = 'EMBED001';

    $creative = AffiliateProgramCreative::create([
        'tracking_code' => 'EMBED',
        'type' => 'banner',
        'name' => 'Test Banner',
        'destination_url' => 'https://example.com/landing',
        'width' => 300,
        'height' => 250,
    ]);

    $media = $creative->addMedia(UploadedFile::fake()->image('banner.png'))->toMediaCollection('creative_asset');

    $embedCode = $creative->getEmbedCode($affiliateCode);

    expect($embedCode)->toContain($media->getUrl());
    expect($embedCode)->toContain('<a href="');
    expect($embedCode)->toContain('<img src="');
    expect($embedCode)->toContain('width="300"');
    expect($embedCode)->toContain('height="250"');
});

test('AffiliateProgramCreative getEmbedCode for text link type', function (): void {
    config(['affiliates.links.parameter' => 'ref']);

    $affiliateCode = 'EMBED002';

    $creative = new AffiliateProgramCreative([
        'type' => 'text_link',
        'name' => 'Click Here!',
        'destination_url' => 'https://example.com/landing',
    ]);

    $embedCode = $creative->getEmbedCode($affiliateCode);

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
    app(CreativeAdministration::class)->update(null, (string) $creative->getKey(), [], UploadedFile::fake()->image('second.png')->get());
    $second = $creative->fresh()->getFirstMedia('creative_asset');
    expect($creative->fresh()->getMedia('creative_asset'))->toHaveCount(1)
        ->and($creative->fresh()->getAssetUrl())->toBe($second->getFullUrl());
    Storage::disk('public')->assertMissing($firstPath);

    $secondPath = $second->getPathRelativeToRoot();
    app(CreativeAdministration::class)->delete(null, (string) $creative->getKey());
    expect($second->fresh())->toBeNull();
    Storage::disk('public')->assertMissing($secondPath);
});

test('banner embeds require an attached asset', function (): void {
    $creative = new AffiliateProgramCreative([
        'type' => 'banner',
        'destination_url' => 'https://example.com',
    ]);

    expect($creative->getEmbedCode('REF'))->toBeNull();
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
        $creative = AffiliateProgramCreative::query()->create([
            'program_id' => $program->id,
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
    $creative = AffiliateProgramCreative::query()->create([
        'program_id' => $program->id,
        'type' => 'image', 'name' => 'Program Asset', 'tracking_code' => 'PROGRAM-MEDIA',
    ]);
    $media = $creative->addMedia(UploadedFile::fake()->image('program.png'))->toMediaCollection('creative_asset');
    $path = $media->getPathRelativeToRoot();

    app(ProgramAdministration::class)->delete($program->id);

    expect($creative->fresh())->toBeNull()
        ->and($media->fresh())->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('creative tracking replaces conflicting referral parameters before the fragment and encodes the affiliate code', function (): void {
    config(['affiliates.links.parameter' => 'ref']);

    $creative = new AffiliateProgramCreative([
        'destination_url' => 'https://example.com/landing?source=email&ref=OLD&%72ef=OTHER#details',
    ]);

    expect($creative->getTrackingUrl('A+B&test'))
        ->toBe('https://example.com/landing?source=email&ref=A%2BB%26test#details');
});

test('creative links reject non-http destinations', function (string $destination): void {
    $creative = new AffiliateProgramCreative([
        'type' => 'text_link',
        'destination_url' => $destination,
    ]);

    expect($creative->getTrackingUrl('REF'))->toBeNull()
        ->and($creative->getEmbedCode('REF'))->toBeNull();
})->with(['javascript:alert(1)', 'data:text/html,hello', 'ftp://example.com/file']);

test('creative administration validates destinations and fixes the program identity', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Creative Administration', 'slug' => 'creative-admin', 'status' => ProgramStatus::Active,
        'commission_type' => 'percentage', 'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);
    $api = app(CreativeAdministration::class);
    $data = ['name' => 'Text', 'type' => 'text_link', 'tracking_code' => 'ADMIN', 'destination_url' => 'https://example.com'];
    expect(fn () => $api->create($program->id, [...$data, 'destination_url' => 'javascript:alert(1)']))
        ->toThrow(ValidationException::class);

    $id = $api->create($program->id, [...$data, 'program_id' => null]);
    $api->update($program->id, $id, ['program_id' => null, 'name' => 'Updated']);
    $creative = AffiliateProgramCreative::query()->findOrFail($id);
    expect($creative->program_id)->toBe($program->id)
        ->and($creative->name)->toBe('Updated');

    expect(fn () => $api->delete(null, $id))->toThrow(ModelNotFoundException::class);
    expect($api->delete($program->id, $id))->toBeTrue()
        ->and($creative->fresh())->toBeNull();
});

test('tenant-owned contexts cannot administer general creatives', function (): void {
    $owner = User::create(['name' => 'Creative Tenant', 'email' => 'creative-tenant@example.com', 'password' => 'secret']);
    config(['affiliates.owner.enabled' => true, 'affiliates.owner.include_global' => false]);

    OwnerContext::withOwner($owner, function (): void {
        expect(fn () => app(CreativeAdministration::class)->create(null, [
            'name' => 'Global', 'type' => 'text_link', 'tracking_code' => 'GLOBAL',
        ]))->toThrow(AuthorizationException::class);
    });
});

test('rolled back program deletion preserves creative media rows and files', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Rollback Media Program', 'slug' => 'rollback-media-program', 'status' => ProgramStatus::Active,
        'commission_type' => 'percentage', 'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);
    $creative = AffiliateProgramCreative::query()->create([
        'program_id' => $program->id,
        'type' => 'image', 'name' => 'Rollback Asset', 'tracking_code' => 'ROLLBACK-MEDIA',
    ]);
    $media = $creative->addMedia(UploadedFile::fake()->image('rollback.png'))->toMediaCollection('creative_asset');
    $path = $media->getPathRelativeToRoot();

    expect(fn () => $program->getConnection()->transaction(function () use ($program, $media, $path): void {
        app(ProgramAdministration::class)->delete($program->id);
        expect($media->fresh())->toBeNull();
        Storage::disk('public')->assertExists($path);

        throw new RuntimeException('force rollback');
    }))->toThrow(RuntimeException::class, 'force rollback');

    expect($program->fresh())->not->toBeNull()
        ->and($creative->fresh())->not->toBeNull()
        ->and($media->fresh())->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('creative replacement rolls back metadata and media without destroying the previous asset', function (): void {
    $api = app(CreativeAdministration::class);
    $firstBytes = UploadedFile::fake()->image('first.png')->get();
    $secondBytes = UploadedFile::fake()->image('second.png', 32, 32)->get();
    $id = $api->create(null, ['type' => 'image', 'name' => 'First', 'tracking_code' => 'REPLACE'], $firstBytes);
    $creative = AffiliateProgramCreative::query()->findOrFail($id);
    $first = $creative->getFirstMedia('creative_asset');
    $firstPath = $first->getPathRelativeToRoot();
    $replacementPath = null;

    expect(function () use ($creative, $api, $id, $secondBytes, $firstPath, &$replacementPath): void {
        $creative->getConnection()->transaction(function () use ($api, $id, $secondBytes, $firstPath, &$replacementPath): void {
            $api->update(null, $id, ['name' => 'Second'], $secondBytes);
            $replacementPath = AffiliateProgramCreative::query()->findOrFail($id)->getFirstMedia('creative_asset')->getPathRelativeToRoot();
            Storage::disk('public')->assertExists($firstPath);
            Storage::disk('public')->assertExists($replacementPath);

            throw new RuntimeException('force rollback');
        });
    })->toThrow(RuntimeException::class, 'force rollback');

    expect($creative->fresh()->name)->toBe('First')
        ->and($creative->fresh()->getFirstMedia('creative_asset')->getKey())->toBe($first->getKey());
    Storage::disk('public')->assertExists($firstPath);
    Storage::disk('public')->assertMissing($replacementPath);
});

test('creative uploads reject active content and retain the current asset', function (): void {
    $api = app(CreativeAdministration::class);
    $bytes = UploadedFile::fake()->image('asset.png')->get();
    $id = $api->create(null, ['type' => 'image', 'name' => 'Asset', 'tracking_code' => 'SAFE'], $bytes);
    $creative = AffiliateProgramCreative::query()->findOrFail($id);
    $first = $creative->getFirstMedia('creative_asset');

    expect(fn () => $api->update(null, $id, ['name' => 'Unsafe'], '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))
        ->toThrow(ValidationException::class);

    expect($creative->fresh()->name)->toBe('Asset')
        ->and($creative->fresh()->getFirstMedia('creative_asset')->getKey())->toBe($first->getKey());
    Storage::disk('public')->assertExists($first->getPathRelativeToRoot());
});

test('replacing or clearing a creative asset preserves unrelated media collections', function (): void {
    $api = app(CreativeAdministration::class);
    $id = $api->create(null, ['type' => 'image', 'name' => 'Asset', 'tracking_code' => 'COLLECTION'], UploadedFile::fake()->image('first.png')->get());
    $creative = AffiliateProgramCreative::query()->findOrFail($id);
    $other = $creative->addMedia(UploadedFile::fake()->image('other.png'))->toMediaCollection('attachments', 'public');
    $path = $other->getPathRelativeToRoot();

    $api->update(null, $id, [], UploadedFile::fake()->image('second.png')->get());
    expect($other->fresh())->not->toBeNull();
    Storage::disk('public')->assertExists($path);

    $api->update(null, $id, [], removeAsset: true);
    expect($creative->fresh()->getFirstMedia('creative_asset'))->toBeNull()
        ->and($other->fresh())->not->toBeNull();
    Storage::disk('public')->assertExists($path);

    $api->delete(null, $id);
    expect($other->fresh())->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('creative upload size is limited by bytes rather than multibyte characters', function (): void {
    $bytes = UploadedFile::fake()->image('asset.png')->get() . str_repeat('é', 2048);
    config(['media-library.max_file_size' => mb_strlen($bytes, '8bit') - 1]);

    expect(fn () => app(CreativeAdministration::class)->create(null, [
        'type' => 'image', 'name' => 'Oversized', 'tracking_code' => 'SIZE',
    ], $bytes))->toThrow(ValidationException::class);
    expect(AffiliateProgramCreative::query()->where('tracking_code', 'SIZE')->exists())->toBeFalse();
});

test('failed post-commit creative cleanup is retained and can be retried', function (): void {
    $api = app(CreativeAdministration::class);
    $id = $api->create(null, ['type' => 'image', 'name' => 'Retry', 'tracking_code' => 'RETRY'], UploadedFile::fake()->image('retry.png')->get());
    $creative = AffiliateProgramCreative::query()->findOrFail($id);
    $asset = $creative->getFirstMedia('creative_asset');
    $path = $asset->getPathRelativeToRoot();

    $this->mock(CreativeAssetFilesystem::class)->shouldReceive('removeDetached')->once()->andThrow(new RuntimeException('storage unavailable'));

    expect(app(CreativeAdministration::class)->delete(null, $id))->toBeTrue()
        ->and($asset->fresh())->toBeNull()
        ->and(AffiliateCreativeAssetCleanup::query()->count())->toBe(1)
        ->and(AffiliateCreativeAssetCleanup::query()->firstOrFail()->attempt_count)->toBe(1);
    Storage::disk('public')->assertExists($path);

    app()->instance(CreativeAssetFilesystem::class, new SpatieCreativeAssetFilesystem);

    expect(app(ProcessCreativeAssetCleanup::class)->drain(100))->toBe(0)
        ->and(AffiliateCreativeAssetCleanup::query()->count())->toBe(0);
    Storage::disk('public')->assertMissing($path);
});

test('failed creative staging removes its temporary file and preserves the current asset', function (): void {
    $api = app(CreativeAdministration::class);
    $id = $api->create(null, ['type' => 'image', 'name' => 'Original', 'tracking_code' => 'STAGING'], UploadedFile::fake()->image('original.png')->get());
    $creative = AffiliateProgramCreative::query()->findOrFail($id);
    $original = $creative->getFirstMedia('creative_asset');
    $before = glob(sys_get_temp_dir() . '/affiliate-creative*') ?: [];

    config(['media-library.allowed_extensions' => ['txt']]);

    try {
        expect(fn () => $api->update(null, $id, [], UploadedFile::fake()->image('second.png')->get()))
            ->toThrow(FileNameNotAllowed::class);
    } finally {
        config(['media-library.allowed_extensions' => null]);
    }

    $after = glob(sys_get_temp_dir() . '/affiliate-creative*') ?: [];

    expect(array_values(array_diff($after, $before)))->toBe([])
        ->and($creative->fresh()->name)->toBe('Original')
        ->and($creative->fresh()->getFirstMedia('creative_asset')->getKey())->toBe($original->getKey())
        ->and(AffiliateCreativeAssetCleanup::query()->count())->toBe(0);
    Storage::disk('public')->assertExists($original->getPathRelativeToRoot());
});

test('creative updates reject contradictory replacement and removal before changing state', function (): void {
    $api = app(CreativeAdministration::class);
    $id = $api->create(null, ['type' => 'image', 'name' => 'Original', 'tracking_code' => 'CONTRADICTION'], UploadedFile::fake()->image('original.png')->get());
    $creative = AffiliateProgramCreative::query()->findOrFail($id);
    $original = $creative->getFirstMedia('creative_asset');
    $before = glob(sys_get_temp_dir() . '/affiliate-creative*') ?: [];

    expect(fn () => $api->update(null, $id, ['name' => 'Changed'], UploadedFile::fake()->image('second.png')->get(), true))
        ->toThrow(ValidationException::class, 'Choose either a replacement or removal.');

    $after = glob(sys_get_temp_dir() . '/affiliate-creative*') ?: [];

    expect(array_values(array_diff($after, $before)))->toBe([])
        ->and($creative->fresh()->name)->toBe('Original')
        ->and($creative->fresh()->getFirstMedia('creative_asset')->getKey())->toBe($original->getKey())
        ->and(AffiliateCreativeAssetCleanup::query()->count())->toBe(0);
});

test('creative cleanup performs storage io outside the cleanup row transaction', function (): void {
    $api = app(CreativeAdministration::class);
    $id = $api->create(null, ['type' => 'image', 'name' => 'Transaction', 'tracking_code' => 'CLEANUP-TX'], UploadedFile::fake()->image('transaction.png')->get());
    $baselineLevel = DB::connection()->transactionLevel();
    $observedLevel = null;

    $this->mock(CreativeAssetFilesystem::class)
        ->shouldReceive('removeDetached')
        ->once()
        ->andReturnUsing(function () use (&$observedLevel): void {
            $observedLevel = DB::connection()->transactionLevel();
        });

    expect(app(CreativeAdministration::class)->delete(null, $id))->toBeTrue()
        ->and($observedLevel)->toBe($baselineLevel)
        ->and(AffiliateCreativeAssetCleanup::query()->count())->toBe(0);
});

test('creative cleanup claims a queued record once during nested drains', function (): void {
    $api = app(CreativeAdministration::class);
    $id = $api->create(null, ['type' => 'image', 'name' => 'Claim', 'tracking_code' => 'CLEANUP-CLAIM'], UploadedFile::fake()->image('claim.png')->get());
    $calls = 0;

    $this->mock(CreativeAssetFilesystem::class)
        ->shouldReceive('removeDetached')
        ->andReturnUsing(function () use (&$calls): void {
            $calls++;
            app(ProcessCreativeAssetCleanup::class)->drain(100);
        });

    expect(app(CreativeAdministration::class)->delete(null, $id))->toBeTrue()
        ->and($calls)->toBe(1)
        ->and(AffiliateCreativeAssetCleanup::query()->count())->toBe(0);
});

test('stale creative cleanup claims are retried without losing work', function (): void {
    $api = app(CreativeAdministration::class);
    $id = $api->create(null, ['type' => 'image', 'name' => 'Stale', 'tracking_code' => 'CLEANUP-STALE'], UploadedFile::fake()->image('stale.png')->get());
    $path = AffiliateProgramCreative::query()->findOrFail($id)->getFirstMedia('creative_asset')->getPathRelativeToRoot();

    $this->mock(CreativeAssetFilesystem::class)->shouldReceive('removeDetached')->once()->andThrow(new RuntimeException('storage unavailable'));

    expect(app(CreativeAdministration::class)->delete(null, $id))->toBeTrue();
    $record = AffiliateCreativeAssetCleanup::query()->firstOrFail();
    $record->forceFill([
        'claim_token' => (string) Str::uuid(),
        'claimed_at' => CarbonImmutable::now()->subSeconds(600),
    ])->save();

    app()->instance(CreativeAssetFilesystem::class, new SpatieCreativeAssetFilesystem);

    expect(app(ProcessCreativeAssetCleanup::class)->drain(100))->toBe(0)
        ->and($record->fresh())->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('failed rollback cleanup of a new creative asset is retained for retry', function (): void {
    $api = app(CreativeAdministration::class);
    $id = $api->create(null, ['type' => 'image', 'name' => 'Original', 'tracking_code' => 'ROLLBACK-RETRY'], UploadedFile::fake()->image('original.png')->get());
    $creative = AffiliateProgramCreative::query()->findOrFail($id);
    $original = $creative->getFirstMedia('creative_asset')->getPathRelativeToRoot();
    $replacement = null;

    $this->mock(CreativeAssetFilesystem::class)->shouldReceive('removeDetached')->andThrow(new RuntimeException('storage unavailable'));

    expect(function () use ($creative, $id, &$replacement): void {
        $creative->getConnection()->transaction(function () use ($id, &$replacement): void {
            app(CreativeAdministration::class)->update(null, $id, [], UploadedFile::fake()->image('replacement.png')->get());
            $replacement = AffiliateProgramCreative::query()->findOrFail($id)->getFirstMedia('creative_asset')->getPathRelativeToRoot();

            throw new RuntimeException('force creative rollback');
        });
    })->toThrow(RuntimeException::class, 'force creative rollback');

    expect(AffiliateCreativeAssetCleanup::query()->count())->toBe(1);
    Storage::disk('public')->assertExists($original);
    Storage::disk('public')->assertExists($replacement);

    app()->instance(CreativeAssetFilesystem::class, new SpatieCreativeAssetFilesystem);

    expect(app(ProcessCreativeAssetCleanup::class)->drain(100))->toBe(0)
        ->and(AffiliateCreativeAssetCleanup::query()->count())->toBe(0);
    Storage::disk('public')->assertExists($original);
    Storage::disk('public')->assertMissing($replacement);
});
