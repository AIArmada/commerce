<?php

declare(strict_types=1);

use Illuminate\Support\Str;

function cartPerformanceMigrationPath(): string
{
    $repoRoot = dirname(__DIR__, 4);

    return $repoRoot . '/packages/cart/database/migrations/2000_02_01_000001_create_carts_table.php';
}

function cartCasIndexMigrationPath(): string
{
    $repoRoot = dirname(__DIR__, 4);

    return $repoRoot . '/packages/cart/database/migrations/2026_09_12_162447_add_cas_lookup_index_to_carts_table.php';
}

it('does not use NOW() in PostgreSQL index predicates', function (): void {
    $path = cartPerformanceMigrationPath();

    $contents = file_get_contents($path);

    expect($contents)->toBeString();
    expect(Str::contains($contents, 'NOW()', true))->toBeFalse();
});

it('does not create analytics index by comparing json to json', function (): void {
    $path = cartPerformanceMigrationPath();

    $contents = file_get_contents($path);

    expect($contents)->toBeString();
    expect((bool) preg_match("/items\\s*!=\\s*'\\[\\]'::json(?!b)/i", $contents))->toBeFalse();
});

it('declares a composite index for CAS lookups', function (): void {
    $contents = file_get_contents(cartCasIndexMigrationPath());

    expect($contents)->toBeString();
    expect($contents)->toContain("['identifier', 'instance', 'version']");
});
