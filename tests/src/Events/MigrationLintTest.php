<?php

declare(strict_types=1);

it('has no soft deletes in event migrations', function (): void {
    $dir = __DIR__ . '/../../../packages/events/database/migrations/';
    $files = glob($dir . '*.php');
    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $content = file_get_contents($file);

        expect($content)->not->toContain('softDeletes')
            ->and($content)->not->toContain('softDeletesTz')
            ->and($content)->not->toContain("foreign('")
            ->and($content)->not->toContain('constrained(');
    }
});

it('has uuid primary keys in all create table migrations', function (): void {
    $dir = __DIR__ . '/../../../packages/events/database/migrations/';
    $files = glob($dir . '*.php');
    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $content = file_get_contents($file);
        // Only check migrations that CREATE tables (not alter/add)
        if (str_contains($content, 'Schema::create(')) {
            expect($content)->toContain("uuid('id')->primary()");
        }
    }
});

it('defines portable lookup indexes and nullable inherited mode defaults', function (): void {
    $dir = __DIR__ . '/../../../packages/events/database/migrations/';

    $registrations = file_get_contents($dir . '2000_01_01_000014_create_event_registrations_table.php');
    $search = file_get_contents($dir . '2000_01_01_000053_create_event_search_documents_table.php');
    $events = file_get_contents($dir . '2000_01_01_000001_create_events_table.php');
    $occurrences = file_get_contents($dir . '2000_01_01_000002_create_event_occurrences_table.php');
    $sessions = file_get_contents($dir . '2000_01_01_000003_create_event_sessions_table.php');

    expect($registrations)
        ->toContain("'event_registrations_external_order_index'")
        ->toContain("'event_registrations_event_idempotency_index'")
        ->and($search)
        ->toContain("'event_search_documents_identity_unique'")
        ->toContain('USING GIN (facets)')
        ->not->toContain('jsonb_path_ops')
        ->and($events)
        ->toContain("boolean('issue_passes_for_free')->nullable()->default(null)")
        ->and($occurrences)
        ->toContain("boolean('issue_passes_for_free')->nullable()->default(null)")
        ->and($sessions)
        ->toContain("boolean('issue_passes_for_free')->nullable()->default(null)");
});
