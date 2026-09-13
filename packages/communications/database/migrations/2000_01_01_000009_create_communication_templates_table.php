<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('communications', 'jsonb');

        Schema::create(config('communications.database.tables.templates', 'communication_templates'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category');
            $table->string('default_locale')->nullable();
            $table->string('status')->index();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('disabled_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id', 'key']);
            $table->index(['owner_type', 'owner_id', 'category']);
        });
    }
};
