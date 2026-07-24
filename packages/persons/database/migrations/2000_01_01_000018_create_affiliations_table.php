<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('persons.database.tables.affiliations', 'affiliations'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('affiliatable_type');
            $table->uuid('affiliatable_id');
            $table->uuid('institution_id')->nullable()->index();
            $table->string('affiliation_type', 50);
            $table->date('joined_at')->nullable();
            $table->date('left_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestampsTz();

            $table->index(['affiliatable_type', 'affiliatable_id']);
        });
    }
};
