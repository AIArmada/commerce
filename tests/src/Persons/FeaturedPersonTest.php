<?php

declare(strict_types=1);

use AIArmada\Persons\Models\Person;

it('filters featured people and assigns curated positions', function (): void {
    $first = Person::factory()->create(['is_featured' => true]);
    $second = Person::factory()->create(['is_featured' => true]);
    $plain = Person::factory()->create();

    expect($first->order_column)->toBe(1)
        ->and($second->order_column)->toBe(2)
        ->and($plain->order_column)->toBeNull()
        ->and(Person::query()->featured()->ordered()->pluck('id')->all())
        ->toBe([$first->id, $second->id]);

    $plain->update(['is_featured' => true]);

    expect($plain->fresh()->order_column)->toBe(3)
        ->and(Person::query()->featured()->count())->toBe(3);
});

it('preserves explicit positions on create and appends re-featured people', function (): void {
    $first = Person::factory()->create(['is_featured' => true, 'order_column' => 10]);
    $second = Person::factory()->create(['is_featured' => true, 'order_column' => 20]);

    expect($first->order_column)->toBe(10)
        ->and($second->order_column)->toBe(20);

    $second->update(['is_featured' => false]);
    $second->update(['is_featured' => true]);

    expect($second->fresh()->order_column)->toBe(21)
        ->and(Person::query()->featured()->ordered()->pluck('id')->all())
        ->toBe([$first->id, $second->id]);
});
