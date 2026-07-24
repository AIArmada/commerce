<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('persons.database.tables.title_assignments', 'title_assignments'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('titleable_type');
            $table->uuid('titleable_id');
            $table->uuid('title_id')->index();
            $table->uuid('issuer_id')->nullable();
            $table->date('date_awarded')->nullable();
            $table->date('date_expired')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestampsTz();

            $table->index(['titleable_type', 'titleable_id']);
        });
    }
};
