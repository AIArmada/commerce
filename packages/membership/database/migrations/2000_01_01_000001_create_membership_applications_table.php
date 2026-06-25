<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = (string) config('membership.database.json_column_type', commerce_json_column_type('membership', 'jsonb'));

        $tableName = (string) config('membership.database.tables.applications', 'membership_applications');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->foreignUuid('applicant_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('granted_role')->nullable();
            $table->text('justification');
            $table->foreignUuid('reviewer_id')->nullable();
            $table->text('reviewer_note')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->{$jsonType}('meta')->nullable();
            $table->timestampsTz();

            $table->index(['subject_type', 'subject_id']);
            $table->index('applicant_id');
            $table->index('status');
            $table->index('reviewer_id');
        });
    }
};
