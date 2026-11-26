<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkAdministration;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Programs\Api\CreativeAdministration;
use AIArmada\Affiliates\Features\Programs\Api\ProgramAdministration;
use AIArmada\Affiliates\Features\Programs\Api\ProgramEnrollment;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\States\Active;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\Filesystem;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Tests\Support\CreativeRaceFilesystemAdapter;
use Tests\Support\EngineRace;

function creativeRaceGate(): string
{
    $gate = sys_get_temp_dir() . '/affiliate-creative-race-' . Str::uuid();
    mkdir($gate, 0777, true);
    mkdir($gate . '/disk', 0777, true);
    file_put_contents($gate . '/hold', '1');

    return $gate;
}

function creativeRaceStorage(string $gate): void
{
    Storage::extend('creative-race', function ($app, array $config): FilesystemAdapter {
        $adapter = new CreativeRaceFilesystemAdapter($config['root'], $config['gate']);

        return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
    });

    config(['filesystems.disks.public' => [
        'driver' => 'creative-race',
        'root' => $gate . '/disk',
        'gate' => $gate,
        'url' => 'http://localhost/storage',
        'visibility' => 'public',
        'throw' => false,
    ]]);

    Storage::forgetDisk('public');
}

function creativeRaceProvision(): void
{
    $root = dirname(__DIR__, 4);

    if (! Schema::hasTable('media')) {
        (require $root . '/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    }

    if (! Schema::hasTable(config('links.database.tables.links', 'tracked_links'))) {
        $migrator = app('migrator');
        $migrator->usingConnection(config('database.default'), fn () => $migrator->run([$root . '/packages/links/database/migrations']));
    }
}

function creativeRaceWaitForFile(string $path, int $timeoutMs = 20000): void
{
    $deadline = microtime(true) + ($timeoutMs / 1000);

    while (! is_file($path)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Creative race timed out waiting for ' . $path);
        }

        usleep(1000);
    }
}

function creativeRaceWaitForLockedQuery(string $table, int $timeoutMs = 20000): bool
{
    $deadline = microtime(true) + ($timeoutMs / 1000);

    do {
        $waiting = DB::select(
            "select count(*) as aggregate from pg_stat_activity where datname = current_database() and wait_event_type = 'Lock' and state = 'active' and query ilike ?",
            ['%' . $table . '%'],
        );

        if ((int) ($waiting[0]->aggregate ?? 0) > 0) {
            return true;
        }

        usleep(1000);
    } while (microtime(true) <= $deadline);

    return false;
}

function creativeRaceProgram(): AffiliateProgram
{
    return AffiliateProgram::query()->create([
        'name' => 'Creative Race ' . Str::random(8),
        'slug' => 'creative-race-' . mb_strtolower(Str::random(10)),
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
        'default_commission_rate_basis_points' => 1000,
        'commission_type' => CommissionType::Percentage,
        'cookie_lifetime_days' => 30,
    ]);
}

function creativeRaceAffiliate(string $suffix): Affiliate
{
    return Affiliate::query()->create([
        'code' => 'RACE-' . $suffix . '-' . mb_strtoupper(Str::random(8)),
        'name' => 'Race Affiliate ' . $suffix,
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 0,
        'currency' => 'USD',
    ]);
}

function creativeRaceRelease(string $gate): void
{
    file_put_contents($gate . '/release', '1');
}

function creativeRaceCleanup(string $gate): void
{
    File::deleteDirectory($gate);
}

test('shared program locks allow enrollment and tracking links during an in-flight creative upload', function (): void {
    $this->useEngine();
    app()->register(MediaLibraryServiceProvider::class);
    creativeRaceProvision();
    config(['affiliates.links.allowed_hosts' => ['ilmu360.test']]);

    $program = creativeRaceProgram();
    $member = creativeRaceAffiliate('MEMBER');
    $linkAffiliate = creativeRaceAffiliate('LINK');
    $programId = (string) $program->getKey();
    $memberId = (string) $member->getKey();
    $linkAffiliateId = (string) $linkAffiliate->getKey();
    $bytes = UploadedFile::fake()->image('race.png')->get();
    $gate = creativeRaceGate();
    creativeRaceStorage($gate);

    try {
        $results = EngineRace::run([
            'uploader' => function () use ($programId, $bytes): array {
                $id = app(CreativeAdministration::class)->create($programId, [
                    'type' => 'image', 'name' => 'Race Upload', 'tracking_code' => 'RACE-UPLOAD',
                ], $bytes);

                return ['creative' => $id];
            },
            'probe' => function () use ($gate, $programId, $memberId, $linkAffiliateId): array {
                creativeRaceWaitForFile($gate . '/started');
                $started = microtime(true);
                $membership = app(ProgramEnrollment::class)->join($memberId, $programId);
                $link = app(TrackingLinkAdministration::class)->create($linkAffiliateId, 'https://ilmu360.test/race', [
                    'program_id' => $programId,
                ]);
                app(TrackingLinkAdministration::class)->update($link->id, ['program_id' => $programId]);
                $elapsed = (microtime(true) - $started) * 1000;
                $released = is_file($gate . '/release');
                creativeRaceRelease($gate);

                return [
                    'membership' => $membership->id,
                    'link' => $link->id,
                    'released' => $released,
                    'elapsed_ms' => round($elapsed, 2),
                ];
            },
        ]);

        expect($results['probe']['released'])->toBeFalse()
            ->and($results['uploader']['creative'])->toBeString()
            ->and(AffiliateProgramCreative::query()->whereKey($results['uploader']['creative'])->exists())->toBeTrue();
    } finally {
        creativeRaceCleanup($gate);
    }
});

test('program updates remain exclusive while a creative upload holds the shared program lock', function (): void {
    $this->useEngine();
    app()->register(MediaLibraryServiceProvider::class);
    creativeRaceProvision();

    $program = creativeRaceProgram();
    $programId = (string) $program->getKey();
    $bytes = UploadedFile::fake()->image('update-race.png')->get();
    $gate = creativeRaceGate();
    creativeRaceStorage($gate);

    try {
        $results = EngineRace::run([
            'uploader' => function () use ($programId, $bytes): array {
                return ['creative' => app(CreativeAdministration::class)->create($programId, [
                    'type' => 'image', 'name' => 'Update Race', 'tracking_code' => 'RACE-UPDATE',
                ], $bytes)];
            },
            'updater' => function () use ($gate, $programId): array {
                creativeRaceWaitForFile($gate . '/started');
                file_put_contents($gate . '/updating', '1');
                app(ProgramAdministration::class)->update($programId, ['name' => 'Updated During Race']);
                file_put_contents($gate . '/updated', '1');

                return ['updated' => true];
            },
            'probe' => function () use ($gate): array {
                creativeRaceWaitForFile($gate . '/started');
                creativeRaceWaitForFile($gate . '/updating');
                $waiting = creativeRaceWaitForLockedQuery('affiliate_programs');
                $completedBeforeRelease = is_file($gate . '/updated');
                creativeRaceRelease($gate);
                creativeRaceWaitForFile($gate . '/updated');

                return ['waiting' => $waiting, 'completed_before_release' => $completedBeforeRelease];
            },
        ]);

        expect($results['probe']['waiting'])->toBeTrue()
            ->and($results['probe']['completed_before_release'])->toBeFalse()
            ->and(AffiliateProgram::query()->whereKey($programId)->value('name'))->toBe('Updated During Race');
    } finally {
        creativeRaceCleanup($gate);
    }
});

test('program deletes remain exclusive while a creative upload holds the shared program lock', function (): void {
    $this->useEngine();
    app()->register(MediaLibraryServiceProvider::class);
    creativeRaceProvision();

    $program = creativeRaceProgram();
    $programId = (string) $program->getKey();
    $bytes = UploadedFile::fake()->image('delete-race.png')->get();
    $gate = creativeRaceGate();
    creativeRaceStorage($gate);

    try {
        $results = EngineRace::run([
            'uploader' => function () use ($programId, $bytes): array {
                return ['creative' => app(CreativeAdministration::class)->create($programId, [
                    'type' => 'image', 'name' => 'Delete Race', 'tracking_code' => 'RACE-DELETE',
                ], $bytes)];
            },
            'deleter' => function () use ($gate, $programId): array {
                creativeRaceWaitForFile($gate . '/started');
                file_put_contents($gate . '/deleting', '1');
                app(ProgramAdministration::class)->delete($programId);
                file_put_contents($gate . '/deleted', '1');

                return ['deleted' => true];
            },
            'probe' => function () use ($gate): array {
                creativeRaceWaitForFile($gate . '/started');
                creativeRaceWaitForFile($gate . '/deleting');
                $waiting = creativeRaceWaitForLockedQuery('affiliate_programs');
                $completedBeforeRelease = is_file($gate . '/deleted');
                creativeRaceRelease($gate);
                creativeRaceWaitForFile($gate . '/deleted');

                return ['waiting' => $waiting, 'completed_before_release' => $completedBeforeRelease];
            },
        ]);

        expect($results['probe']['waiting'])->toBeTrue()
            ->and($results['probe']['completed_before_release'])->toBeFalse()
            ->and(AffiliateProgram::query()->whereKey($programId)->exists())->toBeFalse()
            ->and(AffiliateProgramCreative::query()->whereKey($results['uploader']['creative'])->exists())->toBeFalse();
    } finally {
        creativeRaceCleanup($gate);
    }
});

test('same-creative replacement stays exclusive while an unrelated enrollment proceeds', function (): void {
    $this->useEngine();
    app()->register(MediaLibraryServiceProvider::class);
    creativeRaceProvision();

    $program = creativeRaceProgram();
    $member = creativeRaceAffiliate('REPLACE-MEMBER');
    $programId = (string) $program->getKey();
    $memberId = (string) $member->getKey();
    $creativeId = app(CreativeAdministration::class)->create($programId, [
        'type' => 'image', 'name' => 'Replacement Race', 'tracking_code' => 'RACE-REPLACE',
    ]);
    $firstBytes = UploadedFile::fake()->image('first-race.png')->get();
    $secondBytes = UploadedFile::fake()->image('second-race.png', 32, 32)->get();
    $gate = creativeRaceGate();
    creativeRaceStorage($gate);

    try {
        $results = EngineRace::run([
            'uploader' => function () use ($programId, $creativeId, $firstBytes): array {
                app(CreativeAdministration::class)->update($programId, $creativeId, [], $firstBytes);
                $asset = AffiliateProgramCreative::query()->findOrFail($creativeId)->getFirstMedia('creative_asset');

                return ['path' => $asset->getPathRelativeToRoot()];
            },
            'replacer' => function () use ($gate, $programId, $creativeId, $secondBytes): array {
                creativeRaceWaitForFile($gate . '/started');
                file_put_contents($gate . '/replacing', '1');
                app(CreativeAdministration::class)->update($programId, $creativeId, [], $secondBytes);
                file_put_contents($gate . '/replaced', '1');
                $asset = AffiliateProgramCreative::query()->findOrFail($creativeId)->getFirstMedia('creative_asset');

                return ['path' => $asset->getPathRelativeToRoot()];
            },
            'probe' => function () use ($gate, $programId, $memberId): array {
                creativeRaceWaitForFile($gate . '/started');
                creativeRaceWaitForFile($gate . '/replacing');
                $waiting = creativeRaceWaitForLockedQuery('affiliate_program_creatives');
                $membership = app(ProgramEnrollment::class)->join($memberId, $programId);
                $completedBeforeRelease = is_file($gate . '/replaced');
                creativeRaceRelease($gate);
                creativeRaceWaitForFile($gate . '/replaced');

                return [
                    'waiting' => $waiting,
                    'completed_before_release' => $completedBeforeRelease,
                    'membership' => $membership->id,
                ];
            },
        ]);

        $creative = AffiliateProgramCreative::query()->findOrFail($creativeId);

        expect($results['probe']['waiting'])->toBeTrue()
            ->and($results['probe']['completed_before_release'])->toBeFalse()
            ->and($creative->getMedia('creative_asset'))->toHaveCount(1)
            ->and($creative->getFirstMedia('creative_asset')->getPathRelativeToRoot())->toBe($results['replacer']['path']);
        Storage::disk('public')->assertMissing($results['uploader']['path']);
        Storage::disk('public')->assertExists($results['replacer']['path']);
    } finally {
        creativeRaceCleanup($gate);
    }
});
