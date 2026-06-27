<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

test('commerce:install command is registered', function (): void {
    $commands = Artisan::all();

    expect(array_key_exists('commerce:install', $commands))->toBeTrue();
});

test('commerce:install can list tags', function (): void {
    $exitCode = Artisan::call('commerce:install', ['--list' => true]);

    expect($exitCode)->toBe(0);
});

test('commerce:install can dry-run (migrations only by default)', function (): void {
    $exitCode = Artisan::call('commerce:install', ['--all' => true, '--dry-run' => true]);

    expect($exitCode)->toBe(0);
});

test('commerce:install can dry-run with configs', function (): void {
    $exitCode = Artisan::call('commerce:install', ['--all' => true, '--dry-run' => true, '--with-config' => true]);

    expect($exitCode)->toBe(0);
});
