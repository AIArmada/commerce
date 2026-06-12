<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonColumnType = config('feedback.database.json_column_type', 'jsonb');

        $addJsonColumn = function (Blueprint $table, string $column) use ($jsonColumnType): void {
            if ($jsonColumnType === 'jsonb') {
                $table->jsonb($column)->nullable();

                return;
            }

            $table->json($column)->nullable();
        };

        // feedback_forms
        Schema::create(config('feedback.database.tables.forms', 'feedback_forms'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('purpose')->index();
            $table->string('status')->index();
            $table->string('visibility')->index();

            $table->nullableUuidMorphs('subject');

            $table->boolean('is_anonymous_allowed')->default(true);
            $table->boolean('is_anonymity_optional')->default(false);
            $table->boolean('is_login_required')->default(false);
            $table->boolean('is_one_response_per_respondent')->default(false);
            $table->boolean('is_edit_after_submit_allowed')->default(false);

            $table->timestampTz('opens_at')->nullable();
            $table->timestampTz('closes_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('archived_at')->nullable();

            $addJsonColumn($table, 'settings');
            $addJsonColumn($table, 'metadata');

            $table->nullableUuidMorphs('created_by');

            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id']);
            $table->index(['status', 'visibility']);
        });

        // feedback_sections
        Schema::create(config('feedback.database.tables.sections', 'feedback_sections'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->foreignUuid('feedback_form_id')->index();
            $table->string('key')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('order_column')->default(0);

            $addJsonColumn($table, 'settings');
            $addJsonColumn($table, 'metadata');

            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id']);
            $table->index(['feedback_form_id', 'order_column']);
        });

        // feedback_questions
        Schema::create(config('feedback.database.tables.questions', 'feedback_questions'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->foreignUuid('feedback_form_id')->index();
            $table->foreignUuid('feedback_section_id')->nullable()->index();

            $table->string('key')->index();
            $table->string('type')->index();
            $table->string('label');
            $table->text('description')->nullable();
            $table->text('help_text')->nullable();
            $table->string('placeholder')->nullable();

            $table->boolean('is_required')->default(false);
            $table->boolean('is_scored')->default(false);
            $table->unsignedInteger('order_column')->default(0);

            $addJsonColumn($table, 'validation_rules');
            $addJsonColumn($table, 'visibility_rules');
            $addJsonColumn($table, 'scoring_rules');
            $addJsonColumn($table, 'settings');
            $addJsonColumn($table, 'metadata');

            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id']);
            $table->index(['feedback_form_id', 'key']);
            $table->index(['feedback_form_id', 'order_column']);
        });

        // feedback_question_options
        Schema::create(config('feedback.database.tables.question_options', 'feedback_question_options'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->foreignUuid('feedback_question_id')->index();

            $table->string('label');
            $table->string('value')->index();
            $table->decimal('score', 10, 2)->nullable();
            $table->unsignedInteger('order_column')->default(0);

            $addJsonColumn($table, 'metadata');

            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id']);
            $table->index(['feedback_question_id', 'order_column']);
        });

        // feedback_responses
        Schema::create(config('feedback.database.tables.responses', 'feedback_responses'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->foreignUuid('feedback_form_id')->index();
            $table->foreignUuid('feedback_invitation_id')->nullable()->index();

            $table->nullableUuidMorphs('subject');
            $table->nullableUuidMorphs('respondent');

            $table->string('status')->index();
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_editable')->default(false);

            $table->decimal('score', 10, 2)->nullable();
            $table->decimal('max_score', 10, 2)->nullable();

            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('marked_spam_at')->nullable();

            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $addJsonColumn($table, 'metadata');

            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['respondent_type', 'respondent_id']);
            $table->index(['feedback_form_id', 'status']);
            $table->index(['submitted_at']);
        });

        // feedback_answers
        Schema::create(config('feedback.database.tables.answers', 'feedback_answers'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->foreignUuid('feedback_response_id')->index();
            $table->foreignUuid('feedback_question_id')->index();

            $addJsonColumn($table, 'value');

            $table->text('text_value')->nullable();
            $table->decimal('number_value', 12, 4)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->date('date_value')->nullable();
            $table->timestampTz('datetime_value')->nullable();

            $table->decimal('score', 10, 2)->nullable();

            $addJsonColumn($table, 'metadata');

            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id']);
            $table->index(['feedback_response_id', 'feedback_question_id']);
            $table->index(['feedback_question_id', 'number_value']);
            $table->index(['feedback_question_id', 'score']);
        });

        // feedback_invitations
        Schema::create(config('feedback.database.tables.invitations', 'feedback_invitations'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->foreignUuid('feedback_form_id')->index();
            $table->nullableUuidMorphs('recipient');

            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->string('token_hash')->unique();
            $table->string('status')->index();

            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('expires_at')->nullable();

            $addJsonColumn($table, 'metadata');

            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id']);
            $table->index(['recipient_type', 'recipient_id']);
            $table->index(['feedback_form_id', 'status']);
        });

        // feedback_templates
        Schema::create(config('feedback.database.tables.templates', 'feedback_templates'), function (Blueprint $table) use ($addJsonColumn): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->string('name');
            $table->string('slug')->index();
            $table->string('purpose')->index();
            $table->string('category')->nullable()->index();
            $table->string('status')->index();

            $addJsonColumn($table, 'definition');
            $addJsonColumn($table, 'settings');
            $addJsonColumn($table, 'metadata');

            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('archived_at')->nullable();

            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id']);
            $table->index(['purpose', 'status']);
        });

        // feedback_testimonials
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

            $table->index(['owner_type', 'owner_id']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['respondent_type', 'respondent_id']);
            $table->index(['status', 'published_at']);
        });
    }
};
