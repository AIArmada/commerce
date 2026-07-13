<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\SlugGenerator;
use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Models\Reference;

test('generates slug from title', function (): void {
    $reference = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'Introduction to Fiqh',
    ]);

    $slug = SlugGenerator::generate($reference, 'title');

    expect($slug)->toBe('introduction-to-fiqh');
});

test('ensures uniqueness by appending suffix', function (): void {
    $first = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Published,
        'title' => 'Unique Title',
        'slug' => 'unique-title',
    ]);

    $second = Reference::create([
        'type' => ReferenceType::Article,
        'status' => ReferenceStatus::Published,
        'title' => 'Unique Title',
    ]);

    $slugSecond = SlugGenerator::generate($second, 'title');

    expect($slugSecond)->toBe('unique-title-1');
});

test('respects custom slug source field', function (): void {
    $reference = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'Some Title',
        'author' => 'Imam Al-Ghazali',
    ]);

    $slug = SlugGenerator::generate($reference, 'author');

    expect($slug)->toBe('imam-al-ghazali');
});

test('keeps unique slug suffixes within configured maximum length', function (): void {
    $existing = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'A Very Long Reference Title',
        'slug' => 'a-very-long-',
    ]);

    $reference = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'A Very Long Reference Title',
    ]);

    $slug = SlugGenerator::generate($reference, 'title', maxLength: 12);

    expect($slug)->toStartWith('a-very-long-')
        ->and(mb_strlen($slug))->toBeLessThanOrEqual(12);
});
