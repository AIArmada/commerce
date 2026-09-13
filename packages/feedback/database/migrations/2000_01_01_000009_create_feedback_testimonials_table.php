<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonColumnType = commerce_json_column_type('feedback', 'jsonb');

        $addJsonColumn = function (Blueprint $table, string $column) use ($jsonColumnType): void {
            $table->{$jsonColumnType}($column)->nullable();
        };

        Schema::create(config('feedback.database.tables.testimonials', 'feedback_testimonials'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->foreignUuid('feedback_response_id')->nullable()->index();
            $table->foreignUuid('feedback_answer_id')->nullable()->index();

            $table->nullableUuidMorphs('subject');
            $table->nullableUuidMorphs('respondent');

            $table->text('quote');
            $table->string('display_name')->nullable();
            $table->string('display_title')->nullable();
            $table->string('display_organization')->nullable();

            $table->decimal('rating', 10, 2)->nullable();
            $table->string('status')->index();

            $table->timestampTz('permission_given_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('hidden_at')->nullable();

            $addJsonColumn($table, 'metadata');

            $table->timestampsTz();

            $table->index(['status', 'published_at']);
        });
    }
};
