<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('organizations.database.tables.organizations', 'organizations');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->index();
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->string('visibility')->default('private')->index();
            $table->uuid('created_by')->index();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('privatized_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampTz('last_state_change_at')->nullable();
            $table->timestampsTz();
        });
    }
};
