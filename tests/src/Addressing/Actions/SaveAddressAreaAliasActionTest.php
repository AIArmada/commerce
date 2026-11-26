<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\AddressAreaAliasDeleter;
use AIArmada\Addressing\Contracts\AddressAreaAliasWriter;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

it('creates and updates only manually sourced aliases', function (): void {
    $this->seedCountry('MY');
    $area = AddressArea::factory()->create(['country_code' => 'MY']);
    $action = app(AddressAreaAliasWriter::class);

    $alias = AddressAreaName::query()->findOrFail($action->handle((string) $area->getKey(), ['name' => 'Kuala Lumpur City']));

    expect($alias->source)->toBe('manual')
        ->and($alias->name)->toBe('Kuala Lumpur City');

    $updated = AddressAreaName::query()->findOrFail($action->handle((string) $area->getKey(), ['name' => 'KL City'], (string) $alias->getKey()));

    expect($updated->name)->toBe('KL City')
        ->and($updated->source)->toBe('manual');
});

it('preserves optional alias fields when an update omits them', function (): void {
    $area = AddressArea::factory()->create(['country_code' => 'MY']);
    $alias = AddressAreaName::query()->create([
        'address_area_id' => $area->getKey(),
        'name' => 'Preferred name',
        'source' => 'manual',
        'name_type' => 'official',
        'is_preferred' => true,
    ]);

    $updated = AddressAreaName::query()->findOrFail(app(AddressAreaAliasWriter::class)->handle((string) $area->getKey(), ['name' => 'Renamed'], (string) $alias->getKey()));

    expect($updated->name_type)->toBe('official')
        ->and($updated->is_preferred)->toBeTrue();
});

it('validates alias name size and strict preferred booleans', function (): void {
    $area = AddressArea::factory()->create(['country_code' => 'MY']);
    $action = app(AddressAreaAliasWriter::class);

    expect(fn () => $action->handle((string) $area->getKey(), ['name' => str_repeat('a', 256)]))
        ->toThrow(ValidationException::class)
        ->and(fn () => $action->handle((string) $area->getKey(), ['name' => 'Alias', 'is_preferred' => 'false']))
        ->toThrow(ValidationException::class);
});

it('reports observer-cancelled alias saves and deletes as failures', function (): void {
    $area = AddressArea::factory()->create(['country_code' => 'MY']);
    $alias = AddressAreaName::query()->create([
        'address_area_id' => $area->getKey(),
        'name' => 'Manual alias',
        'source' => 'manual',
    ]);
    $savingEvent = 'eloquent.saving: ' . AddressAreaName::class;
    Event::listen($savingEvent, static fn (): bool => false);

    try {
        expect(fn () => app(AddressAreaAliasWriter::class)->handle((string) $area->getKey(), ['name' => 'Changed'], (string) $alias->getKey()))
            ->toThrow(DomainException::class);
    } finally {
        Event::forget($savingEvent);
    }

    $deletingEvent = 'eloquent.deleting: ' . AddressAreaName::class;
    Event::listen($deletingEvent, static fn (): bool => false);

    try {
        expect(fn () => app(AddressAreaAliasDeleter::class)->execute((string) $area->getKey(), (string) $alias->getKey()))
            ->toThrow(DomainException::class);
    } finally {
        Event::forget($deletingEvent);
    }

    expect($alias->fresh())->not->toBeNull();
});

it('refuses to update a provider sourced alias', function (): void {
    $this->seedCountry('MY');
    $area = AddressArea::factory()->create(['country_code' => 'MY']);
    $alias = AddressAreaName::query()->create([
        'address_area_id' => $area->getKey(),
        'name' => 'Provider name',
        'source' => 'my.provider',
    ]);

    expect(fn () => app(AddressAreaAliasWriter::class)->handle(
        (string) $area->getKey(),
        ['name' => 'Changed name'],
        (string) $alias->getKey(),
    ))->toThrow(ValidationException::class);

    expect($alias->fresh()->name)->toBe('Provider name');
});

it('refuses to delete a provider sourced alias', function (): void {
    $area = AddressArea::factory()->create(['country_code' => 'MY']);
    $alias = AddressAreaName::query()->create([
        'address_area_id' => $area->getKey(),
        'name' => 'Provider name',
        'source' => 'my.provider',
    ]);

    expect(fn () => app(AddressAreaAliasDeleter::class)->execute((string) $area->getKey(), (string) $alias->getKey()))
        ->toThrow(ValidationException::class);

    expect($alias->fresh())->not->toBeNull();
});

it('deletes a manually sourced alias', function (): void {
    $area = AddressArea::factory()->create(['country_code' => 'MY']);
    $alias = AddressAreaName::query()->create([
        'address_area_id' => $area->getKey(),
        'name' => 'Manual name',
        'source' => 'manual',
    ]);

    app(AddressAreaAliasDeleter::class)->execute((string) $area->getKey(), (string) $alias->getKey());

    expect($alias->fresh())->toBeNull();
});

it('checks provider ownership from the persisted alias instead of the submitted model', function (): void {
    $area = AddressArea::factory()->create(['country_code' => 'MY']);
    $providerArea = AddressArea::factory()->create(['country_code' => 'MY']);
    $alias = AddressAreaName::query()->create([
        'address_area_id' => $providerArea->getKey(),
        'name' => 'Provider name',
        'source' => 'my.provider',
    ]);
    $forgedAlias = $alias->replicate();
    $forgedAlias->id = $alias->getKey();
    $forgedAlias->source = 'manual';
    $forgedAlias->address_area_id = $area->getKey();

    expect(fn () => app(AddressAreaAliasWriter::class)->handle(
        (string) $area->getKey(),
        ['name' => 'Changed name'],
        (string) $forgedAlias->getKey(),
    ))->toThrow(ValidationException::class);

    expect($alias->fresh()->name)->toBe('Provider name')
        ->and($alias->fresh()->address_area_id)->toBe($providerArea->getKey());
});
