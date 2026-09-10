<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Models\Reference;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\MediaCollections\Models\Observers\MediaObserver;
use Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer;
use Spatie\MediaLibrary\Support\FileRemover\DefaultFileRemover;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

beforeEach(function (): void {
    config()->set('references.owner.enabled', true);

    $this->reference = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'The Art of Islamic Living',
        'author' => 'Ibn Kathir',
        'publisher' => 'Dar Al-Kutub',
        'year' => 2024,
    ]);
});

test('creates a reference with minimal attributes', function (): void {
    expect($this->reference->id)->toBeUuid();
    expect($this->reference->title)->toBe('The Art of Islamic Living');
    expect($this->reference->author)->toBe('Ibn Kathir');
    expect($this->reference->publisher)->toBe('Dar Al-Kutub');
    expect($this->reference->year)->toBe(2024);
});

test('uses UUID primary key', function (): void {
    expect($this->reference->getKeyType())->toBe('string');
    expect($this->reference->getIncrementing())->toBeFalse();
});

test('casts type and status enums correctly', function (): void {
    $ref = Reference::find($this->reference->id);

    expect($ref->type)->toBeInstanceOf(ReferenceType::class);
    expect($ref->type->value)->toBe('book');
    expect($ref->status)->toBeInstanceOf(ReferenceStatus::class);
    expect($ref->status->value)->toBe('draft');
});

test('casts reference_parts and metadata as array', function (): void {
    $ref = Reference::create([
        'type' => ReferenceType::Article,
        'status' => ReferenceStatus::Published,
        'title' => 'Test Article',
        'reference_parts' => [['type' => 'jilid', 'value' => '1']],
        'metadata' => ['source' => 'library'],
    ]);

    expect($ref->reference_parts)->toBeArray();
    expect($ref->reference_parts[0]['type'])->toBe('jilid');
    expect($ref->metadata)->toBeArray();
    expect($ref->metadata['source'])->toBe('library');
});

test('casts year as integer', function (): void {
    expect($this->reference->year)->toBeInt();
});

test('generates slug on creation', function (): void {
    expect($this->reference->slug)->toBe('the-art-of-islamic-living');
});

test('does not regenerate slug on update', function (): void {
    $this->reference->update(['title' => 'Updated Title']);

    expect($this->reference->refresh()->slug)->toBe('the-art-of-islamic-living');
});

test('scopePublished returns only published references', function (): void {
    $published = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Published,
        'title' => 'Published Book',
    ]);

    $results = Reference::published()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($published->id);
});

test('scopeByType filters by reference type', function (): void {
    Reference::create([
        'type' => ReferenceType::Article,
        'status' => ReferenceStatus::Draft,
        'title' => 'Test Article',
    ]);

    $results = Reference::byType(ReferenceType::Book)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($this->reference->id);
});

test('has config-driven table name', function (): void {
    expect((new Reference)->getTable())->toBe('references');

    config()->set('references.database.tables.references', 'custom_references');
    expect((new Reference)->getTable())->toBe('custom_references');
});

test('owner scoping follows the commerce support contract', function (): void {
    config()->set('references.owner.enabled', true);

    $ownerA = User::query()->create([
        'name' => 'References Owner A',
        'email' => 'references-owner-a-' . uniqid() . '@example.test',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'References Owner B',
        'email' => 'references-owner-b-' . uniqid() . '@example.test',
        'password' => 'secret',
    ]);

    $referenceA = OwnerContext::withOwner($ownerA, fn (): Reference => Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'Owner A Reference',
    ]));
    $referenceB = OwnerContext::withOwner($ownerB, fn (): Reference => Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'Owner B Reference',
    ]));

    expect($referenceA->owner_type)->toBe($ownerA->getMorphClass())
        ->and($referenceA->owner_id)->toBe($ownerA->getKey())
        ->and(OwnerContext::withOwner($ownerA, fn (): int => Reference::query()->count()))->toBe(1)
        ->and(OwnerContext::withOwner($ownerB, fn (): int => Reference::query()->count()))->toBe(1)
        ->and($referenceB->owner_id)->not->toBe($referenceA->owner_id);
});

test('deleting a reference removes its complete subtree and media', function (): void {
    config()->set('media-library.file_namer', DefaultFileNamer::class);
    config()->set('media-library.file_remover_class', DefaultFileRemover::class);
    config()->set('media-library.path_generator', DefaultPathGenerator::class);
    config()->set('media-library.max_file_size', 1024 * 1024);
    Storage::fake('public');
    Media::observe(MediaObserver::class);

    $root = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'Root Reference',
    ]);
    $child = Reference::create([
        'type' => ReferenceType::Article,
        'status' => ReferenceStatus::Draft,
        'title' => 'Child Reference',
        'parent_id' => $root->getKey(),
    ]);
    $grandchild = Reference::create([
        'type' => ReferenceType::Article,
        'status' => ReferenceStatus::Draft,
        'title' => 'Grandchild Reference',
        'parent_id' => $child->getKey(),
    ]);

    $addMedia = static function (Reference $reference, string $name): void {
        $media = Media::create([
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

        Storage::disk('public')->put($media->getKey() . '/' . $media->file_name, 'data');
    };

    $addMedia($root, 'root');
    $addMedia($child, 'child');
    $addMedia($grandchild, 'grandchild');

    $root->delete();

    expect(Reference::query()->whereKey($this->reference->getKey())->exists())->toBeTrue()
        ->and(Reference::query()->whereKey([$root->getKey(), $child->getKey(), $grandchild->getKey()])->count())->toBe(0)
        ->and(Media::query()->where('model_type', $root->getMorphClass())->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('slug configuration fails loudly when invalid', function (mixed $source, mixed $maxLength): void {
    config()->set('references.slug.source', $source);
    config()->set('references.slug.max_length', $maxLength);

    expect(fn (): mixed => (new Reference)->getSlugOptions())
        ->toThrow(InvalidArgumentException::class);
})->with([
    [null, 200],
    ['unknown_attribute', 200],
    ['year', 200],
    ['title', 0],
    ['title', 'invalid'],
]);
