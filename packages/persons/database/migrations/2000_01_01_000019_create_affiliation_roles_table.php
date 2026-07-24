<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('persons.database.tables.affiliation_roles', 'affiliation_roles'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('affiliation_id')->index();
            $table->string('role_name', 150);
            $table->string('department', 150)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestampsTz();
        });
    }
};
