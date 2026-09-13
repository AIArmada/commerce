<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('persons.database.tables.credential_definitions', 'credential_definitions'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->string('short_form', 50)->nullable();
            $table->string('field', 100)->nullable();
            $table->string('credential_type', 50)->index();
            $table->string('language_code', 10)->nullable();
            $table->timestampsTz();
        });
    }
};
