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
        $tableName = (string) config('pricing.database.tables.price_lists', 'price_lists');
        $connection = Schema::getConnection();
        $driver = ConnectionDriver::name($connection);

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf('Pricing identity migration cannot run on unsupported database driver [%s].', $driver));
        }

        Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->string('currency', 3)->default('MYR');
            $table->integer('priority')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('deactivated_at')->nullable();

            // Optional: Link to customer or segment
            $table->foreignUuid('customer_id')->nullable();
            $table->foreignUuid('segment_id')->nullable();

            // Scheduling
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();

            $table->nullableUuidMorphs('owner');

            $table->timestampsTz();

            // Indexes
            $table->index(['is_active', 'priority']);
            $table->index(['starts_at', 'ends_at']);
            $table->index('customer_id');
            $table->index('segment_id');
            $table->index('is_default');
            $table->index('currency');
            $table->unique(['owner_type', 'owner_id', 'slug'], str_replace(['.', '-', ' '], '_', $tableName) . '_owner_slug_unique');
        });

        $grammar = $connection->getQueryGrammar();
        $indexPrefix = str_replace(['.', '-', ' '], '_', $tableName);
        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s IS NULL AND %s IS NULL',
            $grammar->wrap($indexPrefix . '_global_slug_unique'),
            $grammar->wrapTable($tableName),
            $grammar->wrap('slug'),
            $grammar->wrap('owner_type'),
            $grammar->wrap('owner_id'),
        ));

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s, %s, %s) WHERE %s AND %s IS NOT NULL AND %s IS NOT NULL',
            $grammar->wrap($indexPrefix . '_owner_currency_default_unique'),
            $grammar->wrapTable($tableName),
            $grammar->wrap('owner_type'),
            $grammar->wrap('owner_id'),
            $grammar->wrap('currency'),
            $grammar->wrap('is_default'),
            $grammar->wrap('owner_type'),
            $grammar->wrap('owner_id'),
        ));

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s AND %s IS NULL AND %s IS NULL',
            $grammar->wrap($indexPrefix . '_global_currency_default_unique'),
            $grammar->wrapTable($tableName),
            $grammar->wrap('currency'),
            $grammar->wrap('is_default'),
            $grammar->wrap('owner_type'),
            $grammar->wrap('owner_id'),
        ));
    }
};
