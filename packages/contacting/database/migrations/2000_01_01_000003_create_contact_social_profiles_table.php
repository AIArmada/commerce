<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('contacting.database.tables.social_profiles', 'social_profiles');
        $jsonColumnType = config('contacting.database.json_column_type', 'json');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) use ($jsonColumnType): void {
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
        });
    }
};
