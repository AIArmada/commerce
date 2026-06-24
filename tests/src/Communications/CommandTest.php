<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\NotificationFamily;
use AIArmada\Communications\Enums\NotificationPriority;
use AIArmada\Communications\Enums\NotificationTrigger;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\NotificationInbox;
use Illuminate\Support\Facades\Artisan;

test('dispatch-due command runs without error', function (): void {
    $exitCode = Artisan::call('communications:dispatch-due');
    expect($exitCode)->toBe(0);
});

test('prune command runs without error', function (): void {
    $exitCode = Artisan::call('communications:prune');
    expect($exitCode)->toBe(0);
});

test('prune-inboxes command runs without error', function (): void {
    $exitCode = Artisan::call('communications:prune-inboxes');
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

test('prune-inboxes command accepts dry-run flag', function (): void {
    $exitCode = Artisan::call('communications:prune-inboxes', ['--dry-run' => true]);
    expect($exitCode)->toBe(0);
});

test('prune-inboxes command reports archived inbox entries in dry-run mode', function (): void {
    $user = User::create([
        'name' => 'Inbox User',
        'email' => 'inbox-user-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $communication = Communication::create([
        'direction' => CommunicationDirection::Internal,
        'category' => CommunicationCategory::Internal,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'prune-inboxes-test',
        'status' => CommunicationStatus::Completed,
    ]);

    NotificationInbox::create([
        'recipient_type' => $user::class,
        'recipient_id' => $user->id,
        'communication_id' => $communication->id,
        'family' => NotificationFamily::SystemAnnouncement,
        'priority' => NotificationPriority::Normal,
        'trigger' => NotificationTrigger::SystemAlert,
        'title' => 'Archived Inbox',
        'archived_at' => now()->subDays(100),
    ]);

    $exitCode = Artisan::call('communications:prune-inboxes', ['--dry-run' => true]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Would prune 1 inbox entries.');
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
