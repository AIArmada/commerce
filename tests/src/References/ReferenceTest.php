<?php

declare(strict_types=1);

use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Models\Reference;

beforeEach(function (): void {
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
