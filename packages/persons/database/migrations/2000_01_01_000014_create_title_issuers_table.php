<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        commerce_schema_create_if_missing(config('persons.database.tables.title_issuers', 'title_issuers'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('country_id')->nullable();
            $table->uuid('institution_id')->nullable()->index();
            $table->string('issuer_name');
            $table->string('issuer_type', 50)->index();
            $table->timestampsTz();
        });
    }
};
