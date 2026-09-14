<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Enums\ReportSeverity;
use AIArmada\CommerceSupport\Enums\ReportStatus;
use AIArmada\CommerceSupport\Models\NotificationPreference;
use AIArmada\CommerceSupport\Models\Report;
use AIArmada\CommerceSupport\Models\SavedSearch;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('reports');
    Schema::create('reports', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('reportable_type');
        $table->string('reportable_id');
        $table->string('reporter_type')->nullable();
        $table->string('reporter_id')->nullable();
        $table->string('report_type');
        $table->string('status');
        $table->string('severity');
        $table->string('title')->nullable();
        $table->text('message')->nullable();
        $table->string('reviewed_by_type')->nullable();
        $table->string('reviewed_by_id')->nullable();
        $table->timestampTz('reported_at')->nullable();
        $table->timestampTz('reviewed_at')->nullable();
        $table->timestampTz('resolved_at')->nullable();
        $table->timestampTz('rejected_at')->nullable();
        $table->timestampTz('archived_at')->nullable();
        $table->text('resolution')->nullable();
        $table->text('internal_notes')->nullable();
        $table->json('metadata')->nullable();
        $table->timestampsTz();
    });
});

it('ignores privileged report fields passed to mass assignment', function (): void {
    $report = new Report([
        'report_type' => 'spam',
        'title' => 'Looks spammy',
        'status' => 'resolved',
        'severity' => 'critical',
        'reporter_type' => 'App\\Models\\Admin',
        'reporter_id' => 'admin-1',
        'reviewed_by_type' => 'App\\Models\\Admin',
        'reviewed_by_id' => 'admin-1',
        'resolution' => 'forged',
        'internal_notes' => 'forged',
        'resolved_at' => '2025-01-01 00:00:00',
    ]);

    expect($report->report_type)->toBe('spam')
        ->and($report->title)->toBe('Looks spammy')
        ->and($report->status)->toBe(ReportStatus::Open)
        ->and($report->severity)->toBe(ReportSeverity::Medium)
        ->and($report->getAttribute('reporter_type'))->toBeNull()
        ->and($report->getAttribute('reporter_id'))->toBeNull()
        ->and($report->getAttribute('reviewed_by_type'))->toBeNull()
        ->and($report->getAttribute('reviewed_by_id'))->toBeNull()
        ->and($report->getAttribute('resolution'))->toBeNull()
        ->and($report->getAttribute('internal_notes'))->toBeNull()
        ->and($report->getAttribute('resolved_at'))->toBeNull();
});

it('ignores polymorphic identities passed to saved search mass assignment', function (): void {
    $search = new SavedSearch([
        'name' => 'my search',
        'user_type' => 'App\\Models\\Admin',
        'user_id' => 'admin-1',
        'searchable_type' => 'App\\Models\\Order',
        'searchable_id' => 'order-1',
    ]);

    expect($search->name)->toBe('my search')
        ->and($search->getAttribute('user_type'))->toBeNull()
        ->and($search->getAttribute('user_id'))->toBeNull()
        ->and($search->getAttribute('searchable_type'))->toBeNull()
        ->and($search->getAttribute('searchable_id'))->toBeNull();
});

it('ignores the owning user passed to notification preference mass assignment', function (): void {
    $preference = new NotificationPreference([
        'channel' => 'email',
        'user_type' => 'App\\Models\\Admin',
        'user_id' => 'admin-1',
    ]);

    expect($preference->channel)->toBe('email')
        ->and($preference->getAttribute('user_type'))->toBeNull()
        ->and($preference->getAttribute('user_id'))->toBeNull();
});

it('transitions reports through explicit review methods', function (): void {
    $reviewer = new Report(['report_type' => 'seed']);
    $reviewer->forceFill([
        'reportable_type' => Report::class,
        'reportable_id' => '00000000-0000-0000-0000-000000000000',
    ]);
    $reviewer->saveQuietly();

    $report = new Report(['report_type' => 'spam', 'title' => 't']);
    $report->reportable()->associate($reviewer);
    $report->reporter()->associate($reviewer);
    $report->save();

    expect($report->status)->toBe(ReportStatus::Open)
        ->and($report->reported_at)->not->toBeNull();

    expect($report->startReview($reviewer))->toBeTrue();

    $report->refresh();

    expect($report->status)->toBe(ReportStatus::UnderReview)
        ->and($report->reviewed_by_type)->toBe(Report::class)
        ->and($report->reviewed_at)->not->toBeNull();

    expect($report->resolve($reviewer, 'confirmed spam'))->toBeTrue();

    $report->refresh();

    expect($report->status)->toBe(ReportStatus::Resolved)
        ->and($report->resolution)->toBe('confirmed spam')
        ->and($report->resolved_at)->not->toBeNull();
});
