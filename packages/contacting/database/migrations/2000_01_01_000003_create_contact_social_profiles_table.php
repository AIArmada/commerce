<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('contacting.database.tables.social_profiles', 'social_profiles');
        $jsonColumnType = commerce_json_column_type('contacting', 'jsonb');

        $connection = Schema::getConnection();
        $validityIndexName = str_replace(['.', '-', ' '], '_', $tableName) . '_socialable_validity_index';
        $validityIndexColumns = [
            'socialable_type', 'socialable_id', 'platform', 'purpose',
            'is_primary', 'valid_from', 'valid_until', 'sort_order',
        ];

        Schema::create($tableName, function (Blueprint $table) use ($jsonColumnType, $tableName, $validityIndexColumns, $validityIndexName): void {
            $table->uuid('id')->primary();

            $table->nullableUuidMorphs('owner');
            $table->nullableUuidMorphs('socialable');

            $table->string('platform');
            $table->string('purpose')->default('general');
            $table->string('label')->nullable();

            $table->string('handle')->nullable();
            $table->text('url')->nullable();
            $table->text('normalized_url')->nullable();
            $table->string('display_name')->nullable();
            $table->string('external_id')->nullable();

            $table->boolean('is_primary')->default(false);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_verified')->default(false);

            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_until')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestamps();

            $table->index(['platform', 'purpose']);
            $table->index(['is_primary']);
            $table->index(['is_public']);
            $table->index(['is_verified']);
            $table->index(
                ['socialable_type', 'socialable_id', 'is_primary'],
                str_replace(['.', '-', ' '], '_', $tableName) . '_socialable_primary_index',
            );

            $table->index($validityIndexColumns, $validityIndexName);
        });

        $this->createPrimaryUniqueIndexes($tableName);
    }

    private function createPrimaryUniqueIndexes(string $tableName): void
    {
        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();
        $driver = ConnectionDriver::name($connection);
        $base = str_replace(['.', '-', ' '], '_', $tableName);

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf(
                'Contacting primary index migration cannot run on unsupported database driver [%s].',
                $driver,
            ));
        }
        $isPrimary = $driver === 'pgsql'
            ? $grammar->wrap('is_primary') . ' IS TRUE'
            : $grammar->wrap('is_primary') . ' = 1';
        $socialablePresent = $grammar->wrap('socialable_type') . ' IS NOT NULL AND '
            . $grammar->wrap('socialable_id') . ' IS NOT NULL';
        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s AND %s AND %s IS NOT NULL AND %s IS NOT NULL',
            $grammar->wrap($base . '_primary_owner_unique'),
            $grammar->wrapTable($tableName),
            implode(', ', array_map($grammar->wrap(...), ['owner_type', 'owner_id', 'socialable_type', 'socialable_id', 'platform', 'purpose'])),
            $isPrimary,
            $socialablePresent,
            $grammar->wrap('owner_type'),
            $grammar->wrap('owner_id'),
        ));
        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s AND %s AND %s IS NULL AND %s IS NULL',
            $grammar->wrap($base . '_primary_unique'),
            $grammar->wrapTable($tableName),
            implode(', ', array_map($grammar->wrap(...), ['socialable_type', 'socialable_id', 'platform', 'purpose'])),
            $isPrimary,
            $socialablePresent,
            $grammar->wrap('owner_type'),
            $grammar->wrap('owner_id'),
        ));
    }
};
