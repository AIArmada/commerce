<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('filament-permissions.database.tables.access_policies', 'perm_access_policies');
        $jsonType = config('filament-permissions.database.json_column_type', 'json');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('effect'); // allow, deny
            $table->string('target_action');
            $table->string('target_resource')->nullable();
            $table->{$jsonType}('conditions');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('target_action');
            $table->index('target_resource');
            $table->index('priority');
            $table->index('slug');
            $table->index(['is_active', 'target_action', 'priority'], 'access_policies_active_lookup_index');
            $table->index(['valid_from', 'valid_until'], 'access_policies_validity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('filament-permissions.database.tables.access_policies', 'perm_access_policies'));
    }
};
