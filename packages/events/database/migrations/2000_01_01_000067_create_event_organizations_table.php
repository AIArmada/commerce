<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('events', 'jsonb');

        Schema::create(config('events.database.tables.organizations', 'organizations'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('owner_type')->nullable()->index();
            $table->string('owner_id')->nullable()->index();
            $table->string('name');
            $table->string('slug')->unique()->index();
            $table->text('bio')->nullable();
            $table->string('status')->default('active')->index();
            $table->string('visibility')->default('public')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
