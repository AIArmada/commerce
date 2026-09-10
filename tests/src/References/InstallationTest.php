<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
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
    expect(config('references.database.table_prefix'))->toBeString()->toBe('');
    expect(config('references.database.tables.references'))->toBe('references');
    expect(config('references.slug.source'))->toBe('title');
    expect(config('references.slug.max_length'))->toBe(200);
    expect(config('references.owner.enabled'))->toBeTrue();
    expect(config('references.owner.include_global'))->toBeFalse();
});

test('references table exists after migration', function (): void {
    expect(Schema::hasTable('references'))->toBeTrue();
    expect(Schema::hasColumn('references', 'owner_type'))->toBeTrue();
    expect(Schema::hasColumn('references', 'owner_id'))->toBeTrue();
    expect(Schema::hasColumn('references', 'reference_parts'))->toBeTrue();
    expect(Schema::hasColumn('references', 'part_type'))->toBeFalse();
    expect(Schema::hasColumn('references', 'part_number'))->toBeFalse();
    expect(Schema::hasColumn('references', 'part_label'))->toBeFalse();
});

test('references declares its commerce support dependency', function (): void {
    $manifest = json_decode(
        (string) file_get_contents(dirname(__DIR__, 3) . '/packages/references/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($manifest['require']['aiarmada/commerce-support'])->toBe('self.version');
});

test('standalone installation can load commerce support primitives', function (): void {
    expect(class_exists(OwnerContext::class))->toBeTrue()
        ->and(function_exists('commerce_json_column_type'))->toBeTrue()
        ->and(function_exists('commerce_schema_create_if_missing'))->toBeTrue();
});

test('reference model instantiates with correct table name', function (): void {
    $ref = new Reference;

    expect($ref->getTable())->toBe('references');
});

test('helper function returns correct table name', function (): void {
    expect(references_table('references'))->toBe('references');
    expect(references_table('non_existent_key'))->toBe('non_existent_key');
});
