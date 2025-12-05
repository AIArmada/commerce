<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('filament-permissions.database.tables.role_templates', 'perm_role_templates');
        $jsonType = config('filament-permissions.database.json_column_type', 'json');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignUuid('parent_id')->nullable();
            $table->string('guard_name');
            $table->{$jsonType}('default_permissions')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('parent_id');
            $table->index('guard_name');
            $table->index('is_active');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('filament-permissions.database.tables.role_templates', 'perm_role_templates'));
    }
};
