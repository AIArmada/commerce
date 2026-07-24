<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('persons.database.tables.credential_assignments', 'credential_assignments'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('credentialable_type');
            $table->uuid('credentialable_id');
            $table->uuid('credential_id')->index();
            $table->uuid('issuing_institution_id')->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->date('date_obtained')->nullable();
            $table->date('date_expired')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestampsTz();

            $table->index(['credentialable_type', 'credentialable_id']);
        });
    }
};
