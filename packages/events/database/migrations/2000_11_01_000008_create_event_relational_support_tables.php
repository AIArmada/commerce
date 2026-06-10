<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event as EventModel;
use AIArmada\Events\Services\EventContentSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $databaseConfig = (array) config('events.database', []);
        $tables = (array) ($databaseConfig['tables'] ?? []);
        $defaults = (array) config('events.defaults', []);

        $tablePrefix = (string) ($databaseConfig['table_prefix'] ?? 'event_');
        $jsonColumnType = (string) ($databaseConfig['json_column_type'] ?? commerce_json_column_type('events', 'jsonb'));

        $classificationsTable = (string) ($tables['classifications'] ?? $tablePrefix . 'classifications');
        $assetsTable = (string) ($tables['assets'] ?? $tablePrefix . 'assets');
        $submissionsTable = (string) ($tables['submissions'] ?? $tablePrefix . 'submissions');
        $reviewsTable = (string) ($tables['reviews'] ?? $tablePrefix . 'reviews');
        $changeNoticesTable = (string) ($tables['change_notices'] ?? $tablePrefix . 'change_notices');
        $attendanceTable = (string) ($tables['attendance'] ?? $tablePrefix . 'attendance');
        $engagementsTable = (string) ($tables['engagements'] ?? $tablePrefix . 'engagements');
        $occurrencesTable = (string) ($tables['occurrences'] ?? $tablePrefix . 'occurrences');

        Schema::create($classificationsTable, function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->nullableMorphs('assignable');
            $table->nullableMorphs('source');
            $table->string('group_key');
            $table->string('term_key');
            $table->string('term_label')->nullable();
            $table->unsignedInteger('order_column')->nullable();
            $table->{$jsonColumnType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['assignable_type', 'assignable_id', 'group_key'], 'event_classifications_assignable_group_index');
            $table->index(['group_key', 'term_key'], 'event_classifications_group_term_index');
        });

        Schema::create($assetsTable, function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->nullableMorphs('assignable');
            $table->nullableMorphs('asset');
            $table->string('role_key');
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->text('url')->nullable();
            $table->string('title')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('visibility')->default('public');
            $table->unsignedInteger('order_column')->nullable();

            $table->{$jsonColumnType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['assignable_type', 'assignable_id', 'role_key'], 'event_assets_assignable_role_index');
            $table->index(['role_key', 'visibility'], 'event_assets_role_visibility_index');
        });

        Schema::create($submissionsTable, function (Blueprint $table) use ($jsonColumnType, $defaults): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->foreignUuid('event_id');
            $table->nullableMorphs('submitted_by');
            $table->string('status')->default((string) ($defaults['event_submission_status'] ?? 'draft'));
            $table->timestampTz('submitted_at')->nullable();
            $table->text('notes')->nullable();
            $table->{$jsonColumnType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['event_id', 'status'], 'event_submissions_event_status_index');
        });

        Schema::create($reviewsTable, function (Blueprint $table) use ($jsonColumnType, $defaults): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->foreignUuid('event_id');
            $table->foreignUuid('event_submission_id')->nullable();
            $table->nullableMorphs('reviewed_by');
            $table->string('decision')->default((string) ($defaults['event_moderation_status'] ?? 'pending'));
            $table->string('reason_key')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->{$jsonColumnType}('before_snapshot')->nullable();
            $table->{$jsonColumnType}('after_snapshot')->nullable();
            $table->{$jsonColumnType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['event_id', 'decision'], 'event_reviews_event_decision_index');
            $table->index(['event_submission_id', 'decision'], 'event_reviews_submission_decision_index');
        });

        Schema::create($changeNoticesTable, function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->foreignUuid('event_id');
            $table->foreignUuid('replacement_event_id')->nullable();
            $table->foreignUuid('replacement_occurrence_id')->nullable();
            $table->string('change_key');
            $table->string('severity')->default('info');
            $table->string('status')->default('draft');
            $table->{$jsonColumnType}('changed_sections')->nullable();
            $table->{$jsonColumnType}('before_snapshot')->nullable();
            $table->{$jsonColumnType}('after_snapshot')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('retracted_at')->nullable();
            $table->{$jsonColumnType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['event_id', 'change_key'], 'event_change_notices_event_change_index');
            $table->index(['status', 'severity'], 'event_change_notices_state_severity_index');
        });

        Schema::create($attendanceTable, function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->foreignUuid('event_id');
            $table->foreignUuid('occurrence_id')->nullable();
            $table->foreignUuid('registration_id')->nullable();
            $table->nullableMorphs('attendee');
            $table->nullableMorphs('recorded_by');
            $table->string('source')->default('registration');
            $table->string('status')->default('present');
            $table->timestampTz('checked_in_at')->nullable();
            $table->{$jsonColumnType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['occurrence_id', 'status'], 'event_attendance_occurrence_status_index');
            $table->index(['registration_id', 'source'], 'event_attendance_registration_source_index');
        });

        Schema::create($engagementsTable, function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->foreignUuid('event_id');
            $table->foreignUuid('occurrence_id')->nullable();
            $table->nullableMorphs('actor');
            $table->string('type');
            $table->unsignedInteger('weight')->default(1);
            $table->{$jsonColumnType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['event_id', 'type'], 'event_engagements_event_type_index');
            $table->index(['occurrence_id', 'type'], 'event_engagements_occurrence_type_index');
        });

        Schema::table($occurrencesTable, function (Blueprint $table) use ($jsonColumnType, $defaults): void {
            $table->string('schedule_mode')->nullable()->after('participation_mode');
            $table->string('schedule_reference_key')->nullable()->after('schedule_mode');
            $table->{$jsonColumnType}('schedule_reference_payload')->nullable()->after('schedule_reference_key');
            $table->string('schedule_label')->nullable()->after('schedule_reference_payload');
            $table->string('registration_mode')->default((string) ($defaults['occurrence_registration_mode'] ?? 'free'))->after('schedule_label');
            $table->string('duplicate_strategy')->default((string) ($defaults['occurrence_duplicate_strategy'] ?? 'per_occurrence'))->after('registration_mode');
            $table->boolean('waitlist_enabled')->default(false)->after('duplicate_strategy');
            $table->boolean('approval_required')->default(false)->after('waitlist_enabled');
        });

        $synchronizer = app(EventContentSynchronizer::class);

        EventModel::query()
            ->withoutOwnerScope()
            ->orderBy('id')
            ->chunkById(100, static function ($events) use ($synchronizer): void {
                foreach ($events as $event) {
                    $owner = OwnerContext::fromTypeAndId(
                        is_string($event->getAttribute('owner_type')) ? $event->getAttribute('owner_type') : null,
                        is_scalar($event->getAttribute('owner_id')) ? (string) $event->getAttribute('owner_id') : null,
                    );

                    OwnerContext::withOwner($owner, static function () use ($synchronizer, $event): void {
                        $synchronizer->sync($event);
                    });
                }
            });
    }
};
