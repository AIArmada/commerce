<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('persons.database.tables.person_names', 'person_names'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('person_id')->index();
            $table->string('name_type', 50);
            $table->string('full_name');
            $table->string('language_code', 10);
            $table->boolean('is_primary')->default(false);
            $table->timestampsTz();

            $table->index(['person_id', 'name_type']);
        });
    }
};
