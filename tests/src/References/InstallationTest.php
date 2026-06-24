<?php

declare(strict_types=1);

use AIArmada\References\Models\Reference;
use AIArmada\References\ReferencesServiceProvider;
use Illuminate\Support\Facades\Schema;

// Load package helpers for tests (autoload.files in package composer.json
// is not inherited by the monorepo's root composer.json).
require_once __DIR__ . '/../../../packages/references/src/helpers.php';

test('service provider registers', function (): void {
    $providers = app()->getLoadedProviders();

    expect(isset($providers[ReferencesServiceProvider::class]))->toBeTrue();
});

test('config publishes and reads correctly', function (): void {
    expect(config('references.database.table_prefix'))->toBeString()->toBe('ref_');
    expect(config('references.database.tables.references'))->toBe('ref_references');
    expect(config('references.slug.source'))->toBe('title');
    expect(config('references.slug.max_length'))->toBe(200);
});

test('references table exists after migration', function (): void {
    expect(Schema::hasTable('ref_references'))->toBeTrue();
});

test('reference model instantiates with correct table name', function (): void {
    $ref = new Reference;

    expect($ref->getTable())->toBe('ref_references');
});

test('helper function returns correct table name', function (): void {
    expect(references_table('references'))->toBe('ref_references');
    expect(references_table('non_existent_key'))->toBe('non_existent_key');
});
