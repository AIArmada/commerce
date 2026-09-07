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

it('uses the centralized morph key helper in both audit migration stubs', function (): void {
    $createStub = file_get_contents(helpersAndMigrationStubsRepoPath('packages/commerce-support/database/migrations/1970_01_01_000002_create_audits_table.php.stub'));
    $fixStub = file_get_contents(helpersAndMigrationStubsRepoPath('packages/commerce-support/database/migrations/1970_01_01_000003_fix_audits_user_actor_column_type.php.stub'));

    expect($createStub)->toBeString()
        ->and($fixStub)->toBeString()
        ->and($createStub)->toContain('commerce_morph_key($table, $morphPrefix)')
        ->and($createStub)->not->toContain("config('commerce-support.database.morph_key_type'")
        ->and($fixStub)->toContain('commerce_morph_key($table, $morphPrefix)')
        ->and($fixStub)->not->toContain("config('commerce-support.database.morph_key_type'")
        ->and($fixStub)->not->toContain('$desiredMorphKeyType');
});

function helpersAndMigrationStubsRepoPath(string $relativePath): string
{
    return dirname(__DIR__, 3) . '/' . $relativePath;
}
