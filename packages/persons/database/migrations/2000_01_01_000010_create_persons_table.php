<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('persons', 'jsonb');

        Schema::create(config('persons.database.tables.persons', 'persons'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('family_name', 100)->nullable();
            $table->string('middle_name', 100)->nullable();
            $table->string('gender', 50)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->uuid('nationality_country_id')->nullable()->index();
            $table->string('slug')->nullable();
            $table->string('searchable_name', 512)->nullable()->index();
            $table->{$jsonType}('bio')->nullable();
            $table->string('status', 50)->nullable()->index();
            $table->timestampsTz();
        });
    }
};
