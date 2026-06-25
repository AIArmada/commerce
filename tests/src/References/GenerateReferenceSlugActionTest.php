<?php

declare(strict_types=1);

use AIArmada\References\Actions\GenerateReferenceSlugAction;
use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Models\Reference;

beforeEach(function (): void {
    $this->action = new GenerateReferenceSlugAction;
});

test('generates slug from title', function (): void {
    $reference = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'Introduction to Fiqh',
    ]);

    $slug = $this->action->execute($reference);

    expect($slug)->toBe('introduction-to-fiqh');
});

test('ensures uniqueness by appending suffix', function (): void {
    $first = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Published,
        'title' => 'Unique Title',
    ]);

    $second = Reference::create([
        'type' => ReferenceType::Article,
        'status' => ReferenceStatus::Published,
        'title' => 'Unique Title',
    ]);

    $slugFirst = $this->action->execute($first);
    $slugSecond = $this->action->execute($second);

    expect($slugFirst)->toBe('unique-title');
    expect($slugSecond)->toBe('unique-title-1');
});

test('respects custom slug source field', function (): void {
    $reference = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'Some Title',
        'author' => 'Imam Al-Ghazali',
    ]);

    $slug = $this->action->execute($reference, 'author');

    expect($slug)->toBe('imam-al-ghazali');
});

test('uses configured slug source when no source is passed', function (): void {
    config()->set('references.slug.source', 'author');

    $reference = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'Ignored Title',
        'author' => 'Configured Author',
    ]);

    expect($this->action->execute($reference))->toBe('configured-author');
});

test('keeps unique slug suffixes within configured maximum length', function (): void {
    config()->set('references.slug.max_length', 12);

    $existing = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'A Very Long Reference Title',
    ]);
    $existing->forceFill(['slug' => 'a-very-long-'])->save();

    $reference = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'A Very Long Reference Title',
    ]);

    $slug = $this->action->execute($reference);

    expect($slug)->toBe('a-very-lon-1')
        ->and(mb_strlen($slug))->toBeLessThanOrEqual(12);
});
