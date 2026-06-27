<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

test('commerce:publish-migrations command is registered', function (): void {
    $commands = Artisan::all();

    expect(array_key_exists('commerce:publish-migrations', $commands))->toBeTrue();
});

test('commerce:publish-migrations can list tags', function (): void {
    $exitCode = Artisan::call('commerce:publish-migrations', ['--list' => true]);

    expect($exitCode)->toBe(0);
});

test('commerce:publish-migrations can dry-run publish all', function (): void {
    $exitCode = Artisan::call('commerce:publish-migrations', ['--all' => true, '--dry-run' => true]);

    expect($exitCode)->toBe(0);
});
