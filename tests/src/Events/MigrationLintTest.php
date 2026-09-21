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
