<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\References\Actions\SyncReferenceContributors;
use AIArmada\References\Enums\ReferenceContributorRole;
use AIArmada\References\Enums\ReferenceRecordKind;
use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Models\Reference;
use AIArmada\References\Models\ReferenceContributor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\MediaCollections\Models\Observers\MediaObserver;
use Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer;
use Spatie\MediaLibrary\Support\FileRemover\DefaultFileRemover;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

final class AlternateReferenceMedia extends Media
{
    protected $connection = 'testing';

    protected $table = 'reference_alternate_media';
}

final class CrossConnectionReferenceMedia extends Media
{
    protected $connection = 'references_secondary';

    protected $table = 'reference_secondary_media';
}

function configureReferenceMedia(): void
{
    config()->set('media-library.file_namer', DefaultFileNamer::class);
    config()->set('media-library.file_remover_class', DefaultFileRemover::class);
    config()->set('media-library.path_generator', DefaultPathGenerator::class);
    config()->set('media-library.max_file_size', 1024 * 1024);
    Storage::fake('public');
    Media::observe(MediaObserver::class);
}

function addReferenceMedia(Reference $reference, string $name, ?Media $model = null): Media
{
    $model ??= new Media;

    if ($model->getConnectionName() === null) {
        $model->setConnection($reference->getConnectionName());
    }

    $media = $model->newInstance()->forceFill([
        'model_type' => $reference->getMorphClass(),
        'model_id' => $reference->getKey(),
        'uuid' => (string) Str::uuid(),
        'collection_name' => 'gallery',
        'name' => $name,
        'file_name' => $name . '.txt',
        'mime_type' => 'text/plain',
        'disk' => 'public',
        'conversions_disk' => 'public',
        'size' => 4,
        'manipulations' => [],
        'custom_properties' => [],
        'generated_conversions' => [],
        'responsive_images' => [],
        'order_column' => 1,
    ]);
    $media->save();

    Storage::disk('public')->put($media->getKey() . '/' . $media->file_name, 'data');

    return $media;
}

function makeDeletionOwner(string $email): User
{
    return User::query()->create([
        'name' => 'Deletion Owner',
        'email' => $email,
        'password' => 'secret',
    ]);
}

function makeDeletionReference(string $title, array $attributes = []): Reference
{
    return Reference::create(array_merge([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => $title,
    ], $attributes));
}

beforeEach(function (): void {
    config()->set('references.owner.enabled', true);
    config()->set('references.owner.include_global', false);
});

test('defers media removal and deleted events until commit hooks run', function (): void {
    configureReferenceMedia();

    $root = makeDeletionReference('Deferred Root');
    $child = makeDeletionReference('Deferred Child', [
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $root->getKey(),
    ]);

    $rootMedia = addReferenceMedia($root, 'root-deferred');
    $childMedia = addReferenceMedia($child, 'child-deferred');

    $hooks = captureAfterCommitHooks();
    Event::fake();

    expect($root->delete())->toBeTrue();

    expect(Reference::query()->withoutOwnerScope()->whereKey([$root->getKey(), $child->getKey()])->count())->toBe(0)
        ->and(Media::query()->whereKey([$rootMedia->getKey(), $childMedia->getKey()])->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->not->toBe([])
        ->and($root->shouldDeletePreservingMedia())->toBeFalse()
        ->and($child->shouldDeletePreservingMedia())->toBeFalse();

    Event::assertNotDispatched('eloquent.deleted: ' . Reference::class);

    $hooks();

    expect(Storage::disk('public')->allFiles())->toBe([]);

    Event::assertDispatchedTimes('eloquent.deleted: ' . Reference::class, 2);
});

test('cancelled root deletion leaves database rows and files intact', function (): void {
    configureReferenceMedia();

    $root = makeDeletionReference('Cancelled Root');
    $child = makeDeletionReference('Cancelled Child', [
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $root->getKey(),
    ]);
    $media = addReferenceMedia($child, 'cancelled-child');

    Reference::deleting(static fn (Reference $reference): ?bool => $reference->is($root) ? false : null);

    expect($root->delete())->toBeFalse()
        ->and(Reference::query()->withoutOwnerScope()->whereKey([$root->getKey(), $child->getKey()])->count())->toBe(2)
        ->and(Media::query()->whereKey($media->getKey())->exists())->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->not->toBe([]);
});

test('cancelled descendant deletion rolls back the complete cascade', function (): void {
    configureReferenceMedia();

    $root = makeDeletionReference('Descendant Cancel Root');
    $child = makeDeletionReference('Descendant Cancel Child', [
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $root->getKey(),
    ]);
    $media = addReferenceMedia($child, 'descendant-cancel');

    Reference::deleting(static fn (Reference $reference): ?bool => $reference->is($child) ? false : null);

    expect(fn (): ?bool => $root->delete())
        ->toThrow(RuntimeException::class, 'descendant deletion was cancelled');

    expect(Reference::query()->withoutOwnerScope()->whereKey([$root->getKey(), $child->getKey()])->count())->toBe(2)
        ->and(Media::query()->whereKey($media->getKey())->exists())->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->not->toBe([]);
});

test('deletes a partially loaded reference and its complete subtree', function (): void {
    $root = makeDeletionReference('Partial Root');
    $child = makeDeletionReference('Partial Child', [
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $root->getKey(),
    ]);
    $grandchild = makeDeletionReference('Partial Grandchild', [
        'record_kind' => ReferenceRecordKind::Part,
        'parent_id' => $child->getKey(),
    ]);

    $partial = Reference::query()->select('id')->whereKey($root->getKey())->firstOrFail();

    expect($partial->delete())->toBeTrue()
        ->and(Reference::query()->withoutOwnerScope()->whereKey([$root->getKey(), $child->getKey(), $grandchild->getKey()])->count())->toBe(0);
});

test('uses the configured media model for cascade deletion', function (): void {
    configureReferenceMedia();

    Schema::create('reference_alternate_media', function (Blueprint $table): void {
        $table->id();
        $table->morphs('model');
        $table->uuid()->nullable()->unique();
        $table->string('collection_name');
        $table->string('name');
        $table->string('file_name');
        $table->string('mime_type')->nullable();
        $table->string('disk');
        $table->string('conversions_disk')->nullable();
        $table->unsignedBigInteger('size');
        $table->json('manipulations');
        $table->json('custom_properties');
        $table->json('generated_conversions');
        $table->json('responsive_images');
        $table->unsignedInteger('order_column')->nullable()->index();
        $table->nullableTimestamps();
    });

    config()->set('media-library.media_model', AlternateReferenceMedia::class);

    $root = makeDeletionReference('Alternate Media Root');
    $media = addReferenceMedia($root, 'alternate-media', new AlternateReferenceMedia);

    $hooks = captureAfterCommitHooks();

    expect($root->delete())->toBeTrue()
        ->and(AlternateReferenceMedia::query()->whereKey($media->getKey())->exists())->toBeFalse()
        ->and(Storage::disk('public')->allFiles())->not->toBe([]);

    $hooks();

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('reuses distinct owner contexts and queries each owner once during cascade', function (): void {
    config()->set('references.owner.include_global', true);

    $ownerA = makeDeletionOwner('cascade-owner-a@example.test');
    $ownerB = makeDeletionOwner('cascade-owner-b@example.test');

    $root = OwnerContext::withOwner(null, fn (): Reference => makeDeletionReference('Global Cascade Root'));

    $children = [];

    foreach ([$ownerA, $ownerA, $ownerB, $ownerB] as $index => $owner) {
        $children[] = OwnerContext::withOwner($owner, fn (): Reference => makeDeletionReference(
            'Owned Cascade Child ' . $index,
            ['record_kind' => ReferenceRecordKind::Part, 'parent_id' => $root->getKey()],
        ));
    }

    $deletedOwners = [];
    Event::listen('eloquent.deleted: ' . Reference::class, static function () use (&$deletedOwners): void {
        $deletedOwners[] = OwnerContext::resolve()?->getKey();
    });

    DB::enableQueryLog();
    $hooks = captureAfterCommitHooks();

    OwnerContext::withOwner(null, function () use ($root, $hooks): void {
        $root->delete();
        $hooks();
    });

    $ownerQueries = array_filter(
        DB::getQueryLog(),
        static fn (array $query): bool => str_contains((string) $query['query'], 'from "users"'),
    );

    expect($ownerQueries)->toHaveCount(2)
        ->and(Reference::query()->withoutOwnerScope()->whereKey($root->getKey())->exists())->toBeFalse()
        ->and(Reference::query()->withoutOwnerScope()->whereKey(array_map(static fn (Reference $r): string => (string) $r->getKey(), $children))->count())->toBe(0)
        ->and($deletedOwners)->toHaveCount(5)
        ->and($deletedOwners)->toContain((string) $ownerA->getKey(), (string) $ownerB->getKey(), null);
});

test('rejects orphaned owner rows instead of treating them as global', function (): void {
    config()->set('references.owner.include_global', true);

    $owner = makeDeletionOwner('orphan-owner@example.test');
    $root = OwnerContext::withOwner(null, fn (): Reference => makeDeletionReference('Orphan Root'));
    $child = OwnerContext::withOwner($owner, fn (): Reference => makeDeletionReference('Orphan Child', [
        'record_kind' => ReferenceRecordKind::Part,
        'parent_id' => $root->getKey(),
    ]));

    $owner->delete();

    expect(fn (): ?bool => OwnerContext::withOwner(null, fn (): ?bool => $root->delete()))
        ->toThrow(RuntimeException::class, 'owner row is missing');

    expect(Reference::query()->withoutOwnerScope()->whereKey([$root->getKey(), $child->getKey()])->count())->toBe(2);
});

test('deleting a reference removes its contributor links inside the cascade', function (): void {
    $author = makeDeletionOwner('cascade-contributor@example.test');
    $root = makeDeletionReference('Contributor Cascade Root');

    (new SyncReferenceContributors)->handle(
        $root,
        ReferenceContributorRole::Author,
        $author->getMorphClass(),
        [(string) $author->getKey()],
    );

    expect(ReferenceContributor::query()->count())->toBe(1);

    $root->delete();

    expect(ReferenceContributor::query()->count())->toBe(0);
});

test('delivers deferred deletion events in the owner captured at delete time', function (): void {
    $ownerA = makeDeletionOwner('deferred-owner-a@example.test');
    $ownerB = makeDeletionOwner('deferred-owner-b@example.test');

    $root = OwnerContext::withOwner($ownerA, fn (): Reference => makeDeletionReference('Deferred Owner Root'));
    $child = OwnerContext::withOwner($ownerA, fn (): Reference => makeDeletionReference('Deferred Owner Child', [
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $root->getKey(),
    ]));

    $deleted = [];
    Event::listen('eloquent.deleted: ' . Reference::class, static function (Reference $reference) use (&$deleted): void {
        $deleted[] = [(string) $reference->getKey(), OwnerContext::resolve()?->getKey()];
    });

    DB::beginTransaction();

    try {
        expect(OwnerContext::withOwner($ownerA, fn (): ?bool => $root->delete()))->toBeTrue()
            ->and($deleted)->toBe([]);
        OwnerContext::withOwner($ownerB, static fn (): mixed => DB::commit());
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    expect($deleted)->toEqualCanonicalizing([
        [(string) $child->getKey(), (string) $ownerA->getKey()],
        [(string) $root->getKey(), (string) $ownerA->getKey()],
    ]);
});

test('quiet deletion suppresses deferred deletion events after commit', function (): void {
    configureReferenceMedia();

    $quiet = makeDeletionReference('Quiet Deletion Root');
    $withoutEvents = makeDeletionReference('Without Events Root');
    $media = addReferenceMedia($quiet, 'quiet-deletion');

    Event::fake();

    DB::beginTransaction();

    try {
        $quietDeleted = $quiet->deleteQuietly();
        $withoutEventsDeleted = Reference::withoutEvents(fn (): ?bool => $withoutEvents->delete());
    } finally {
        DB::commit();
    }

    expect($quietDeleted)->toBeTrue()
        ->and($withoutEventsDeleted)->toBeTrue()
        ->and(Media::query()->whereKey($media->getKey())->exists())->toBeFalse()
        ->and(Storage::disk('public')->allFiles())->toBe([]);

    Event::assertNotDispatched('eloquent.deleting: ' . Reference::class);
    Event::assertNotDispatched('eloquent.deleted: ' . Reference::class);
});

test('reports failing deferred listeners and continues delivery', function (): void {
    $root = makeDeletionReference('Failing Listener Root');
    $child = makeDeletionReference('Failing Listener Child', [
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $root->getKey(),
    ]);

    Exceptions::fake();
    $delivered = [];

    Event::listen('eloquent.deleted: ' . Reference::class, static function (Reference $reference) use (&$delivered, $child): void {
        $delivered[] = (string) $reference->getKey();

        if ((string) $reference->getKey() === (string) $child->getKey()) {
            throw new RuntimeException('deferred listener failed');
        }
    });

    DB::beginTransaction();

    try {
        expect($root->delete())->toBeTrue();
    } finally {
        DB::commit();
    }

    expect($delivered)->toBe([(string) $child->getKey(), (string) $root->getKey()]);
    Exceptions::assertReported(RuntimeException::class);
});

test('rejects cross-connection media models before cascade writes', function (): void {
    configureReferenceMedia();

    config()->set('database.connections.references_secondary', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    DB::purge('references_secondary');

    Schema::connection('references_secondary')->create('reference_secondary_media', function (Blueprint $table): void {
        $table->id();
        $table->morphs('model');
        $table->uuid()->nullable()->unique();
        $table->string('collection_name');
        $table->string('name');
        $table->string('file_name');
        $table->string('mime_type')->nullable();
        $table->string('disk');
        $table->string('conversions_disk')->nullable();
        $table->unsignedBigInteger('size');
        $table->json('manipulations');
        $table->json('custom_properties');
        $table->json('generated_conversions');
        $table->json('responsive_images');
        $table->unsignedInteger('order_column')->nullable()->index();
        $table->nullableTimestamps();
    });

    config()->set('media-library.media_model', CrossConnectionReferenceMedia::class);

    $root = makeDeletionReference('Cross Connection Root');
    $child = makeDeletionReference('Cross Connection Child', [
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $root->getKey(),
    ]);
    $media = addReferenceMedia($root, 'cross-connection', new CrossConnectionReferenceMedia);

    expect(fn (): ?bool => $root->delete())
        ->toThrow(RuntimeException::class, 'same database connection');

    expect(Reference::query()->withoutOwnerScope()->whereKey([$root->getKey(), $child->getKey()])->count())->toBe(2)
        ->and(CrossConnectionReferenceMedia::query()->whereKey($media->getKey())->exists())->toBeTrue();
});

test('deletePreservingMedia keeps media rows and files across the whole subtree', function (): void {
    configureReferenceMedia();

    $root = makeDeletionReference('Preserving Root');
    $child = makeDeletionReference('Preserving Child', [
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $root->getKey(),
    ]);
    $rootMedia = addReferenceMedia($root, 'preserving-root');
    $childMedia = addReferenceMedia($child, 'preserving-child');

    $hooks = captureAfterCommitHooks();
    Event::fake();

    expect($root->deletePreservingMedia())->toBeTrue();

    expect(Reference::query()->withoutOwnerScope()->whereKey([$root->getKey(), $child->getKey()])->count())->toBe(0)
        ->and(Media::query()->whereKey([$rootMedia->getKey(), $childMedia->getKey()])->count())->toBe(2)
        ->and(Storage::disk('public')->allFiles())->not->toBe([]);

    $hooks();

    expect(Storage::disk('public')->allFiles())->not->toBe([]);
    Event::assertDispatchedTimes('eloquent.deleted: ' . Reference::class, 2);
});

test('chunks large cascade writes into bounded batches', function (): void {
    $root = makeDeletionReference('Bulk Cascade Root');
    $rows = [];

    foreach (range(1, 501) as $index) {
        $rows[] = [
            'id' => (string) Str::uuid(),
            'owner_type' => $root->getAttribute('owner_type'),
            'owner_id' => $root->getAttribute('owner_id'),
            'type' => ReferenceType::Book->value,
            'record_kind' => ReferenceRecordKind::Part->value,
            'status' => ReferenceStatus::Draft->value,
            'title' => 'Bulk Cascade Child ' . $index,
            'slug' => 'bulk-cascade-child-' . $index,
            'parent_id' => $root->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    foreach (array_chunk($rows, 100) as $chunk) {
        DB::table('references')->insert($chunk);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();

    expect($root->delete())->toBeTrue();

    $queries = collect(DB::getQueryLog());
    $contributorDeletes = $queries->filter(fn (array $query): bool => str_starts_with((string) $query['query'], 'delete from "reference_contributors"'));
    $referenceDeletes = $queries->filter(fn (array $query): bool => str_starts_with((string) $query['query'], 'delete from "references"'));

    expect($contributorDeletes)->toHaveCount(2)
        ->and($referenceDeletes)->toHaveCount(2)
        ->and(Reference::query()->withoutOwnerScope()->where('parent_id', $root->getKey())->count())->toBe(0);
});
