<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('feedback.database.tables.form_analytics', 'feedback_form_analytics');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('feedback_form_id')->unique('feedback_form_analytics_form_unique');
            $table->nullableMorphs('owner');

            $table->unsignedBigInteger('total_responses')->default(0);
            $table->unsignedBigInteger('completed_responses')->default(0);
            $table->decimal('average_score', 10, 2)->nullable();
            $table->decimal('max_score', 10, 2)->nullable();
            $table->decimal('completion_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('pending_review')->default(0);
            $table->unsignedBigInteger('rejected')->default(0);
            $table->unsignedBigInteger('spam')->default(0);
            $table->timestampTz('calculated_at');

            $table->timestampsTz();
        });
    }
};
