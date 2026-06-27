<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('contacting.database.tables.contact_snapshots', 'contact_snapshots');
        $jsonColumnType = config('contacting.database.json_column_type', 'json');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();

            $table->nullableMorphs('owner');
            $table->nullableMorphs('snapshotable');

            $table->string('snapshot_type');
            $table->uuid('source_id')->nullable();
            $table->string('source_type')->nullable();

            $table->string('reason')->nullable();
            $table->string('label')->nullable();
            $table->string('channel')->nullable();

            $table->text('value')->nullable();
            $table->text('normalized_value')->nullable();
            $table->text('url')->nullable();
            $table->text('display_value')->nullable();

            $table->boolean('is_public')->default(true);

            $table->{$jsonColumnType}('payload')->nullable();
            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestamps();

            $table->index(['snapshot_type']);
            $table->index(['channel']);
            $table->index(['reason']);
            $table->index(['is_public']);
        });
    }
};
