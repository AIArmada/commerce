<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Facades\Schema;

it('adds nullable morph IDs using Laravel default morph key types', function (): void {
    $tableName = 'commerce_morph_key_test';
    $originalMorphKeyType = SchemaBuilder::$defaultMorphKeyType;

    try {
        foreach ([
            'int' => 'integer',
            'uuid' => 'varchar',
            'ulid' => 'varchar',
        ] as $morphKeyType => $expectedColumnType) {
            Schema::dropIfExists($tableName);
            Schema::defaultMorphKeyType($morphKeyType);

            Schema::create($tableName, function (Blueprint $table): void {
                commerce_morph_key($table, 'actor');
            });

            $column = collect(Schema::getColumns($tableName))
                ->firstWhere('name', 'actor_id');

            expect($column)->toBeArray()
                ->and($column['type'])->toBe($expectedColumnType)
                ->and($column['nullable'])->toBeTrue();
        }
    } finally {
        Schema::dropIfExists($tableName);
        Schema::defaultMorphKeyType($originalMorphKeyType);
    }
});

function helpersAndMigrationStubsRepoPath(string $relativePath): string
{
    return dirname(__DIR__, 3) . '/' . $relativePath;
}
