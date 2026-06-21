<?php

declare(strict_types=1);

use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Models\Communication;
use Illuminate\Support\Facades\Artisan;

test('dispatch-due command runs without error', function (): void {
    $exitCode = Artisan::call('communications:dispatch-due');
    expect($exitCode)->toBe(0);
});

test('prune command runs without error', function (): void {
    $exitCode = Artisan::call('communications:prune');
    expect($exitCode)->toBe(0);
});

test('expire command runs without error', function (): void {
    Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'expire-test',
        'status' => CommunicationStatus::Scheduled,
        'expires_at' => now()->subDay(),
    ]);

    $exitCode = Artisan::call('communications:expire');
    expect($exitCode)->toBe(0);
});

test('reconcile command runs without error', function (): void {
    $exitCode = Artisan::call('communications:reconcile');
    expect($exitCode)->toBe(0);
});

test('reconcile command accepts dry-run flag', function (): void {
    $exitCode = Artisan::call('communications:reconcile', ['--dry-run' => true]);
    expect($exitCode)->toBe(0);
});

test('dispatch-due command accepts dry-run flag', function (): void {
    $exitCode = Artisan::call('communications:dispatch-due', ['--dry-run' => true]);
    expect($exitCode)->toBe(0);
});

test('expire command accepts dry-run flag', function (): void {
    $exitCode = Artisan::call('communications:expire', ['--dry-run' => true]);
    expect($exitCode)->toBe(0);
});

test('prune command accepts dry-run flag', function (): void {
    $exitCode = Artisan::call('communications:prune', ['--dry-run' => true]);
    expect($exitCode)->toBe(0);
});

test('replay-webhooks command runs without error when no events exist', function (): void {
    $exitCode = Artisan::call('communications:replay-webhooks');
    expect($exitCode)->toBe(0);
});

test('replay-webhooks command accepts dry-run flag', function (): void {
    $exitCode = Artisan::call('communications:replay-webhooks', ['--dry-run' => true]);
    expect($exitCode)->toBe(0);
});

test('replay-webhooks command accepts force flag', function (): void {
    $exitCode = Artisan::call('communications:replay-webhooks', ['--force' => true]);
    expect($exitCode)->toBe(0);
});

test('replay-webhooks command accepts provider filter', function (): void {
    $exitCode = Artisan::call('communications:replay-webhooks', ['--provider' => 'sendgrid']);
    expect($exitCode)->toBe(0);
});

test('replay-webhooks command accepts communication filter', function (): void {
    $exitCode = Artisan::call('communications:replay-webhooks', ['--communication' => 'test-id']);
    expect($exitCode)->toBe(0);
});
