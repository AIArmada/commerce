<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('filament-authz.database.tables.permission_groups', 'authz_permission_groups');
        $jsonType = config('filament-authz.database.json_column_type', 'json');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignUuid('parent_id')->nullable();
            $table->{$jsonType}('implicit_abilities')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->index('parent_id');
            $table->index('slug');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('filament-authz.database.tables.permission_groups', 'authz_permission_groups'));
    }
};
