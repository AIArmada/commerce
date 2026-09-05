<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('organizations.database.tables.members', 'organization_members');

        if (Schema::hasTable($tableName)) {
            return;
        }

        commerce_schema_create_if_missing($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->index();
            $table->foreignUuid('user_id')->index();
            $table->string('role')->index();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'user_id']);
        });
    }
};
