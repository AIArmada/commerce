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

it('stores event money as integer minor units', function (): void {
    $dir = __DIR__ . '/../../../packages/events/database/migrations/';

    foreach ([
        '2000_01_01_000009_create_event_venue_facilities_table.php',
        '2000_01_01_000010_create_event_facilities_table.php',
        '2000_01_01_000014_create_event_registrations_table.php',
        '2000_01_01_000017_create_event_registration_items_table.php',
    ] as $filename) {
        $content = file_get_contents($dir . $filename);

        expect($content)->not->toContain("decimal('price'")
            ->and($content)->not->toContain("decimal('fee_amount'")
            ->and($content)->not->toContain("decimal('unit_price'")
            ->and($content)->not->toContain("decimal('total_price'")
            ->and($content)->not->toContain("decimal('total_amount'");
    }
});
