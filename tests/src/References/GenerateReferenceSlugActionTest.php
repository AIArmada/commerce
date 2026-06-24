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
